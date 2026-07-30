<?php
/**
 * FormsService — the single home for the forms module's write rules:
 * form save (create + id-based field sync), version fork, delete
 * (leaf/chain), submission create (with the form.submitted workflow dispatch)
 * and submission delete.
 *
 * Shared by the UI endpoints (api/forms/*.php) and the REST API
 * (api/v1/resources/forms.php). Each caller passes an ActorContext + canonical
 * input; this layer validates + writes and returns the affected id(s) or throws
 * ServiceError. It never emits HTTP. The AI form-generation + settings endpoints
 * are UI-only and stay out of here.
 *
 * Canonical behaviour = the API resource's (see docs/design/service-layer.md):
 * an empty field label / unknown field_type is a 422 (the UI silently dropped /
 * blindly stored), an unknown field id in a submission is a 422 (was a raw FK
 * error), a frozen (non-leaf) version can't be edited/forked (409), and delete
 * is leaf-only unless the chain flag is set. Timestamps are written UTC.
 *
 * ⚙️ Side effect: a successful submission dispatches the `form.submitted`
 * workflow event with a label-keyed answers map (+ the first email answer) —
 * the "new starter form → tickets" automation. It fires after commit and its
 * errors are swallowed so a workflow can never break a submission.
 */

require_once __DIR__ . '/../service_context.php';
require_once __DIR__ . '/../form_logic.php';
require_once dirname(__DIR__, 2) . '/workflow/includes/engine.php';

class FormsService
{
    // 'section' is a heading, not a question — see ANSWERABLE_TYPES.
    const FIELD_TYPES = ['text', 'textarea', 'email', 'number', 'checkbox', 'checkboxes', 'dropdown', 'radio', 'section'];

    /** The types that actually collect an answer. A 'section' never produces submission data. */
    const ANSWERABLE_TYPES = ['text', 'textarea', 'email', 'number', 'checkbox', 'checkboxes', 'dropdown', 'radio'];

    /** Operators a conditional-visibility rule may use. Mirrors assets/js/form-logic.js. */
    const CONDITION_OPS = ['equals', 'not_equals', 'contains', 'is_empty', 'is_not_empty', 'greater_than', 'less_than'];

    // ======================================================================
    //  Forms
    // ======================================================================

    /** Create (no id) or update (id present) a form + its fields. Returns ['id','created']. */
    public static function saveForm(PDO $conn, ActorContext $ctx, array $in): array
    {
        if (!empty($in['id'])) {
            $formId  = (int)$in['id'];
            $current = self::loadFormRow($conn, $formId);      // 404 if gone
            self::requireLeaf($current);                       // 409 on frozen versions
            if (!array_diff_key($in, ['id' => true])) {
                throw new ServiceError('validation', 'missing_field', 'No fields to update.');
            }
            $title = array_key_exists('title', $in) ? trim((string)$in['title']) : $current['title'];
            if ($title === '') {
                throw new ServiceError('validation', 'invalid_field', "'title' cannot be empty.");
            }
            $fields = null;
            if (array_key_exists('fields', $in)) {
                if (!is_array($in['fields'])) {
                    throw new ServiceError('validation', 'invalid_field', "'fields' must be an array.");
                }
                $fields = self::validateFields($in['fields']);
            }

            $conn->beginTransaction();
            try {
                $conn->prepare(
                    "UPDATE forms SET title = ?, description = ?, is_active = ?, is_portal_visible = ?,
                            requires_approval = ?, approver_id = ?,
                            modified_by = ?, modified_date = UTC_TIMESTAMP()
                     WHERE id = ?"
                )->execute([
                    $title,
                    array_key_exists('description', $in) ? trim((string)$in['description']) : $current['description'],
                    array_key_exists('is_active', $in) ? (int)(bool)$in['is_active'] : (int)$current['is_active'],
                    // Only touched when sent, so an adapter that knows nothing about
                    // the portal can never silently withdraw a catalogue form.
                    array_key_exists('is_portal_visible', $in)
                        ? (int)(bool)$in['is_portal_visible']
                        : (int)($current['is_portal_visible'] ?? 0),
                    // Catalogue-request approval (#928), same incremental rule.
                    array_key_exists('requires_approval', $in)
                        ? (int)(bool)$in['requires_approval']
                        : (int)($current['requires_approval'] ?? 0),
                    array_key_exists('approver_id', $in)
                        ? (($in['approver_id'] === null || $in['approver_id'] === '') ? null : (int)$in['approver_id'])
                        : ($current['approver_id'] ?? null),
                    $ctx->actorId,
                    $formId,
                ]);
                if ($fields !== null) {
                    self::syncFields($conn, $formId, $fields);
                }
                $conn->commit();
            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                throw $e;
            }
            return ['id' => $formId, 'created' => false];
        }

        $title = trim((string)($in['title'] ?? ''));
        if ($title === '') {
            throw new ServiceError('validation', 'missing_field', "'title' is required.");
        }
        $fields = self::validateFields(is_array($in['fields'] ?? null) ? $in['fields'] : []);

        $conn->beginTransaction();
        try {
            $conn->prepare(
                "INSERT INTO forms (title, description, is_active, is_portal_visible, requires_approval, approver_id, created_by, modified_by, version_number, created_date, modified_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            )->execute([
                $title,
                trim((string)($in['description'] ?? '')),
                isset($in['is_active']) ? (int)(bool)$in['is_active'] : 1,
                // Fail closed: a new form is NOT offered to customers unless
                // someone deliberately says so.
                isset($in['is_portal_visible']) ? (int)(bool)$in['is_portal_visible'] : 0,
                isset($in['requires_approval']) ? (int)(bool)$in['requires_approval'] : 0,
                (isset($in['approver_id']) && $in['approver_id'] !== null && $in['approver_id'] !== '') ? (int)$in['approver_id'] : null,
                $ctx->actorId,
                $ctx->actorId,
            ]);
            $formId = (int)$conn->lastInsertId();
            // Same path as an update, so a brand-new form's conditions get resolved
            // and stored by exactly the same code rather than a second copy of it.
            self::syncFields($conn, $formId, $fields);
            $conn->commit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            throw $e;
        }
        return ['id' => $formId, 'created' => true];
    }

    /** Delete one version (leaf only) or the whole chain. Returns ['id','versions_deleted']. */
    public static function deleteForm(PDO $conn, ActorContext $ctx, int $id, bool $chain = false): array
    {
        $current = self::loadFormRow($conn, $id);              // 404 if gone

        if ($chain) {
            $rootId = (int)$current['id'];
            $hops = 0;
            while ($hops < 500) {
                $stmt = $conn->prepare("SELECT parent_form_id FROM forms WHERE id = ?");
                $stmt->execute([$rootId]);
                $parent = $stmt->fetchColumn();
                if (!$parent) break;
                $rootId = (int)$parent;
                $hops++;
            }
            $ids   = [$rootId];
            $queue = [$rootId];
            while ($queue) {
                $place = implode(',', array_fill(0, count($queue), '?'));
                $stmt = $conn->prepare("SELECT id FROM forms WHERE parent_form_id IN ($place)");
                $stmt->execute($queue);
                $children = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
                if (!$children) break;
                $ids   = array_merge($ids, $children);
                $queue = $children;
            }
        } else {
            if (((int)$current['child_count']) > 0) {
                throw new ServiceError('conflict', 'conflict', 'This version has newer versions built on it. Delete the whole chain with ?chain=true, or delete the current (leaf) version.');
            }
            $ids = [(int)$current['id']];
        }

        $place = implode(',', array_fill(0, count($ids), '?'));
        $conn->beginTransaction();
        try {
            $conn->prepare(
                "DELETE sd FROM form_submission_data sd
                 INNER JOIN form_submissions s ON sd.submission_id = s.id
                 WHERE s.form_id IN ($place)"
            )->execute($ids);
            $conn->prepare("DELETE FROM form_submissions WHERE form_id IN ($place)")->execute($ids);
            $conn->prepare("DELETE FROM form_fields WHERE form_id IN ($place)")->execute($ids);
            // Children before parents so fk_forms_parent never blocks.
            foreach (array_reverse($ids) as $fid) {
                $conn->prepare("DELETE FROM forms WHERE id = ?")->execute([$fid]);
            }
            $conn->commit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            throw $e;
        }
        return ['id' => $id, 'versions_deleted' => count($ids)];
    }

    // ======================================================================
    //  Versions
    // ======================================================================

    /** Fork the leaf into a new version. Returns ['id','version_number']. */
    public static function createVersion(PDO $conn, ActorContext $ctx, int $parentId): array
    {
        if ($parentId <= 0) {
            throw new ServiceError('validation', 'missing_field', 'parent_form_id is required');
        }
        $src = self::loadFormRow($conn, $parentId);            // 404 if gone
        self::requireLeaf($src);                               // 409 on frozen versions

        $conn->beginTransaction();
        try {
            $conn->prepare(
                "INSERT INTO forms (title, description, is_active, is_portal_visible, created_by, modified_by, parent_form_id, version_number, created_date, modified_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
            )->execute([
                $src['title'],
                $src['description'],
                (int)$src['is_active'],
                // Carried to the new version DELIBERATELY. A new version is the
                // editable leaf and the catalogue lists leaves, so dropping this
                // would silently withdraw a published form from the portal the
                // moment someone edited it — a disappearance nobody would connect
                // to having pressed Save.
                (int)($src['is_portal_visible'] ?? 0),
                $ctx->actorId,
                $ctx->actorId,
                $parentId,
                (int)$src['version_number'] + 1,
            ]);
            $newId = (int)$conn->lastInsertId();

            // Copied row by row rather than INSERT..SELECT because a condition stores
            // the form_fields.id it depends on: a bulk copy would leave the new
            // version's rules pointing at the OLD version's fields, so editing the
            // copy would change what the frozen original shows. Retired (soft-deleted)
            // fields are left behind — they exist to keep old answers readable on the
            // version they belong to, not to follow the form forward.
            // NOT $src — that already holds the source form's row, and this method
            // still reads $src['version_number'] after the copy is done.
            $srcStmt = $conn->prepare(
                "SELECT id, field_type, label, options, is_required, sort_order, config
                   FROM form_fields
                  WHERE form_id = ? AND is_deleted = 0
                  ORDER BY sort_order, id"
            );
            $srcStmt->execute([$parentId]);
            $srcFields = $srcStmt->fetchAll(PDO::FETCH_ASSOC);

            $ins = $conn->prepare(
                "INSERT INTO form_fields (form_id, field_type, label, options, is_required, sort_order, config)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $idMap = [];
            foreach ($srcFields as $i => $f) {
                $ins->execute([$newId, $f['field_type'], $f['label'], $f['options'], $f['is_required'], $i, $f['config']]);
                $idMap[(int)$f['id']] = (int)$conn->lastInsertId();
            }

            $updCfg = $conn->prepare("UPDATE form_fields SET config = ? WHERE id = ?");
            foreach ($srcFields as $f) {
                if (empty($f['config'])) continue;
                $config = json_decode((string)$f['config'], true);
                if (!is_array($config) || !isset($config['visible_if']['rules'])) continue;
                foreach ($config['visible_if']['rules'] as $r => $rule) {
                    $oldRef = (int)($rule['field'] ?? 0);
                    $config['visible_if']['rules'][$r]['field'] = $idMap[$oldRef] ?? $oldRef;
                }
                $updCfg->execute([json_encode($config), $idMap[(int)$f['id']]]);
            }
            $conn->commit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            throw $e;
        }
        return ['id' => $newId, 'version_number' => (int)$src['version_number'] + 1];
    }

    // ======================================================================
    //  Submissions
    // ======================================================================

    /**
     * Validate + record a submission, then dispatch form.submitted. $data is a
     * field_id => value map. Returns the submission id.
     *
     * @param ?int $portalUserId  a REQUESTER (users.id) submitting through the
     *   self-service request catalogue, or null for the analyst paths.
     *
     *   It is a separate argument rather than $ctx->actorId because the two are
     *   DIFFERENT ID SPACES. `submitted_by` has no foreign key and every reader
     *   LEFT JOINs it to `analysts`, so writing a users.id there would silently
     *   attribute a customer's request to whichever analyst happened to share
     *   the number. They go in different columns and exactly one is set.
     */
    public static function submitForm(PDO $conn, ActorContext $ctx, int $formId, array $data, ?int $portalUserId = null): int
    {
        $form = self::loadFormRow($conn, $formId);             // 404 if gone
        if (!(int)$form['is_active']) {
            throw new ServiceError('conflict', 'conflict', 'This form is inactive and cannot accept submissions.');
        }

        // A requester may only submit a form actually offered in the catalogue.
        // Checked HERE, not just in the adapter, so the rule holds however this
        // is reached — knowing a hidden form's id must not be enough.
        if ($portalUserId !== null && !(int)($form['is_portal_visible'] ?? 0)) {
            throw new ServiceError('not_found', 'not_found', 'Form not found.');
        }

        // Ordered, and without retired fields: a soft-deleted question is no longer
        // asked, so it can neither be answered nor be required. Order matters because
        // conditions are evaluated in it (a rule may only look backwards).
        $stmt = $conn->prepare(
            "SELECT id, label, field_type, is_required, options, config, sort_order
               FROM form_fields
              WHERE form_id = ? AND is_deleted = 0
              ORDER BY sort_order, id"
        );
        $stmt->execute([$formId]);
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $fieldsById = [];
        foreach ($fields as $f) {
            $fieldsById[(int)$f['id']] = $f;
        }

        // Unknown field ids are a 422 (the UI inserts them blindly → FK error).
        foreach (array_keys($data) as $fieldId) {
            if (!isset($fieldsById[(int)$fieldId])) {
                throw new ServiceError('validation', 'invalid_field', "Unknown field id for this form: {$fieldId}");
            }
            if ($fieldsById[(int)$fieldId]['field_type'] === 'section') {
                throw new ServiceError('validation', 'invalid_field', "Field {$fieldId} is a section heading and takes no answer.");
            }
        }

        // Normalise values (bools and arrays accepted natively).
        $normalised = [];
        foreach ($data as $fieldId => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (is_array($value)) {
                $value = json_encode(array_values($value));
            }
            $normalised[(int)$fieldId] = (string)$value;
        }

        // Which questions were actually ASKED, given the answers given. Re-derived here
        // rather than trusted from the client, because "required" has to mean something
        // even when the browser never ran our JS: without this, hiding a required field
        // client-side would still 422 on submit, and a crafted post could skip a
        // required question by pretending it was hidden.
        $visible = formLogicVisibility($fields, $normalised);

        // An answer to a question that was never shown is dropped, so what we store is
        // what the person was actually asked.
        foreach (array_keys($normalised) as $fieldId) {
            if (empty($visible[$fieldId])) {
                unset($normalised[$fieldId]);
            }
        }

        // Per-type required + format validation.
        foreach ($fields as $field) {
            $fid  = (int)$field['id'];
            $val  = array_key_exists($fid, $normalised) ? $normalised[$fid] : '';
            $type = $field['field_type'];

            // Headings collect nothing; hidden questions were never asked.
            if ($type === 'section' || empty($visible[$fid])) {
                continue;
            }

            if ($field['is_required']) {
                $isEmpty = false;
                if ($val === '' || $val === null) {
                    $isEmpty = true;
                } elseif ($type === 'checkbox' && (string)$val === '0') {
                    $isEmpty = true;
                } elseif ($type === 'checkboxes') {
                    $decoded = json_decode((string)$val, true);
                    $isEmpty = !is_array($decoded) || count($decoded) === 0;
                }
                if ($isEmpty) {
                    throw new ServiceError('validation', 'missing_field', '"' . $field['label'] . '" is required.');
                }
            }
            if ($val !== '' && $val !== null) {
                if ($type === 'email' && !filter_var((string)$val, FILTER_VALIDATE_EMAIL)) {
                    throw new ServiceError('validation', 'invalid_field', '"' . $field['label'] . '" must be a valid email address.');
                }
                if ($type === 'number' && !is_numeric((string)$val)) {
                    throw new ServiceError('validation', 'invalid_field', '"' . $field['label'] . '" must be a number.');
                }

                // A choice field must be answered with one of ITS OWN choices.
                // This was never checked: the value was whatever the client
                // posted, so a select could carry arbitrary text straight into
                // the stored answers. Harmless-ish while only analysts could
                // reach it; not once customers can.
                if (in_array($type, ['dropdown', 'radio', 'checkboxes'], true)) {
                    $options = json_decode((string)($field['options'] ?? '[]'), true);
                    if (is_array($options) && $options) {
                        $options = array_map('strval', $options);
                        $chosen  = ($type === 'checkboxes')
                            ? (json_decode((string)$val, true) ?: [])
                            : [(string)$val];
                        foreach ($chosen as $one) {
                            if (!in_array((string)$one, $options, true)) {
                                throw new ServiceError('validation', 'invalid_field',
                                    '"' . $field['label'] . '" has an option that is not on its list.');
                            }
                        }
                    }
                }
            }
        }

        // Catalogue-request approval (#928): gate a PORTAL submission behind the
        // form's designated approver, if one is configured. Only portal submissions
        // are gated — the feature auto-raises a ticket for the requester, and an
        // analyst filling a form internally has no requester to raise one for. A form
        // flagged requires_approval but with no approver is treated as unconfigured so
        // it can never strand a request nobody can clear.
        $gateApproverId = ($portalUserId !== null && !empty($form['requires_approval']) && !empty($form['approver_id']))
            ? (int) $form['approver_id']
            : null;
        $approvalStatus = $gateApproverId !== null ? 'pending' : 'not_required';

        $conn->beginTransaction();
        try {
            // Exactly one submitter column is populated — see the $portalUserId
            // note on this method.
            $conn->prepare(
                "INSERT INTO form_submissions (form_id, submitted_by, submitted_by_user_id, submitted_date, approval_status, approver_id)
                 VALUES (?, ?, ?, UTC_TIMESTAMP(), ?, ?)"
            )->execute([
                $formId,
                $portalUserId !== null ? null : $ctx->actorId,
                $portalUserId,
                $approvalStatus,
                $gateApproverId,
            ]);
            $submissionId = (int)$conn->lastInsertId();

            $ins = $conn->prepare("INSERT INTO form_submission_data (submission_id, field_id, field_value) VALUES (?, ?, ?)");
            foreach ($normalised as $fieldId => $value) {
                $ins->execute([$submissionId, $fieldId, $value]);
            }
            $conn->commit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            throw $e;
        }

        // Workflow dispatch — label-keyed answers + first email answer, fired after
        // commit and swallowed on error (never breaks a submission).
        try {
            $submissionFields = [];
            $submissionEmail  = '';
            foreach ($normalised as $fieldId => $value) {
                if (!isset($fieldsById[$fieldId])) continue;
                $label = $fieldsById[$fieldId]['label'];
                $decoded = json_decode($value, true);
                $flat = is_array($decoded) ? implode(', ', $decoded) : $value;
                $submissionFields[$label] = $flat;
                if ($submissionEmail === '' && $fieldsById[$fieldId]['field_type'] === 'email' && $flat !== '') {
                    $submissionEmail = $flat;
                }
            }
            $payload = [
                'form' => [
                    'id'   => $formId,
                    'name' => $form['title'],
                ],
                'submission' => [
                    'id'     => $submissionId,
                    'email'  => $submissionEmail,
                    'fields' => $submissionFields,
                ],
            ];
            // A gated request must NOT fire form.submitted: an admin's create-ticket
            // rule on that event would jump the approval gate and raise the ticket
            // anyway. It fires catalogue_request.submitted instead — the hook to
            // notify the approver that something is waiting for them.
            if ($gateApproverId !== null) {
                $payload['approver'] = ['id' => $gateApproverId];
                WorkflowEngine::dispatch('catalogue_request.submitted', $payload);
            } else {
                WorkflowEngine::dispatch('form.submitted', $payload);
            }
        } catch (Exception $wfEx) {
            error_log('Workflow dispatch error in form submission: ' . $wfEx->getMessage());
        }

        return $submissionId;
    }

    /** Delete a submission (+ its data). $formId scopes the 404 when supplied. Returns the id. */
    public static function deleteSubmission(PDO $conn, ActorContext $ctx, int $submissionId, ?int $formId = null): int
    {
        if ($formId !== null) {
            $stmt = $conn->prepare("SELECT id FROM form_submissions WHERE id = ? AND form_id = ?");
            $stmt->execute([$submissionId, $formId]);
            if (!$stmt->fetchColumn()) {
                throw new ServiceError('not_found', 'not_found', 'Submission not found on this form.');
            }
        } else {
            $stmt = $conn->prepare("SELECT id FROM form_submissions WHERE id = ?");
            $stmt->execute([$submissionId]);
            if (!$stmt->fetchColumn()) {
                throw new ServiceError('not_found', 'not_found', 'Submission not found.');
            }
        }
        $conn->beginTransaction();
        try {
            $conn->prepare("DELETE FROM form_submission_data WHERE submission_id = ?")->execute([$submissionId]);
            $conn->prepare("DELETE FROM form_submissions WHERE id = ?")->execute([$submissionId]);
            $conn->commit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            throw $e;
        }
        return $submissionId;
    }

    // ======================================================================
    //  Internals
    // ======================================================================

    /** Load a form row (with child_count for the leaf check), or throw 404. */
    private static function loadFormRow(PDO $conn, int $id): array
    {
        $stmt = $conn->prepare(
            "SELECT f.*, (SELECT COUNT(*) FROM forms ch WHERE ch.parent_form_id = f.id) AS child_count
             FROM forms f WHERE f.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new ServiceError('not_found', 'not_found', 'Form not found.');
        }
        return $row;
    }

    /** 409 unless the form is the chain leaf (the current editable version). */
    private static function requireLeaf(array $formRow): void
    {
        if (((int)$formRow['child_count']) > 0) {
            throw new ServiceError('conflict', 'conflict', 'This is a frozen historical version. Use the current (leaf) version of the chain.');
        }
    }

    /**
     * Validate an incoming fields array — 422s where the raw UI silently drops
     * or blindly stores. Returns rows ready for the id-based sync.
     *
     * Each row may carry an `id`: the form_fields.id it is editing. Absent (or null)
     * means a brand-new field. That id is what keeps a respondent's answer attached
     * to the question they actually answered when the builder reorders things.
     *
     * A conditional rule's `field` may be either an existing form_fields.id or the
     * string "idx:N", meaning "the field at position N of THIS payload" — needed
     * because a rule can point at a question that is itself being created by this
     * same save and has no id yet. syncFields() resolves "idx:N" once the ids exist.
     */
    private static function validateFields(array $fields): array
    {
        // Position of each already-saved field in this payload, so a rule can be
        // checked against the order the user is actually looking at.
        $indexById = [];
        foreach ($fields as $i => $field) {
            if (is_array($field) && !empty($field['id'])) {
                $indexById[(int)$field['id']] = $i;
            }
        }

        $out = [];
        foreach ($fields as $i => $field) {
            if (!is_array($field)) {
                throw new ServiceError('validation', 'invalid_field', "fields[{$i}] must be an object.");
            }
            $label = trim((string)($field['label'] ?? ''));
            if ($label === '') {
                throw new ServiceError('validation', 'invalid_field', "fields[{$i}] needs a non-empty 'label'.");
            }
            $type = (string)($field['field_type'] ?? 'text');
            if (!in_array($type, self::FIELD_TYPES, true)) {
                throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: unknown field_type '{$type}'. One of: " . implode(', ', self::FIELD_TYPES) . '.');
            }
            $options = $field['options'] ?? null;
            if (is_array($options)) {
                $options = json_encode(array_values($options));
            } elseif ($options !== null && !is_string($options)) {
                throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: 'options' must be an array.");
            }

            // A heading collects nothing, so "required" would be a promise we could
            // never keep — reject it rather than store a flag the renderers ignore.
            $isRequired = (int)(bool)($field['is_required'] ?? false);
            if ($type === 'section') {
                if ($isRequired) {
                    throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: a 'section' heading cannot be required.");
                }
                $options = null;
            }

            $out[] = [
                'id'          => !empty($field['id']) ? (int)$field['id'] : null,
                'field_type'  => $type,
                'label'       => $label,
                'options'     => $options,
                'is_required' => $isRequired,
                'config'      => self::validateFieldConfig($field['config'] ?? null, $i, $fields, $indexById),
            ];
        }
        return $out;
    }

    /**
     * Validate one field's `config` JSON (today: the conditional-visibility rule).
     * Returns the config as an array, or null for "no settings" — which is what every
     * pre-existing field has, and why upgrading changes nothing about how a form looks.
     *
     * The important rule enforced here: a condition may only reference an EARLIER
     * answerable field. That single constraint is what makes circular conditions
     * (A shows when B is set, B shows when A is set) structurally impossible, so
     * neither evaluator ever has to detect a cycle at render time.
     */
    private static function validateFieldConfig($config, int $i, array $fields, array $indexById): ?array
    {
        if ($config === null || $config === '') {
            return null;
        }
        if (is_string($config)) {
            $config = json_decode($config, true);
            if (!is_array($config)) {
                throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: 'config' is not valid JSON.");
            }
        }
        if (!is_array($config)) {
            throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: 'config' must be an object.");
        }

        $vif = $config['visible_if'] ?? null;
        if ($vif === null) {
            return $config ?: null;
        }
        if (!is_array($vif) || !isset($vif['rules']) || !is_array($vif['rules'])) {
            throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: 'visible_if' needs a 'rules' array.");
        }
        if (!$vif['rules']) {
            // An empty rule list means "no condition" — drop it rather than store a
            // shape that later reads as a condition that can never be satisfied.
            unset($config['visible_if']);
            return $config ?: null;
        }

        $match = (($vif['match'] ?? 'all') === 'any') ? 'any' : 'all';
        $clean = [];
        foreach ($vif['rules'] as $r => $rule) {
            if (!is_array($rule)) {
                throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: rule {$r} must be an object.");
            }
            $op = (string)($rule['op'] ?? 'equals');
            if (!in_array($op, self::CONDITION_OPS, true)) {
                throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: unknown condition operator '{$op}'. One of: " . implode(', ', self::CONDITION_OPS) . '.');
            }

            // Resolve the referenced field to a position in this payload.
            $ref = $rule['field'] ?? null;
            if (is_string($ref) && strpos($ref, 'idx:') === 0) {
                $refIndex = (int)substr($ref, 4);
                $refValue = $ref;
            } else {
                $refId = (int)$ref;
                if (!isset($indexById[$refId])) {
                    throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: condition references field {$refId}, which is not on this form.");
                }
                $refIndex = $indexById[$refId];
                $refValue = $refId;
            }
            if (!isset($fields[$refIndex])) {
                throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: condition references position {$refIndex}, which does not exist.");
            }
            if ($refIndex >= $i) {
                throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: a condition can only depend on an earlier question.");
            }
            $refType = (string)($fields[$refIndex]['field_type'] ?? 'text');
            if (!in_array($refType, self::ANSWERABLE_TYPES, true)) {
                throw new ServiceError('validation', 'invalid_field', "fields[{$i}]: a condition cannot depend on a '{$refType}', which collects no answer.");
            }

            $clean[] = [
                'field' => $refValue,
                'op'    => $op,
                'value' => (string)($rule['value'] ?? ''),
            ];
        }

        $config['visible_if'] = ['match' => $match, 'rules' => $clean];
        return $config;
    }

    /**
     * Sync a form's fields BY ID.
     *
     * This used to be positional — it updated the existing rows in order, so dragging
     * a question to a new place rewrote the labels while the answers stayed put, and
     * every historic submission silently started reading against the wrong questions.
     * Removing a field hard-deleted the trailing row's answers outright. Now each
     * payload row carries the id it is editing, a field with no id is genuinely new,
     * and a field that disappears is SOFT deleted so past answers survive.
     *
     * Two passes: the ids have to exist before a condition that points at a
     * brand-new field ("idx:N") can be resolved to one.
     */
    private static function syncFields(PDO $conn, int $formId, array $fields): void
    {
        // Ids that really belong to this form. A payload id outside this set is
        // ignored rather than trusted — otherwise a crafted save could re-point
        // another form's field (and, with it, another form's answers).
        $stmt = $conn->prepare("SELECT id FROM form_fields WHERE form_id = ?");
        $stmt->execute([$formId]);
        $ownIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $ownIds = array_flip($ownIds);

        $upd = $conn->prepare(
            "UPDATE form_fields
                SET field_type = ?, label = ?, options = ?, is_required = ?, sort_order = ?, is_deleted = 0
              WHERE id = ? AND form_id = ?"
        );
        $ins = $conn->prepare(
            "INSERT INTO form_fields (form_id, field_type, label, options, is_required, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        // ---- Pass 1: write the fields, remember which id each position ended up as.
        $idByIndex = [];
        $keptIds   = [];
        foreach ($fields as $i => $f) {
            $id = $f['id'] ?? null;
            if ($id !== null && isset($ownIds[$id])) {
                $upd->execute([$f['field_type'], $f['label'], $f['options'], $f['is_required'], $i, $id, $formId]);
            } else {
                $ins->execute([$formId, $f['field_type'], $f['label'], $f['options'], $f['is_required'], $i]);
                $id = (int)$conn->lastInsertId();
            }
            $idByIndex[$i] = $id;
            $keptIds[]     = $id;
        }

        // ---- Retire whatever is no longer on the form, WITHOUT touching its answers.
        if ($keptIds) {
            $place = implode(',', array_fill(0, count($keptIds), '?'));
            $conn->prepare("UPDATE form_fields SET is_deleted = 1 WHERE form_id = ? AND id NOT IN ($place)")
                 ->execute(array_merge([$formId], $keptIds));
        } else {
            $conn->prepare("UPDATE form_fields SET is_deleted = 1 WHERE form_id = ?")->execute([$formId]);
        }

        // ---- Pass 2: store the conditions, now that every referenced field has an id.
        $updCfg = $conn->prepare("UPDATE form_fields SET config = ? WHERE id = ? AND form_id = ?");
        foreach ($fields as $i => $f) {
            $config = $f['config'] ?? null;
            if (is_array($config) && isset($config['visible_if']['rules'])) {
                foreach ($config['visible_if']['rules'] as $r => $rule) {
                    $ref = $rule['field'] ?? null;
                    if (is_string($ref) && strpos($ref, 'idx:') === 0) {
                        $refIndex = (int)substr($ref, 4);
                        // validateFields already proved this position exists and is earlier.
                        $config['visible_if']['rules'][$r]['field'] = $idByIndex[$refIndex] ?? 0;
                    } else {
                        $config['visible_if']['rules'][$r]['field'] = (int)$ref;
                    }
                }
            }
            $updCfg->execute([
                $config ? json_encode($config) : null,
                $idByIndex[$i],
                $formId,
            ]);
        }
    }
}
