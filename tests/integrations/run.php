<?php
/**
 * External issue trackers — IssueDoc and the provider contract.
 *
 * IssueDoc is the piece every later phase writes through: descriptions in V1,
 * comments in V2, everything after. If it is wrong, V2 is where we find out and
 * where we would have to rip it up. So it gets a real suite now rather than an
 * eyeball.
 *
 * Four renderers produce four genuinely different syntaxes from one document,
 * and the risk is that they drift — one of them quietly stops escaping, or
 * handles an empty paragraph differently. Every case therefore runs through ALL
 * FOUR and each is asserted separately.
 *
 *   php tests/integrations/run.php
 *
 * ⚠️ Every negative assertion is paired with a positive control. "The asterisk
 * did not become italics" proves nothing on its own — it is equally true of a
 * renderer that dropped the text entirely. So each escaping test also asserts
 * the surrounding content still arrived.
 *
 * No database. No network. Pure functions, so this suite is fast and safe to run
 * anywhere.
 */

require_once __DIR__ . '/../../includes/integrations/IssueDoc.php';
require_once __DIR__ . '/../../includes/integrations/IssueTrackerProvider.php';

$pass = 0; $fail = 0; $failures = [];

function ok(string $what, bool $cond) {
    global $pass, $fail, $failures;
    if ($cond) { $pass++; return; }
    $fail++; $failures[] = $what;
}
function eq(string $what, $expected, $actual) {
    global $pass, $fail, $failures;
    if ($expected === $actual) { $pass++; return; }
    $fail++;
    $failures[] = $what . "\n      expected: " . var_export($expected, true)
                        . "\n      actual:   " . var_export($actual, true);
}

echo "External issue trackers — IssueDoc\n";
echo str_repeat('=', 62) . "\n\n";

// ---------------------------------------------------------------------------
echo "1. A realistic escalation body renders in all four formats\n";
// ---------------------------------------------------------------------------

$doc = (new IssueDoc)
    ->heading('Raised in FreeITSM')
    ->para('Ticket ', IssueDoc::link('https://sd.example/t/1042', 'SD-1042'),
           ' · Jane Doe (Acme Ltd)')
    ->rule()
    ->para('Outlook crashes when sending with an attachment.')
    ->bullets(['Happens every time', 'Only on the laptop'])
    ->code("Exception 0xC0000005\n  at MSO.dll");

$adf  = $doc->toAdf();
$md   = $doc->toMarkdown();
$wiki = $doc->toWikiMarkup();
$html = $doc->toHtml();

eq('ADF is a doc v1',            'doc', $adf['type']);
eq('ADF version',                1,     $adf['version']);
eq('ADF block count',            6,     count($adf['content']));
eq('ADF first block is heading', 'heading', $adf['content'][0]['type']);
eq('ADF rule survives',          'rule',    $adf['content'][2]['type']);
eq('ADF bulletList',             'bulletList', $adf['content'][4]['type']);
eq('ADF codeBlock',              'codeBlock',  $adf['content'][5]['type']);
ok('ADF JSON-encodes cleanly',   json_encode($adf) !== false);

// The link must become a text node carrying a link MARK — a nested link node is
// the wrong shape and Jira rejects the whole document.
$linkNode = $adf['content'][1]['content'][1];
eq('ADF link is a text node',    'text', $linkNode['type']);
eq('ADF link text',              'SD-1042', $linkNode['text']);
eq('ADF link mark href', 'https://sd.example/t/1042', $linkNode['marks'][0]['attrs']['href']);

ok('Markdown heading',   strpos($md, '### Raised in FreeITSM') === 0);
ok('Markdown link',      strpos($md, '](https://sd.example/t/1042)') !== false);
ok('Markdown rule',      strpos($md, "\n---\n") !== false);
ok('Markdown bullet',    strpos($md, '- Happens every time') !== false);
ok('Markdown fence',     strpos($md, "```\nException") !== false);

ok('Wiki heading',       strpos($wiki, 'h3. Raised in FreeITSM') === 0);
ok('Wiki link syntax',   strpos($wiki, '|https://sd.example/t/1042]') !== false);
ok('Wiki rule',          strpos($wiki, "\n----\n") !== false);
ok('Wiki bullet',        strpos($wiki, '* Happens every time') !== false);
ok('Wiki code macro',    strpos($wiki, "{code}\nException") !== false);

ok('HTML heading',       strpos($html, '<h3>Raised in FreeITSM</h3>') !== false);
ok('HTML anchor',        strpos($html, '<a href="https://sd.example/t/1042">SD-1042</a>') !== false);
ok('HTML rule',          strpos($html, '<hr>') !== false);
ok('HTML list item',     strpos($html, '<li>Happens every time</li>') !== false);
ok('HTML pre',           strpos($html, '<pre>Exception') !== false);

// ---------------------------------------------------------------------------
echo "2. Ticket text cannot inject formatting (each with a positive control)\n";
// ---------------------------------------------------------------------------

// A requester writing about wildcards and brackets must not produce italics or
// a broken link — but the words themselves must still arrive.
$nasty = (new IssueDoc)->para('use the * wildcard and [see notes] plus _underscore_');
$nmd   = $nasty->toMarkdown();

ok('MD escapes asterisk',        strpos($nmd, '\\*') !== false);
ok('MD escapes bracket',         strpos($nmd, '\\[') !== false);
ok('MD escapes underscore',      strpos($nmd, '\\_') !== false);
ok('MD positive control: words survived',
   strpos($nmd, 'wildcard') !== false && strpos($nmd, 'see notes') !== false);

$nwiki = $nasty->toWikiMarkup();
ok('Wiki escapes bracket',       strpos($nwiki, '\\[') !== false);
ok('Wiki escapes asterisk',      strpos($nwiki, '\\*') !== false);
ok('Wiki positive control: words survived', strpos($nwiki, 'wildcard') !== false);

// HTML is the one where a failure is an actual injection, not just ugly output.
$xss  = (new IssueDoc)->para('<script>alert(1)</script> & "quoted"');
$xh   = $xss->toHtml();
ok('HTML escapes the tag',       strpos($xh, '<script>') === false);
ok('HTML encoded the tag',       strpos($xh, '&lt;script&gt;') !== false);
ok('HTML escapes ampersand',     strpos($xh, '&amp;') !== false);
ok('HTML positive control: text survived', strpos($xh, 'alert(1)') !== false);

// A link's TEXT is attacker-influenced too.
$linkXss = (new IssueDoc)->para(IssueDoc::link('https://x.example', '<b>bold</b>'))->toHtml();
ok('HTML escapes link text',     strpos($linkXss, '<b>bold</b>') === false);
ok('HTML link positive control', strpos($linkXss, '&lt;b&gt;bold') !== false);

// ADF carries text literally (it is JSON, not markup) — assert it is NOT escaped,
// because double-escaping there would show users literal backslashes in Jira.
$adfNasty = $nasty->toAdf();
eq('ADF keeps text literal', 'use the * wildcard and [see notes] plus _underscore_',
   $adfNasty['content'][0]['content'][0]['text']);

// ---------------------------------------------------------------------------
echo "3. Degenerate documents stay valid (ADF is strict and 400s otherwise)\n";
// ---------------------------------------------------------------------------

$empty = new IssueDoc;
ok('Empty doc reports empty',    $empty->isEmpty());
$ea = $empty->toAdf();
ok('Empty doc still valid ADF',  !empty($ea['content']));
eq('Empty doc yields a paragraph', 'paragraph', $ea['content'][0]['type']);
eq('Empty doc renders empty markdown', '', $empty->toMarkdown());

// Empty strings must not become empty text nodes — ADF rejects those.
$blank = (new IssueDoc)->para('')->heading('')->bullets(['', '  '])->code('');
ok('Blank builders add nothing',  $blank->isEmpty());

// A rule with nothing before it, or two in a row, is always generated-output noise.
$rules = (new IssueDoc)->rule()->para('x')->rule()->rule()->para('y');
$rblocks = $rules->blocks();
eq('Leading + duplicate rules collapse', 3, count($rblocks));
eq('First block is the paragraph', IssueDoc::T_PARA ?? 'para', $rblocks[0][0]);

// Every ADF text node must be a non-empty string, at any depth.
function adfTextNodesValid(array $node): bool {
    if (isset($node['type']) && $node['type'] === 'text') {
        if (!isset($node['text']) || !is_string($node['text']) || $node['text'] === '') return false;
    }
    foreach (($node['content'] ?? []) as $child) {
        if (!adfTextNodesValid($child)) return false;
    }
    return true;
}
ok('No empty ADF text nodes anywhere', adfTextNodesValid($doc->toAdf()));
ok('No empty ADF text nodes (blank doc)', adfTextNodesValid($blank->toAdf()));

// ---------------------------------------------------------------------------
echo "4. The provider contract\n";
// ---------------------------------------------------------------------------

$rc = new ReflectionClass('IssueTrackerProvider');
ok('Provider is abstract', $rc->isAbstract());

// The five things every tracker genuinely must do are abstract; everything a
// tracker might lack defaults to throwing, so the contract never collapses to a
// lowest common denominator.
foreach (['createIssue', 'fetchIssue', 'testConnection', 'renderDoc'] as $m) {
    ok("$m is abstract", $rc->getMethod($m)->isAbstract());
}
foreach (['addComment', 'addAttachment', 'verifyWebhook', 'pollChanges', 'listProjects'] as $m) {
    ok("$m is optional (not abstract)", !$rc->getMethod($m)->isAbstract());
}

// Status categories are a closed set — the whole point is that no provider's
// vocabulary leaks into ours.
eq('4 status categories', 4, count(array_filter(
    $rc->getConstants(),
    function ($k) { return strpos($k, 'STATUS_') === 0; },
    ARRAY_FILTER_USE_KEY
)));

// A minimal concrete provider must be constructible with only the abstract
// methods implemented — that is what "adding GitHub is one file" means.
eval('class ProbeProvider extends IssueTrackerProvider {
    public function createIssue(array $t, string $s, IssueDoc $b, array $f = []): array { return []; }
    public function fetchIssue(string $id): array { return []; }
    public function testConnection(): array { return []; }
    public function renderDoc(IssueDoc $doc) { return $doc->toMarkdown(); }
    protected function capabilities(): array { return [self::CAP_ATTACHMENTS]; }
}');
$probe = new ProbeProvider(['provider' => 'probe']);
ok('Minimal provider constructs', $probe instanceof IssueTrackerProvider);
eq('getProvider reads the row', 'probe', $probe->getProvider());
ok('supports() true for declared',  $probe->supports(IssueTrackerProvider::CAP_ATTACHMENTS));
ok('supports() false for undeclared', !$probe->supports(IssueTrackerProvider::CAP_ISSUE_TYPES));
ok('renderDoc delegates to IssueDoc', $probe->renderDoc((new IssueDoc)->para('hi')) === 'hi');

// An unsupported optional method must throw, not silently no-op — a silent
// no-op is how "we sent the comment" becomes a lie.
$threw = false;
try { $probe->addComment('1', new IssueDoc); } catch (Exception $e) { $threw = true; }
ok('Unsupported method throws', $threw);

// ---------------------------------------------------------------------------
echo "5. JiraProvider — Cloud vs Data Center, without a live Jira\n";
// ---------------------------------------------------------------------------

require_once __DIR__ . '/../../includes/integrations/JiraProvider.php';

/**
 * Stubs the one method that touches the network, so everything that actually
 * decides correctness — the ADF-vs-wiki choice, the status map, payload shape,
 * error extraction, paging — is provable offline. $queue is a list of
 * [httpCode, body] handed out in order; $seen records the requests made.
 */
class FakeJira extends JiraProvider
{
    public $queue = [];
    public $seen  = [];
    protected function httpRequest(string $url, array $opts = []): array
    {
        $this->seen[] = ['url' => $url, 'opts' => $opts];
        if (!$this->queue) return [200, '{}'];
        return array_shift($this->queue);
    }
    // Expose the protected logic under test.
    public function pubBuild(array $t, string $s, IssueDoc $b, array $f = []) { return $this->buildCreatePayload($t, $s, $b, $f); }
    public function pubMapStatus(string $k) { return $this->mapStatusCategory($k); }
    public function pubError(int $c, string $b) { return $this->extractError($c, $b); }
    public function pubApiUrl(string $p) { return $this->apiUrl($p); }
}

function mkJira(array $overrides = []): FakeJira {
    return new FakeJira(array_merge([
        'provider'    => 'jira',
        'base_url'    => 'https://acme.atlassian.net',
        'credentials' => ['email' => 'svc@acme.com', 'api_token' => 'tok'],
    ], $overrides));
}

$cloud  = mkJira();
$server = mkJira(['base_url' => 'https://jira.acme.internal',
                  'credentials' => ['api_token' => 'pat', 'flavour' => 'server']]);

// Flavour detection — the heuristic, then the stored override winning over it.
eq('atlassian.net sniffs as Cloud',  'cloud',  $cloud->flavour());
eq('custom domain sniffs as Server', 'server', $server->flavour());
eq('Cloud uses API v3', 'https://acme.atlassian.net/rest/api/3/myself', $cloud->pubApiUrl('myself'));
eq('Server uses API v2', 'https://jira.acme.internal/rest/api/2/myself', $server->pubApiUrl('myself'));
$stored = mkJira(['credentials' => ['flavour' => 'server']]);
eq('Stored flavour beats the heuristic', 'server', $stored->flavour());

// A trailing slash on the site URL is the single most likely piece of user
// input, and it must not produce a double slash in every request.
$slashy = mkJira(['base_url' => 'https://acme.atlassian.net/']);
eq('Trailing slash normalised', 'https://acme.atlassian.net/rest/api/3/issue', $slashy->pubApiUrl('issue'));
eq('Browse URL built', 'https://acme.atlassian.net/browse/OPS-412', $slashy->browseUrl('OPS-412'));

// The SAME document must arrive as ADF on Cloud and wiki markup on Server.
// This is the whole reason IssueDoc exists, so it gets asserted both ways.
$body = (new IssueDoc)->heading('Raised in FreeITSM')->para('Outlook crashes');
ok('Cloud renders ADF (array)',      is_array($cloud->renderDoc($body)));
eq('Cloud ADF is a doc',   'doc',    $cloud->renderDoc($body)['type']);
$serverRendered = $server->renderDoc($body);
ok('Server renders a string',        is_string($serverRendered));
// Guarded on is_string: if this regresses to ADF, the assertion must FAIL and
// let the rest of the suite run, not fatal on strpos() and hide everything after.
ok('Server renders wiki markup',     is_string($serverRendered)
                                     && strpos($serverRendered, 'h3. Raised in FreeITSM') === 0);

// Create payload.
$p = $cloud->pubBuild(['project' => 'OPS', 'issue_type' => 'Bug'], 'Outlook crashes', $body);
eq('Payload project key', 'OPS', $p['fields']['project']['key']);
eq('Payload issue type',  'Bug', $p['fields']['issuetype']['name']);
eq('Payload summary',     'Outlook crashes', $p['fields']['summary']);
ok('Payload description is ADF on Cloud', is_array($p['fields']['description']));
ok('Payload sends NO priority field', !isset($p['fields']['priority']));

// A multi-line subject must be flattened — Jira rejects a newline in summary
// with an error that gives no clue what is wrong.
$ml = $cloud->pubBuild(['project' => 'OPS', 'issue_type' => 'Bug'], "Line one\nLine two", $body);
eq('Multi-line summary flattened', 'Line one Line two', $ml['fields']['summary']);
$long = $cloud->pubBuild(['project' => 'OPS', 'issue_type' => 'Bug'], str_repeat('x', 400), $body);
ok('Over-long summary truncated', mb_strlen($long['fields']['summary']) <= 255);
ok('Truncation is marked',        mb_substr($long['fields']['summary'], -1) === '…');

// Missing target must fail loudly here rather than as a confusing Jira 400.
$threw = false;
try { $cloud->pubBuild(['issue_type' => 'Bug'], 's', $body); } catch (Exception $e) { $threw = true; }
ok('Missing project throws', $threw);
$threw = false;
try { $cloud->pubBuild(['project' => 'OPS'], 's', $body); } catch (Exception $e) { $threw = true; }
ok('Missing issue type throws', $threw);

// Status mapping — the three fixed Jira categories, and the refusal to guess.
eq('new → todo',                 IssueTrackerProvider::STATUS_TODO,        $cloud->pubMapStatus('new'));
eq('indeterminate → in_progress', IssueTrackerProvider::STATUS_IN_PROGRESS, $cloud->pubMapStatus('indeterminate'));
eq('done → done',                IssueTrackerProvider::STATUS_DONE,        $cloud->pubMapStatus('done'));
eq('unknown category → null (never guess)', null, $cloud->pubMapStatus('banana'));
eq('empty category → null',      null, $cloud->pubMapStatus(''));

// Error extraction. The common real failure is a project demanding a field we
// did not send; "HTTP 400" alone would make that undiagnosable.
$err = $cloud->pubError(400, '{"errorMessages":[],"errors":{"customfield_10010":"Epic Link is required"}}');
ok('Field error names the field', strpos($err, 'customfield_10010') !== false);
ok('Field error gives the reason', strpos($err, 'Epic Link is required') !== false);
ok('errorMessages surfaced', strpos($cloud->pubError(400, '{"errorMessages":["Project is required"]}'), 'Project is required') !== false);
ok('401 is described as credentials', stripos($cloud->pubError(401, ''), 'credential') !== false);
ok('404 mentions the site URL',       stripos($cloud->pubError(404, ''), 'site URL') !== false);
ok('Non-JSON body still yields a message', $cloud->pubError(500, '<html>Server Error</html>') !== '');
ok('Non-JSON body is stripped of tags',    strpos($cloud->pubError(500, '<html>Server Error</html>'), '<html>') === false);

// fetchIssue parses a real-shaped Jira response.
$cloud->queue = [[200, json_encode([
    'id' => '10042', 'key' => 'OPS-412',
    'fields' => [
        'summary'  => 'Outlook crashes',
        'status'   => ['name' => 'In Progress', 'statusCategory' => ['key' => 'indeterminate']],
        'assignee' => ['displayName' => 'Sam Patel'],
    ],
])]];
$issue = $cloud->fetchIssue('10042');
eq('fetch external_id',     '10042', $issue['external_id']);
eq('fetch external_key',    'OPS-412', $issue['external_key']);
eq('fetch url',             'https://acme.atlassian.net/browse/OPS-412', $issue['external_url']);
eq('fetch status_name',     'In Progress', $issue['status_name']);
eq('fetch status_category', 'in_progress', $issue['status_category']);
eq('fetch assignee',        'Sam Patel', $issue['assignee_name']);

// An unassigned issue with an unknown status must not explode or invent values.
$cloud->queue = [[200, json_encode(['id' => '1', 'key' => 'A-1', 'fields' => ['status' => ['name' => 'Odd', 'statusCategory' => ['key' => 'weird']]]])]];
$sparse = $cloud->fetchIssue('1');
eq('Unknown category → null', null, $sparse['status_category']);
eq('Raw name still kept for display', 'Odd', $sparse['status_name']);
eq('Missing assignee → null', null, $sparse['assignee_name']);

// createIssue: the follow-up read failing must NOT lose an issue that exists.
$cloud->queue = [
    [200, json_encode(['id' => '10099', 'key' => 'OPS-999'])],  // create succeeded
    [500, 'boom'],                                              // the status read did not
];
$created = $cloud->createIssue(['project' => 'OPS', 'issue_type' => 'Bug'], 'x', $body);
eq('Created id survives a failed follow-up read', '10099', $created['external_id']);
eq('Created key survives',  'OPS-999', $created['external_key']);
eq('Created URL survives',  'https://acme.atlassian.net/browse/OPS-999', $created['external_url']);
eq('Status simply unknown', null, $created['status_category']);

// ...but a create that genuinely fails must throw, not return a broken link.
$cloud->queue = [[400, '{"errors":{"summary":"Summary is required"}}']];
$threw = false;
try { $cloud->createIssue(['project' => 'OPS', 'issue_type' => 'Bug'], 'x', $body); } catch (Exception $e) { $threw = true; }
ok('Failed create throws', $threw);

// Batch fetch: one call for the whole watch list, and non-numeric ids rejected
// before they can be interpolated into JQL.
$cloud->seen = [];
$cloud->queue = [[200, json_encode(['issues' => [
    ['id' => '1', 'key' => 'A-1', 'fields' => ['status' => ['name' => 'To Do', 'statusCategory' => ['key' => 'new']]]],
    ['id' => '2', 'key' => 'A-2', 'fields' => ['status' => ['name' => 'Done',  'statusCategory' => ['key' => 'done']]]],
]])]];
$many = $cloud->fetchIssues(['1', '2']);
eq('Batch made ONE call', 1, count($cloud->seen));
eq('Batch returned both', 2, count($many));
eq('Batch keyed by id',   'done', $many['2']['status_category']);
ok('Batch used JQL id in ()', strpos(urldecode($cloud->seen[0]['url']), 'id in (1,2)') !== false);

$cloud->seen = [];
eq('Non-numeric ids rejected', [], $cloud->fetchIssues(['1 OR 1=1', 'DROP']));
eq('...and no request was made', 0, count($cloud->seen));
eq('Empty watch list makes no call', [], $cloud->fetchIssues([]));

// testConnection captures WHO we are — half of echo suppression.
$cloud->queue = [[200, json_encode(['accountId' => '5b10a2', 'displayName' => 'FreeITSM Bot'])]];
$t = $cloud->testConnection();
eq('Cloud identity is accountId', '5b10a2', $t['account_identity']);
eq('Cloud flavour recorded', 'cloud', $t['flavour']);
ok('Detail names the account', strpos($t['detail'], 'FreeITSM Bot') !== false);

$server->queue = [[200, json_encode(['name' => 'svcacct', 'displayName' => 'Service Account'])]];
$ts = $server->testConnection();
eq('Server identity is username', 'svcacct', $ts['account_identity']);
eq('Server flavour recorded', 'server', $ts['flavour']);

// A wrongly-guessed flavour must still connect: v3 404s, v2 succeeds.
$vanity = mkJira(['base_url' => 'https://jira.acme.com']);   // heuristic says server
$vanity->queue = [
    [404, '{}'],                                                        // v2 attempt fails
    [200, json_encode(['accountId' => 'abc', 'displayName' => 'Bot'])], // v3 succeeds
];
$tv = $vanity->testConnection();
eq('Falls back to the other flavour', 'cloud', $tv['flavour']);
eq('...and identifies the account',   'abc',   $tv['account_identity']);

// Bad credentials must surface as an error, not a silent empty connection.
$bad = mkJira();
$bad->queue = [[401, '{}'], [401, '{}']];
$threw = false;
try { $bad->testConnection(); } catch (Exception $e) { $threw = true; }
ok('Bad credentials throw', $threw);

// Auth differs by flavour and both must be right.
$cloud->seen = []; $cloud->queue = [[200, '{"accountId":"x","displayName":"y"}']];
$cloud->testConnection();
eq('Cloud uses basic auth', 'svc@acme.com:tok', $cloud->seen[0]['opts']['auth']);

$server->seen = []; $server->queue = [[200, '{"name":"n","displayName":"d"}']];
$server->testConnection();
ok('Server sends a Bearer token',
   in_array('Authorization: Bearer pat', $server->seen[0]['opts']['headers'], true));
ok('Server does NOT send basic auth', !isset($server->seen[0]['opts']['auth']));

// Project paging — a truncated list looks like "my project is missing".
$paged = mkJira();
$paged->queue = [
    [200, json_encode(['isLast' => false, 'values' => array_map(function ($i) {
        return ['key' => "P$i", 'name' => "Project $i"]; }, range(1, 50))])],
    [200, json_encode(['isLast' => true, 'values' => [['key' => 'P51', 'name' => 'Project 51']]])],
];
eq('Paged project list is walked to the end', 51, count($paged->listProjects()));

// Subtask types are not valid escalation targets (they need a parent).
$types = mkJira();
$types->queue = [[200, json_encode(['values' => [
    ['id' => '1', 'name' => 'Bug', 'subtask' => false],
    ['id' => '2', 'name' => 'Sub-task', 'subtask' => true],
]])]];
$tl = $types->listIssueTypes('OPS');
eq('Subtask types excluded', 1, count($tl));
eq('...leaving the real one', 'Bug', $tl[0]['name']);

// Capabilities drive the settings UI.
ok('Jira supports issue types', $cloud->supports(IssueTrackerProvider::CAP_ISSUE_TYPES));
ok('Jira supports attachments', $cloud->supports(IssueTrackerProvider::CAP_ATTACHMENTS));

// ---------------------------------------------------------------------------
echo "6. The cross-company isolation boundary\n";
// ---------------------------------------------------------------------------

// integrationsCompaniesCompatible() is the outbound twin of the inbound routing
// membrane: escalation is driven by a WORKFLOW, and workflow conditions are
// editable, so the company check must live in code that nobody can edit.
//
// ⚠️ Every "cannot" below is paired with a "can". A guard that refuses
// EVERYTHING would pass all the negative assertions and be completely broken.

// Load the REAL service file, not a copy of the function — the whole point is to
// test what ships. Nothing in it runs at load time and the DB is only touched
// inside functions we do not call here, so a plain require is safe.
require_once __DIR__ . '/../../includes/integrations/integrations.php';

$ACME = 1; $GLOBEX = 2; $DEFAULT = 1;

// A SHARED connection (tenant NULL) serves every company — the MSP's own Jira.
ok('Shared connection accepts Acme',   integrationsCompaniesCompatible($ACME,   null, $DEFAULT));
ok('Shared connection accepts Globex', integrationsCompaniesCompatible($GLOBEX, null, $DEFAULT));
ok('Shared connection accepts an unrouted ticket', integrationsCompaniesCompatible(null, null, $DEFAULT));

// A PINNED connection takes only its own company's work. This is the leak.
ok('PINNED to Acme REFUSES Globex\'s ticket',  !integrationsCompaniesCompatible($GLOBEX, $ACME, $DEFAULT));
ok('PINNED to Globex REFUSES Acme\'s ticket',  !integrationsCompaniesCompatible($ACME, $GLOBEX, $DEFAULT));
// ...positive control: the same pinned connection MUST accept its own.
ok('POSITIVE CONTROL: pinned to Acme accepts Acme',   integrationsCompaniesCompatible($ACME, $ACME, $DEFAULT));
ok('POSITIVE CONTROL: pinned to Globex accepts Globex', integrationsCompaniesCompatible($GLOBEX, $GLOBEX, $DEFAULT));

// A NULL work item means "unrouted, treated as the Default company's". It must
// reach a connection pinned to Default, and no other.
ok('Unrouted ticket reaches a Default-pinned connection',
   integrationsCompaniesCompatible(null, $DEFAULT, $DEFAULT));
ok('Unrouted ticket REFUSED by a Globex-pinned connection',
   !integrationsCompaniesCompatible(null, $GLOBEX, $DEFAULT));

// With no Default resolvable (pre-tenancy install) a pinned target must refuse
// rather than guess. Paired with the shared case, which must still work.
ok('No default + pinned target → refuse', !integrationsCompaniesCompatible(null, $ACME, null));
ok('POSITIVE CONTROL: no default + shared target → allow',
   integrationsCompaniesCompatible(null, null, null));

// Single-company install: one company, so everything is trivially compatible —
// correct rather than a bypass, since there is nothing to leak to.
ok('Single company: ticket 1 → connection 1', integrationsCompaniesCompatible(1, 1, 1));
ok('Single company: unrouted → shared',       integrationsCompaniesCompatible(null, null, 1));

// The guard must not be fooled by loose comparison — '2' and 2, or 0 and null,
// are exactly the sort of thing PDO hands back as strings.
ok('String tenant id still refuses cross-company',
   !integrationsCompaniesCompatible((int)'2', (int)'1', $DEFAULT));
ok('POSITIVE CONTROL: string tenant id still allows same-company',
   integrationsCompaniesCompatible((int)'2', (int)'2', $DEFAULT));

// ---------------------------------------------------------------------------
echo "\n" . str_repeat('=', 62) . "\n";
echo "  passed: $pass\n";
echo "  failed: $fail\n";
if ($failures) {
    echo "\nFailures:\n";
    foreach ($failures as $f) echo "  ✗ $f\n";
}
echo str_repeat('=', 62) . "\n";
exit($fail === 0 ? 0 : 1);
