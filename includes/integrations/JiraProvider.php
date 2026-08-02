<?php
/**
 * JiraProvider — Jira Cloud and Jira Data Center / Server behind one contract.
 *
 * ── Cloud vs Data Center ────────────────────────────────────────────────────
 *
 * They are the same product with two genuinely different APIs, and the
 * differences are not cosmetic:
 *
 *                        Cloud                     Data Center / Server
 *   REST base            /rest/api/3               /rest/api/2
 *   Description format   ADF (nested JSON)         wiki markup (a string)
 *   Auth                 email + API token (Basic) Personal Access Token (Bearer)
 *   User identity        accountId                 username ("name")
 *   Project list         /project/search (paged)   /project (a plain array)
 *
 * Rather than two providers with 80% duplication, this is ONE provider with a
 * `flavour`. It is decided once at testConnection() and cached in the
 * connection's credentials blob, falling back to a base-URL heuristic
 * (*.atlassian.net is always Cloud; Data Center never is) so a connection saved
 * before the sniff still behaves.
 *
 * ── Testability ─────────────────────────────────────────────────────────────
 *
 * Payload building and response parsing are separate, protected methods rather
 * than being inlined into the HTTP calls. That is deliberate: it means the
 * interesting logic — the ADF-vs-wiki choice, the status mapping, the error
 * extraction — is provable without a live Jira site. tests/integrations/run.php
 * subclasses this and stubs httpRequest().
 *
 * ⚠️ Status categories: Jira's own statusCategory is the ONLY stable thing here.
 * Status *names* are per-project, per-workflow and renamed at will, so nothing
 * branches on them. See the note on tickets.merged_into_id for the last time
 * keying on a status name cost us.
 */

require_once __DIR__ . '/IssueTrackerProvider.php';
require_once __DIR__ . '/IssueDoc.php';

class JiraProvider extends IssueTrackerProvider
{
    const FLAVOUR_CLOUD  = 'cloud';
    const FLAVOUR_SERVER = 'server';

    protected function capabilities(): array
    {
        return [
            self::CAP_ISSUE_TYPES,
            self::CAP_PRIORITIES,
            self::CAP_ATTACHMENTS,
            self::CAP_WEBHOOKS,
            self::CAP_POLLING,
            self::CAP_CUSTOM_FIELDS,
        ];
    }

    // ------------------------------------------------------------- flavour

    /** 'cloud' | 'server'. Sniffed at testConnection, cached in credentials. */
    public function flavour(): string
    {
        $stored = $this->connection['credentials']['flavour'] ?? '';
        if ($stored === self::FLAVOUR_CLOUD || $stored === self::FLAVOUR_SERVER) {
            return $stored;
        }
        // Heuristic fallback. Jira Cloud is always on atlassian.net; Data Center
        // never is. Only used until the first successful connection test.
        $host = strtolower((string) parse_url($this->baseUrl(), PHP_URL_HOST));
        return (substr($host, -14) === '.atlassian.net' || $host === 'atlassian.net')
            ? self::FLAVOUR_CLOUD
            : self::FLAVOUR_SERVER;
    }

    protected function isCloud(): bool
    {
        return $this->flavour() === self::FLAVOUR_CLOUD;
    }

    /** REST API version this flavour speaks. */
    protected function apiVersion(): int
    {
        return $this->isCloud() ? 3 : 2;
    }

    protected function baseUrl(): string
    {
        return rtrim((string)($this->connection['base_url'] ?? ''), '/');
    }

    protected function apiUrl(string $path): string
    {
        return $this->baseUrl() . '/rest/api/' . $this->apiVersion() . '/' . ltrim($path, '/');
    }

    /** The human-facing link we store on the link row and show in the panel. */
    public function browseUrl(string $key): string
    {
        return $this->baseUrl() . '/browse/' . rawurlencode($key);
    }

    // ------------------------------------------------------------ requests

    /**
     * Auth differs by flavour: Cloud uses HTTP Basic with the user's email and
     * an API token; Data Center uses a Bearer Personal Access Token.
     */
    protected function jiraRequest(string $method, string $url, ?array $json = null): array
    {
        $creds   = $this->connection['credentials'] ?? [];
        $headers = ['Accept: application/json'];
        $opts    = ['method' => $method, 'headers' => &$headers];

        if ($this->isCloud()) {
            $opts['auth'] = ($creds['email'] ?? '') . ':' . ($creds['api_token'] ?? '');
        } else {
            $headers[] = 'Authorization: Bearer ' . ($creds['api_token'] ?? '');
        }
        if ($json !== null) {
            $headers[]     = 'Content-Type: application/json';
            $opts['body']  = json_encode($json);
        }
        $opts['headers'] = $headers;

        list($code, $body) = $this->httpRequest($url, $opts);
        $decoded = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            throw new Exception($this->extractError($code, $body));
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Turn a Jira error response into something an analyst can act on.
     *
     * Jira reports field-level problems in `errors` (e.g. {"customfield_10010":
     * "Epic Link is required"}) and general ones in `errorMessages`. Surfacing
     * only "HTTP 400" would make the most common failure — a project whose screen
     * scheme demands a field we did not send — completely undiagnosable.
     */
    protected function extractError(int $code, string $rawBody): string
    {
        $d     = json_decode($rawBody, true);
        $parts = [];

        if (is_array($d)) {
            foreach ((array)($d['errorMessages'] ?? []) as $m) {
                if (is_string($m) && $m !== '') $parts[] = $m;
            }
            foreach ((array)($d['errors'] ?? []) as $field => $m) {
                if (is_string($m) && $m !== '') $parts[] = "$field: $m";
            }
        }
        if (!$parts) {
            if ($code === 401 || $code === 403) {
                $parts[] = 'Jira rejected the credentials (HTTP ' . $code . ').';
            } elseif ($code === 404) {
                $parts[] = 'Jira returned 404 — check the site URL and that the issue or project exists.';
            } else {
                $snippet = trim(strip_tags($rawBody));
                $parts[] = 'Jira returned HTTP ' . $code
                         . ($snippet !== '' ? ': ' . mb_substr($snippet, 0, 200) : '.');
            }
        }
        return implode(' ', $parts);
    }

    // ----------------------------------------------------------- rendering

    /** ADF (an array) on Cloud; wiki markup (a string) on Data Center. */
    public function renderDoc(IssueDoc $doc)
    {
        return $this->isCloud() ? $doc->toAdf() : $doc->toWikiMarkup();
    }

    // -------------------------------------------------------------- create

    /**
     * @param array $target ['project' => 'OPS', 'issue_type' => 'Bug']
     */
    protected function buildCreatePayload(array $target, string $summary, IssueDoc $body, array $fields = []): array
    {
        $project = trim((string)($target['project'] ?? ''));
        $type    = trim((string)($target['issue_type'] ?? ''));
        if ($project === '') throw new Exception('No Jira project was chosen.');
        if ($type === '')    throw new Exception('No Jira issue type was chosen.');

        // Summary is a single line in Jira; a pasted subject with a newline in it
        // is rejected with an unhelpful error, so normalise rather than fail.
        $summary = trim(preg_replace('/\s+/u', ' ', $summary));
        if ($summary === '') $summary = '(no subject)';
        if (mb_strlen($summary) > 255) $summary = mb_substr($summary, 0, 252) . '…';

        $payload = ['fields' => array_merge([
            'project'     => ['key' => $project],
            'issuetype'   => ['name' => $type],
            'summary'     => $summary,
            'description' => $this->renderDoc($body),
        ], $fields)];

        // NOTE: priority is deliberately NOT set from the ticket's priority.
        // Jira priorities are per-project with arbitrary names, so sending one
        // 400s on any project that renamed them. Priority travels as text in the
        // description instead. Mapped priorities arrive in V3 via $fields, where
        // an admin has explicitly said which name means which.

        return $payload;
    }

    public function createIssue(array $target, string $summary, IssueDoc $body, array $fields = []): array
    {
        $created = $this->jiraRequest('POST', $this->apiUrl('issue'), $this->buildCreatePayload($target, $summary, $body, $fields));

        $id  = (string)($created['id'] ?? '');
        $key = (string)($created['key'] ?? '');
        if ($id === '') {
            throw new Exception('Jira accepted the issue but returned no id.');
        }

        // Create does not return status, and the panel would otherwise show a
        // blank until the first poll. One extra read is worth a correct panel —
        // but if it fails the issue still EXISTS, so never let this throw away a
        // successful creation.
        try {
            return $this->fetchIssue($id);
        } catch (Exception $e) {
            return [
                'external_id'     => $id,
                'external_key'    => $key,
                'external_url'    => $this->browseUrl($key),
                'status_name'     => null,
                'status_category' => null,
                'assignee_name'   => null,
            ];
        }
    }

    // --------------------------------------------------------------- read

    /** Jira's issue JSON → our normalised shape. */
    protected function parseIssue(array $issue): array
    {
        $key    = (string)($issue['key'] ?? '');
        $fields = (array)($issue['fields'] ?? []);
        $status = (array)($fields['status'] ?? []);

        return [
            'external_id'     => (string)($issue['id'] ?? ''),
            'external_key'    => $key,
            'external_url'    => $key !== '' ? $this->browseUrl($key) : null,
            'status_name'     => isset($status['name']) ? (string)$status['name'] : null,
            'status_category' => $this->mapStatusCategory((string)($status['statusCategory']['key'] ?? '')),
            'assignee_name'   => isset($fields['assignee']['displayName'])
                                    ? (string)$fields['assignee']['displayName'] : null,
            'summary'         => isset($fields['summary']) ? (string)$fields['summary'] : null,
        ];
    }

    /**
     * Jira's statusCategory key → our closed set.
     *
     * These three keys are fixed by Jira and are the same on every project on
     * every site, which is exactly why we key off them and not the status name.
     * Jira has no "cancelled" category — a Won't Do lands in `done` — so
     * STATUS_CANCELLED is simply unreachable here, and that is correct rather
     * than a gap.
     */
    protected function mapStatusCategory(string $categoryKey): ?string
    {
        switch ($categoryKey) {
            case 'new':           return self::STATUS_TODO;
            case 'indeterminate': return self::STATUS_IN_PROGRESS;
            case 'done':          return self::STATUS_DONE;
            default:              return null;   // unknown → leave it unset rather than guess
        }
    }

    public function fetchIssue(string $externalId): array
    {
        $url = $this->apiUrl('issue/' . rawurlencode($externalId))
             . '?fields=summary,status,assignee';
        return $this->parseIssue($this->jiraRequest('GET', $url));
    }

    /**
     * Batch read via JQL — one call for the whole watch list instead of one per
     * issue, which matters because this is the poll cron's hot path.
     *
     * Chunked at 100: JQL has a practical length limit, and a site with hundreds
     * of linked issues would otherwise build a URL nothing will accept.
     */
    public function fetchIssues(array $externalIds): array
    {
        $ids = array_values(array_filter(array_map('strval', $externalIds), function ($i) {
            return ctype_digit($i);   // Jira ids are numeric; anything else cannot be interpolated safely
        }));
        if (!$ids) return [];

        $out = [];
        $chunks = array_chunk($ids, 100);
        $failures = 0;
        $lastError = null;

        foreach ($chunks as $chunk) {
            $jql = 'id in (' . implode(',', $chunk) . ')';
            // ⚠️ Cloud REMOVED /rest/api/3/search — it is /search/jql now, with
            // token paging instead of startAt (Atlassian CHANGE-2046). Data
            // Center's v2 /search is unaffected, so the endpoint is flavour-aware
            // like everything else here. Found the hard way: the old path
            // returned "The requested API has been removed" against live Jira.
            $endpoint = $this->isCloud() ? 'search/jql' : 'search';
            $url = $this->apiUrl($endpoint) . '?' . http_build_query([
                'jql'        => $jql,
                'fields'     => 'summary,status,assignee',
                'maxResults' => count($chunk),
            ]);
            try {
                $res = $this->jiraRequest('GET', $url);
            } catch (Exception $e) {
                // One bad chunk must not lose the others — but see below.
                $failures++;
                $lastError = $e;
                continue;
            }
            foreach ((array)($res['issues'] ?? []) as $issue) {
                $parsed = $this->parseIssue((array)$issue);
                if ($parsed['external_id'] !== '') {
                    $out[$parsed['external_id']] = $parsed;
                }
            }
        }

        // ⚠️ If EVERY chunk failed this is not "one flaky page", it is a broken
        // connection — and returning [] would be indistinguishable from "none of
        // those issues exist". The poll would then cheerfully report "checked 12,
        // changed 0" while the tracker had been unreachable for a week. Surface it.
        if ($failures > 0 && $failures === count($chunks)) {
            throw ($lastError ?: new Exception('Could not read any issues from Jira.'));
        }
        return $out;
    }

    // ------------------------------------------------------------ comments

    public function addComment(string $externalId, IssueDoc $body): string
    {
        $res = $this->jiraRequest(
            'POST',
            $this->apiUrl('issue/' . rawurlencode($externalId) . '/comment'),
            ['body' => $this->renderDoc($body)]
        );
        return (string)($res['id'] ?? '');
    }

    // ------------------------------------------------------------- discovery

    /**
     * Confirm the credentials and — just as importantly — capture who we are.
     *
     * account_identity is half of echo suppression: an inbound event authored by
     * this identity is our own write coming back and must be dropped, not
     * re-imported. Cloud identifies users by accountId, Data Center by username,
     * and those are the values that appear as the author in each flavour's
     * webhook payloads.
     */
    public function testConnection(): array
    {
        // Sniff the flavour: v3 /myself exists only on Cloud. Try the heuristic's
        // choice first, then the other, so a vanity domain in front of Cloud (or a
        // wrong guess either way) still resolves rather than reporting bad creds.
        $order = $this->isCloud()
            ? [self::FLAVOUR_CLOUD, self::FLAVOUR_SERVER]
            : [self::FLAVOUR_SERVER, self::FLAVOUR_CLOUD];

        $lastError = null;
        foreach ($order as $flavour) {
            $this->connection['credentials']['flavour'] = $flavour;
            try {
                $me = $this->jiraRequest('GET', $this->apiUrl('myself'));
            } catch (Exception $e) {
                $lastError = $e;
                continue;
            }

            $identity = $flavour === self::FLAVOUR_CLOUD
                ? (string)($me['accountId'] ?? '')
                : (string)($me['name'] ?? $me['key'] ?? '');
            $display  = (string)($me['displayName'] ?? $me['emailAddress'] ?? $identity);

            if ($identity === '') {
                $lastError = new Exception('Jira did not identify the account this token belongs to.');
                continue;
            }

            return [
                'detail'           => 'Connected to Jira ' . ($flavour === self::FLAVOUR_CLOUD ? 'Cloud' : 'Data Center')
                                    . ' as ' . $display,
                'account_identity' => $identity,
                'flavour'          => $flavour,
            ];
        }
        throw ($lastError ?: new Exception('Could not reach Jira.'));
    }

    /** @return array [['key'=>'OPS','name'=>'Operations'], …] */
    public function listProjects(): array
    {
        $out = [];
        if ($this->isCloud()) {
            // Paged. Walk it rather than taking the first 50 — an MSP's Jira can
            // easily have more, and a silently truncated list looks like a bug
            // ("my project isn't in the dropdown").
            $start = 0;
            do {
                $res = $this->jiraRequest('GET', $this->apiUrl('project/search')
                    . '?' . http_build_query(['startAt' => $start, 'maxResults' => 50]));
                foreach ((array)($res['values'] ?? []) as $p) {
                    $out[] = ['key' => (string)($p['key'] ?? ''), 'name' => (string)($p['name'] ?? '')];
                }
                $start += 50;
                $isLast = !empty($res['isLast']) || empty($res['values']);
            } while (!$isLast && $start < 1000);
        } else {
            foreach ((array)$this->jiraRequest('GET', $this->apiUrl('project')) as $p) {
                $out[] = ['key' => (string)($p['key'] ?? ''), 'name' => (string)($p['name'] ?? '')];
            }
        }
        return $out;
    }

    /**
     * Issue types available on a project.
     *
     * ⚠️ Which types a project offers depends on its issue-type scheme, so this
     * cannot be a hardcoded Bug/Task/Story list — that is exactly how you end up
     * 400ing on the one project that renamed everything.
     */
    public function listIssueTypes(string $project): array
    {
        $out = [];
        if ($this->isCloud()) {
            $res = $this->jiraRequest('GET',
                $this->apiUrl('issue/createmeta/' . rawurlencode($project) . '/issuetypes'));
            foreach ((array)($res['values'] ?? $res['issueTypes'] ?? []) as $t) {
                if (!empty($t['subtask'])) continue;   // subtasks need a parent; not a valid escalation target
                $out[] = ['id' => (string)($t['id'] ?? ''), 'name' => (string)($t['name'] ?? '')];
            }
        } else {
            $res = $this->jiraRequest('GET', $this->apiUrl('issue/createmeta')
                . '?' . http_build_query(['projectKeys' => $project, 'expand' => 'projects.issuetypes']));
            foreach ((array)($res['projects'] ?? []) as $p) {
                foreach ((array)($p['issuetypes'] ?? []) as $t) {
                    if (!empty($t['subtask'])) continue;
                    $out[] = ['id' => (string)($t['id'] ?? ''), 'name' => (string)($t['name'] ?? '')];
                }
            }
        }
        return $out;
    }
}
