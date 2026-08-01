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
echo "\n" . str_repeat('=', 62) . "\n";
echo "  passed: $pass\n";
echo "  failed: $fail\n";
if ($failures) {
    echo "\nFailures:\n";
    foreach ($failures as $f) echo "  ✗ $f\n";
}
echo str_repeat('=', 62) . "\n";
exit($fail === 0 ? 0 : 1);
