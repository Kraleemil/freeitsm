<?php
/**
 * THE search function. Nothing else in FreeITSM writes a search query.
 *
 * That is the whole point of this file. If `MATCH ... AGAINST` appears in six
 * endpoints then changing how search works — or changing what answers it — is a
 * rewrite. Behind one function it is a second implementation of one function.
 * See the Full-Text-Search wiki page for the reasoning.
 *
 * TWO RULES CALLERS MUST NOT BREAK
 *
 *  1. THE PERMISSION PREDICATE GOES INTO THE QUERY, NEVER OVER THE RESULTS.
 *     Filtering afterwards starves results: the index returns its top N by
 *     relevance, you discard what the caller may not see, and hand back three
 *     rows while hundreds they were entitled to never made the top N. It fails
 *     worst for the least privileged user, who is also the least likely to be
 *     the one testing it.
 *
 *  2. THE SCOPE IS A DATA STRUCTURE, NOT SQL. A SQL fragment would weld this to
 *     MySQL. FreeITSM computes the predicate; the backend merely applies it.
 *     Replicating POLICY into a second system is dangerous; passing a COMPUTED
 *     FILTER to a dumb store is ordinary.
 *
 * WHAT IT WILL AND WILL NOT MATCH. Word order is irrelevant and phrases work,
 * but MySQL has no stemmer and no fuzzy matching: "printers" will not match
 * "printer" and a typo finds nothing. searchParseQuery() adds a trailing
 * wildcard to soften the first of those. See §4.6 of the wiki page.
 */

require_once __DIR__ . '/corpus.php';

/**
 * The shortest word this server will index. Read from the server rather than
 * assumed, because it is not the same everywhere — WAMP ships 0, stock MySQL is
 * 3 — and requiring a term below it in boolean mode returns NOTHING AT ALL
 * rather than ignoring it. That is the difference between "search works" and
 * "search mysteriously returns nothing for that phrase".
 */
function searchMinTokenSize(PDO $conn): int {
    static $n = null;
    if ($n !== null) return $n;
    try {
        $n = (int)$conn->query("SELECT @@innodb_ft_min_token_size")->fetchColumn();
    } catch (Throwable $e) {
        $n = 3;   // stock MySQL
    }
    return $n;
}

/**
 * Turn what a person typed into a MySQL boolean-mode query.
 *
 * The user-facing language is deliberately tiny — words, "quoted phrases" and
 * -exclusions — so that it can be translated for a different engine later
 * without the syntax having leaked into the UI.
 *
 * ⚠️ Terms too short to be indexed are DROPPED, not passed through. Requiring
 * one would make the whole query match nothing. They come back in `dropped` so
 * the caller can say so rather than silently returning an empty page.
 *
 * @return array{expr:string,terms:array,phrases:array,excluded:array,dropped:array}
 */
function searchParseQuery(string $raw, int $minToken = 3): array {
    $raw = trim($raw);
    $phrases = $terms = $excluded = $dropped = [];

    // Quoted phrases first, so their spaces survive the word split.
    if (preg_match_all('~"([^"]+)"~u', $raw, $m)) {
        foreach ($m[1] as $p) {
            $p = trim(preg_replace('~[+\-><()\~*@"]+~u', ' ', $p));
            if ($p !== '') $phrases[] = $p;
        }
        $raw = preg_replace('~"[^"]*"~u', ' ', $raw);
    }

    foreach (preg_split('~\s+~u', (string)$raw, -1, PREG_SPLIT_NO_EMPTY) as $word) {
        $negate = ($word[0] ?? '') === '-';
        // Strip every boolean operator: they are ours to add, not the user's to inject.
        $clean = preg_replace('~[+\-><()\~*@"]+~u', '', $word);
        if ($clean === '' || $clean === null) continue;
        if (mb_strlen($clean, 'UTF-8') < max(1, $minToken)) { $dropped[] = $clean; continue; }
        if ($negate) $excluded[] = $clean; else $terms[] = $clean;
    }

    $parts = [];
    foreach ($phrases  as $p) $parts[] = '+"' . $p . '"';
    // Trailing wildcard is the documented mitigation for MySQL having no stemmer:
    // it lets "printer" find "printers". It over-matches on short stems, which is
    // the accepted trade.
    foreach ($terms    as $t) $parts[] = '+' . $t . '*';
    foreach ($excluded as $e) $parts[] = '-' . $e;

    return [
        'expr'     => implode(' ', $parts),
        'terms'    => $terms,
        'phrases'  => $phrases,
        'excluded' => $excluded,
        'dropped'  => $dropped,
    ];
}

/**
 * Build the scope structure for an analyst.
 *
 * Company scoping mirrors ticketTenantFilter() exactly — including that a NULL
 * tenant means "the default company" and is only visible when the analyst's
 * active company IS the default. Ticket search in FreeITSM is not restricted by
 * department or team (see api/tickets/search_tickets.php), so neither is this.
 */
function searchScopeForAnalyst(PDO $conn, int $analystId, array $overrides = []): array {
    $scope = [
        'tenant_id'         => null,   // null = no company filtering at all
        'include_default'   => true,   // may this caller see rows whose source had a NULL tenant?
        'include_internal'  => true,   // internal notes — false for anything customer-facing
        'include_deleted'   => false,  // trashed tickets stay out
        'source_types'      => null,   // null = every kind
        'ticket_ids'        => null,   // null = no restriction
    ];

    if (function_exists('isMultiTenant') && isMultiTenant($conn)) {
        $active  = getActiveTenantId($conn, $analystId);
        $default = getDefaultTenantId($conn);
        $scope['tenant_id']       = $active;
        $scope['include_default'] = ($active === $default);
    }
    return array_merge($scope, $overrides);
}

/**
 * Translate the scope structure into SQL. The ONLY place this happens.
 *
 * @return array{0:string,1:array} [sql fragment, params]
 */
function searchScopeToSql(array $scope, string $alias = 'sd'): array {
    $sql = '';
    $params = [];

    if (!empty($scope['tenant_id'])) {
        // 'shared' is always visible; 'default' only when this caller is in the
        // default company; otherwise the company must match exactly.
        $clause = "($alias.tenant_scope = 'shared' OR $alias.tenant_id = ?";
        $params[] = (int)$scope['tenant_id'];
        if (!empty($scope['include_default'])) $clause .= " OR $alias.tenant_scope = 'default'";
        $sql .= " AND " . $clause . ")";
    }

    if (empty($scope['include_internal'])) {
        $sql .= " AND $alias.is_internal = 0";
    }

    if (!empty($scope['source_types']) && is_array($scope['source_types'])) {
        $sql .= " AND $alias.source_type IN (" . implode(',', array_fill(0, count($scope['source_types']), '?')) . ")";
        foreach ($scope['source_types'] as $t) $params[] = (string)$t;
    }

    if (!empty($scope['ticket_ids']) && is_array($scope['ticket_ids'])) {
        $sql .= " AND $alias.ticket_id IN (" . implode(',', array_fill(0, count($scope['ticket_ids']), '?')) . ")";
        foreach ($scope['ticket_ids'] as $t) $params[] = (int)$t;
    }

    return [$sql, $params];
}

/**
 * Search the corpus.
 *
 * Two bounded queries rather than one clever one: rank the TICKETS, then fetch
 * the matching documents for just that page of tickets. Doing it the other way
 * round — fetch documents, collapse afterwards — reintroduces a top-N
 * distortion, because the top 200 documents may collapse to a handful of
 * tickets.
 *
 * @param array $opts limit, offset, title_only, snippet_chars
 * @return array{total:int,results:array,query:array,ok:bool,reason:?string}
 */
function searchCorpusQuery(PDO $conn, string $rawQuery, array $scope, array $opts = []): array {
    $limit   = max(1, min(100, (int)($opts['limit']  ?? 20)));
    $offset  = max(0, (int)($opts['offset'] ?? 0));
    $snipLen = max(60, min(600, (int)($opts['snippet_chars'] ?? 220)));
    $empty   = ['total' => 0, 'results' => [], 'query' => [], 'ok' => false, 'reason' => null];

    if (!searchCorpusReady($conn)) {
        return array_merge($empty, ['reason' => 'not_ready']);   // Database Verification not run
    }

    $parsed = searchParseQuery($rawQuery, searchMinTokenSize($conn));
    $empty['query'] = $parsed;
    if ($parsed['expr'] === '' || ($parsed['terms'] === [] && $parsed['phrases'] === [])) {
        // Nothing searchable survived. Saying so beats returning "no results",
        // which would read as "that isn't in your tickets".
        return array_merge($empty, ['reason' => 'no_usable_terms']);
    }

    // MATCH must name exactly the columns of a FULLTEXT index — hence two indexes.
    $cols = !empty($opts['title_only']) ? 'sd.title' : 'sd.title, sd.body';
    [$scopeSql, $scopeParams] = searchScopeToSql($scope, 'sd');

    // Deleted tickets stay out. The join is also what keeps ticket-level truth in
    // the tickets table rather than duplicated into the corpus.
    $joinSql = " LEFT JOIN tickets t ON t.id = sd.ticket_id";
    $delSql  = empty($scope['include_deleted']) ? " AND (sd.ticket_id IS NULL OR t.deleted_datetime IS NULL)" : '';

    $where = "MATCH($cols) AGAINST (? IN BOOLEAN MODE)" . $scopeSql . $delSql;
    $args  = array_merge([$parsed['expr']], $scopeParams);

    // --- 1. how many tickets match, and which page of them ------------------
    $countSql = "SELECT COUNT(DISTINCT COALESCE(sd.ticket_id, -sd.id))
                   FROM search_documents sd $joinSql WHERE $where";
    $st = $conn->prepare($countSql);
    $st->execute($args);
    $total = (int)$st->fetchColumn();
    if ($total === 0) return array_merge($empty, ['ok' => true, 'query' => $parsed]);

    $rankSql = "SELECT COALESCE(sd.ticket_id, -sd.id) AS grp,
                       MAX(MATCH($cols) AGAINST (? IN BOOLEAN MODE)) AS score
                  FROM search_documents sd $joinSql
                 WHERE $where
                 GROUP BY grp
                 ORDER BY score DESC, grp DESC
                 LIMIT $limit OFFSET $offset";
    $st = $conn->prepare($rankSql);
    $st->execute(array_merge([$parsed['expr']], $args));
    $groups = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$groups) return array_merge($empty, ['ok' => true, 'query' => $parsed]);

    // --- 2. the matching documents for just those tickets --------------------
    $grpIds = array_map(fn($g) => (int)$g['grp'], $groups);
    $in     = implode(',', array_fill(0, count($grpIds), '?'));
    $hitSql = "SELECT sd.id, sd.source_type, sd.source_id, sd.ticket_id, sd.title, sd.body,
                      sd.is_internal, sd.source_datetime,
                      COALESCE(sd.ticket_id, -sd.id) AS grp,
                      MATCH($cols) AGAINST (? IN BOOLEAN MODE) AS score
                 FROM search_documents sd $joinSql
                WHERE $where AND COALESCE(sd.ticket_id, -sd.id) IN ($in)
                ORDER BY score DESC";
    $st = $conn->prepare($hitSql);
    $st->execute(array_merge([$parsed['expr']], $args, $grpIds));

    $byGroup = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $g = (int)$r['grp'];
        $byGroup[$g][] = [
            'source_type'     => $r['source_type'],
            'source_id'       => (int)$r['source_id'],
            'title'           => (string)$r['title'],
            'snippet'         => searchSnippet((string)$r['body'], $parsed, $snipLen),
            'is_internal'     => (bool)$r['is_internal'],
            'source_datetime' => $r['source_datetime'],
            'score'           => (float)$r['score'],
        ];
    }

    $results = [];
    foreach ($groups as $g) {
        $id   = (int)$g['grp'];
        $hits = $byGroup[$id] ?? [];
        $results[] = [
            'ticket_id' => $id > 0 ? $id : null,
            'score'     => (float)$g['score'],
            'matched'   => array_values(array_unique(array_column($hits, 'source_type'))),
            'hits'      => $hits,
        ];
    }

    return ['total' => $total, 'results' => $results, 'query' => $parsed, 'ok' => true, 'reason' => null];
}

/**
 * A readable fragment of the body around the first matching term.
 *
 * MySQL has no highlighting function, so this is done here. Returns PLAIN TEXT —
 * the caller escapes it. Never build HTML in this file; a snippet is user
 * content and the one place it must not be trusted is on its way to a page.
 */
function searchSnippet(string $body, array $parsed, int $len = 220): string {
    $body = trim(preg_replace('~\s+~u', ' ', $body));
    if ($body === '') return '';
    if (mb_strlen($body, 'UTF-8') <= $len) return $body;

    $needles = array_merge($parsed['phrases'] ?? [], $parsed['terms'] ?? []);
    $at = 0;
    foreach ($needles as $n) {
        $pos = mb_stripos($body, $n, 0, 'UTF-8');
        if ($pos !== false) { $at = max(0, $pos - (int)($len / 4)); break; }
    }
    $out = mb_substr($body, $at, $len, 'UTF-8');
    if ($at > 0) $out = '…' . ltrim($out);
    if ($at + $len < mb_strlen($body, 'UTF-8')) $out = rtrim($out) . '…';
    return $out;
}
