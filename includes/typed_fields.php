<?php
/**
 * TypedFields — the shared engine for user-defined, strongly-typed fields.
 *
 * One definition somewhere says "this thing has a field called Warranty Expiry,
 * and it is a date". One value row holds the answer in a column matching its
 * type. This file owns everything between those two facts: what the types are,
 * how a raw input becomes a stored value, what counts as valid, and how the
 * required rule works.
 *
 * WHAT THIS FILE DOES NOT OWN
 * ---------------------------
 * - **Which fields a thing has.** Each module answers that with its own query
 *   and hands the result in as canonical definitions (see defFrom* below). The
 *   CMDB resolves them from a class; Assets will resolve them from the field
 *   sets attached to a type plus any attached to the individual asset. Trying to
 *   generalise that query would force one attachment model on both.
 * - **Authorisation.** Multi-tenancy gates, module access and actor scope stay
 *   in the calling service. A security rule living in a generic layer degrades
 *   silently, and not toward safety. CmdbService::assertObjectRefsInCompany()
 *   is the live example: this file checks a reference EXISTS and points at the
 *   right kind of thing; only CmdbService decides whether the actor may see it.
 * - **Reading values for display.** Not yet — see the note on readValues in
 *   docs/design/flexible-asset-fields.md §4.4. The CMDB's read path joins for
 *   reference labels under a tenancy filter, which is module business.
 *
 * SHAPE OF A DEFINITION (what callers pass in)
 * --------------------------------------------
 *   [
 *     'id'         => int,      // the definition row's id
 *     'key'        => string,   // stable machine key (property_key / field_key)
 *     'label'      => string,   // display name, used in error messages
 *     'type'       => string,   // one of self::TYPES
 *     'required'   => bool,
 *     'config'     => array,    // per-type settings; [] where a module has none
 *     'ref_kind'   => ?string,  // 'ref' only: which registry entry
 *     'ref_target' => ?int,     // 'ref' only: optional narrowing (e.g. a class id)
 *   ]
 *
 * SHAPE OF A SCHEMA (where the values live)
 * -----------------------------------------
 *   [
 *     'value_table'  => 'cmdb_object_properties',
 *     'owner_column' => 'object_id',
 *     'def_column'   => 'property_id',
 *     'columns'      => [ storage class => column name ],   // see STORAGE
 *     'options'      => callable(PDO, int $defId): string[],// dropdown values
 *     'unknown_hint' => string,                             // appended to the
 *                                                           // unknown-key error
 *   ]
 *
 * @see docs/design/flexible-asset-fields.md — the design and the reasoning.
 */

require_once __DIR__ . '/service_context.php';

final class TypedFields
{
    /**
     * The field types.
     *
     * 🔑 A presentational variant is a MODE inside `config`; a different storage
     * class is a TYPE. A field's type cannot be changed once values exist, so
     * anything someone might plausibly want to flip later must be a mode —
     * text/textarea is `text` + config.multiline, date/time/datetime is `date` +
     * config.date_mode. FormsService::DATE_MODES learned this first and wrote
     * down why: picking the wrong one otherwise means deleting the field and
     * stranding every value already given to it.
     */
    const TYPES = ['text', 'number', 'date', 'boolean', 'dropdown', 'url', 'email', 'ref'];

    /**
     * Type -> storage class. The schema's `columns` map turns a storage class
     * into an actual column, so two modules may name their columns differently
     * (the CMDB has `value_object_id` where assets will have `value_ref_id`)
     * without the engine caring.
     */
    const STORAGE = [
        'text'     => 'text',
        'dropdown' => 'text',
        'url'      => 'text',
        'email'    => 'text',
        'number'   => 'number',
        'date'     => 'date',
        'boolean'  => 'boolean',
        'ref'      => 'ref',
    ];

    /** @var array<string, array> kind => handler. See registerRefKind(). */
    private static array $refKinds = [];

    // ---------------------------------------------------------------- types --

    public static function storageClass(string $type): string
    {
        return self::STORAGE[$type] ?? '';
    }

    public static function isKnownType(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    // ----------------------------------------------------- reference kinds --

    /**
     * Register a kind of thing a `ref` field may point at.
     *
     * Same shape as the entity registry in includes/documents.php, and for the
     * same reason: adding "a headset can point at the person who has it" should
     * be a registry entry, not surgery on a switch statement.
     *
     * A handler provides:
     *   'label'    => string                       — for error messages
     *   'exists'   => callable(PDO, int): ?int     — returns the row's "target"
     *                                                discriminator (e.g. class
     *                                                id), or null if no such row
     *   'target_label' => string                   — what the discriminator is
     *                                                called, for error messages
     *
     * ⚠️ A handler answers "is this a real row of the right kind?" and nothing
     * more. It must never answer "may this actor see it" — that is the calling
     * service's job, every time.
     */
    public static function registerRefKind(string $kind, array $handler): void
    {
        self::$refKinds[$kind] = $handler;
    }

    public static function refKind(string $kind): ?array
    {
        return self::$refKinds[$kind] ?? null;
    }

    // ------------------------------------------------------------ required --

    /**
     * Required-field enforcement, with the create/update asymmetry the CMDB
     * settled on: required is checked on create, and on update only for fields
     * actually being written.
     *
     * 🔑 Without the asymmetry, ticking "required" on an existing field makes
     * every older record unsaveable — including by someone editing an unrelated
     * field. Pre-existing violations are surfaced by an audit view instead.
     *
     * @param array $defs Canonical definitions, keyed by field key.
     */
    public static function checkRequired(array $defs, array $values, bool $isCreate): void
    {
        foreach ($defs as $key => $def) {
            if (empty($def['required'])) {
                continue;
            }
            if (array_key_exists($key, $values)) {
                $v = $values[$key];
                if ($v === null || $v === '' || (is_array($v) && empty($v))) {
                    throw new ServiceError('validation', 'missing_field', "Required property missing: {$def['label']}");
                }
            } elseif ($isCreate) {
                throw new ServiceError('validation', 'missing_field', "Required property missing: {$def['label']}");
            }
        }
    }

    // --------------------------------------------------------------- write --

    /**
     * Validate and store values for one owner.
     *
     * Delete-then-insert per field, so an absent or empty value CLEARS it:
     * 🔑 no row means "not set", which is what lets ten televisions carry three
     * smart-TV fields on three of them and nothing at all on the other seven.
     *
     * @param array $defs   Canonical definitions, keyed by field key.
     * @param array $values [field key => raw value]. Unknown keys throw.
     */
    public static function writeValues(PDO $conn, array $schema, int $ownerId, array $defs, array $values): void
    {
        $cols     = $schema['columns'];
        $valTable = $schema['value_table'];
        $ownerCol = $schema['owner_column'];
        $defCol   = $schema['def_column'];

        $ordered  = array_values($cols);              // stable column order
        $colList  = implode(', ', $ordered);
        $placeholders = implode(', ', array_fill(0, count($ordered), '?'));

        $del = $conn->prepare("DELETE FROM {$valTable} WHERE {$ownerCol} = ? AND {$defCol} = ?");
        $ins = $conn->prepare(
            "INSERT INTO {$valTable} ({$ownerCol}, {$defCol}, {$colList}) VALUES (?, ?, {$placeholders})"
        );

        foreach ($values as $key => $rawValue) {
            if (!isset($defs[$key])) {
                $hint = $schema['unknown_hint'] ?? '';
                throw new ServiceError('validation', 'invalid_field',
                    trim("Unknown property '{$key}' for this class. {$hint}"));
            }
            $def = $defs[$key];
            $defId = (int)$def['id'];

            $del->execute([$ownerId, $defId]);

            if ($rawValue === null || $rawValue === '') {
                continue;   // cleared
            }

            $stored = self::coerce($conn, $schema, $def, $rawValue, $ownerId);
            if ($stored === null) {
                continue;   // the coercer decided this clears too (see 'ref')
            }

            $row = [$ownerId, $defId];
            foreach (array_keys($cols) as $storageClass) {
                $row[] = $stored[$storageClass] ?? null;
            }
            $ins->execute($row);
        }
    }

    /**
     * Turn one raw input into [storage class => value], or null to clear.
     *
     * Every message here is user-facing and deliberately unchanged from the
     * CMDB original — they are what the REST API has always returned.
     */
    public static function coerce(PDO $conn, array $schema, array $def, $rawValue, int $ownerId): ?array
    {
        $out = ['text' => null, 'number' => null, 'date' => null, 'boolean' => null, 'ref' => null];

        switch ($def['type']) {
            case 'text':
                $out['text'] = (string)$rawValue;
                return $out;

            case 'url':
            case 'email':
                // Stored as text; format validation belongs with the field's
                // config and arrives with the asset catalogue in slice 2.
                $out['text'] = (string)$rawValue;
                return $out;

            case 'dropdown':
                $out['text'] = (string)$rawValue;
                $allowed = isset($schema['options'])
                    ? ($schema['options'])($conn, (int)$def['id'])
                    : [];
                if ($allowed && !in_array($out['text'], $allowed, true)) {
                    throw new ServiceError('validation', 'invalid_field',
                        "Property '{$def['label']}' must be one of: " . implode(', ', $allowed));
                }
                return $out;

            case 'number':
                if (!is_numeric($rawValue)) {
                    throw new ServiceError('validation', 'invalid_field',
                        "Property '{$def['label']}' expects a number.");
                }
                $out['number'] = (float)$rawValue;
                return $out;

            case 'date':
                $out['date'] = self::parseDate((string)$rawValue, $def['key']);
                return $out;

            case 'boolean':
                $out['boolean'] = ($rawValue === true || $rawValue === 1 || $rawValue === '1' || $rawValue === 'true') ? 1 : 0;
                return $out;

            case 'ref':
                $refId = (int)$rawValue;
                if ($refId <= 0) {
                    return null;    // treated as cleared, as object_ref always was
                }
                if ($refId === $ownerId && ($def['ref_kind'] ?? '') === ($schema['self_kind'] ?? null)) {
                    throw new ServiceError('validation', 'invalid_field',
                        "Property '{$def['label']}' can't reference its own object.");
                }
                self::validateRef($conn, $def, $refId);
                $out['ref'] = $refId;
                return $out;

            default:
                throw new ServiceError('validation', 'invalid_field',
                    "Unknown property type: {$def['type']}");
        }
    }

    /** Existence + target check for a `ref` value, via the registry. */
    private static function validateRef(PDO $conn, array $def, int $refId): void
    {
        $kind    = $def['ref_kind'] ?? '';
        $handler = self::refKind($kind);
        if ($handler === null) {
            throw new ServiceError('validation', 'invalid_field',
                "Property '{$def['label']}' has an unknown reference kind: {$kind}");
        }

        $actualTarget = ($handler['exists'])($conn, $refId);
        if ($actualTarget === false) {
            throw new ServiceError('validation', 'invalid_field',
                "Property '{$def['label']}' references an object that doesn't exist.");
        }

        $wanted = $def['ref_target'] ?? null;
        if ($wanted !== null && (int)$actualTarget !== (int)$wanted) {
            throw new ServiceError('validation', 'invalid_field',
                "Property '{$def['label']}' can only reference objects of its target class.");
        }
    }

    // ---------------------------------------------------------------- dates --

    /**
     * Parse a date/time to 'Y-m-d H:i:s' UTC (throwing twin of apiParseDate;
     * 400 on bad input).
     */
    public static function parseDate(string $value, string $field): string
    {
        $v = trim($value);
        try {
            $dt = new DateTimeImmutable($v, new DateTimeZone('UTC'));
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw new ServiceError('bad_request', 'invalid_parameter',
                "'{$field}' is not a valid date/time. Use ISO 8601, e.g. 2026-07-02T09:00:00Z.");
        }
    }
}

// ---------------------------------------------------------------------------
// Reference kinds
//
// Registered here rather than by each module, so that "what can a field point
// at?" is answerable by reading one file — the same choice includes/documents.php
// made for its 13 entity types.
// ---------------------------------------------------------------------------

/**
 * A CMDB configuration item. The `target` discriminator is its class id, which
 * is what cmdb_class_properties.target_class_id narrows against.
 *
 * ⚠️ Existence only. Whether the actor may SEE this CI, and whether it is in the
 * same company as the item being written, is decided by
 * CmdbService::assertObjectRefsInCompany() — deliberately not here.
 */
TypedFields::registerRefKind('cmdb_object', [
    'label'        => 'configuration item',
    'target_label' => 'class',
    'exists'       => function (PDO $conn, int $id) {
        $stmt = $conn->prepare("SELECT class_id FROM cmdb_objects WHERE id = ?");
        $stmt->execute([$id]);
        $classId = $stmt->fetchColumn();
        return ($classId === false) ? false : (int)$classId;
    },
]);
