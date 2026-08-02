<?php
/**
 * System — how to set up an issue tracker, end to end.
 *
 * Reached as /system/integrations/jira/help (see .htaccess) or, without
 * mod_rewrite, help.php?provider=jira.
 *
 * ⚠️ Written for somebody who does NOT already know Jira. That is the whole
 * point of it existing: an admin who knows Jira can read the mapping screen and
 * guess, and everybody else is looking at a modal full of dropdowns with no idea
 * what a "project key" is. Prose over reference tables, and every step says what
 * happens if you skip it.
 *
 * ONE page for every provider, like provider.php — the provider's name comes
 * from the registry. The Jira-specific parts (where Atlassian keeps API tokens,
 * what a project key looks like) are guarded on the provider key so a second
 * connector can add its own without unpicking this.
 */
session_start();
require_once '../../config.php';
require_once '../../includes/i18n.php';
require_once '../../includes/timezone.php';
I18n::initFromSession();
Tz::init();
require_once '../../includes/functions.php';
require_once '../../includes/theme.php';
require_once '../../includes/tenancy.php';
require_once '../../includes/integrations/integrations.php';

$current_page = 'integrations';
$path_prefix  = '../../';
$translationNamespaces = ['common', 'system'];

$providerKey = strtolower(trim((string)($_GET['provider'] ?? '')));
$meta        = integrationsProviderMeta($providerKey);
if (!$meta) {
    header('Location: ./');
    exit;
}
$name   = $meta['name'];
$isJira = ($providerKey === 'jira');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(I18n::getLocale()); ?>" data-theme="<?php echo htmlspecialchars(Theme::active()); ?>" data-theme-mode="<?php echo htmlspecialchars(Theme::mode()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Desk - <?php echo htmlspecialchars($name); ?> setup</title>
    <link rel="stylesheet" href="../../assets/css/theme.css?v=22">
    <link rel="stylesheet" href="../../assets/css/inbox.css">
    <style>
        /* Same full-width shell as provider.php. ⚠️ No auto margin — an
           inherited `margin: … auto` would re-centre it even with max-width. */
        .int-container { height: calc(100vh - 48px); overflow-y: auto; padding: 30px 20px; }
        .help-wrap     { max-width: 900px; }
        .page-title    { font-size: 24px; font-weight: 600; color: var(--text); margin: 0 0 6px; }
        .page-subtitle { font-size: 14px; color: var(--text-muted); margin: 0 0 8px; line-height: 1.55; }
        .back-link     { display: inline-block; margin-bottom: 18px; font-size: 13px;
                         color: var(--text-muted); text-decoration: none; }
        .back-link:hover { color: var(--sys-accent); }

        .help-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 24px; margin-bottom: 18px; box-shadow: var(--shadow);
        }
        .help-card h3 {
            margin: 0 0 4px; font-size: 17px; font-weight: 600; color: var(--text);
            display: flex; align-items: baseline; gap: 10px;
        }
        .step-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0;
            background: var(--sys-accent); color: var(--on-accent);
            font-size: 13px; font-weight: 700;
        }
        .help-card p  { font-size: 14px; color: var(--text); line-height: 1.65; margin: 12px 0; }
        .help-card ul, .help-card ol { font-size: 14px; color: var(--text); line-height: 1.75; padding-left: 22px; margin: 12px 0; }
        .help-card li { margin-bottom: 6px; }
        .help-card strong { font-weight: 600; }
        .muted { color: var(--text-muted); }

        code, .code-block {
            font-family: ui-monospace, Consolas, monospace; font-size: 13px;
            background: var(--surface-2); border: 1px solid var(--border);
            border-radius: 5px; color: var(--text);
        }
        code { padding: 1px 6px; }
        .code-block { display: block; padding: 12px 14px; margin: 12px 0; overflow-x: auto; white-space: pre; }

        /* "If you skip this…" — every step has one, because silent failure is
           this feature's main hazard. */
        .consequence {
            border-left: 3px solid var(--sys-accent); background: var(--surface-2);
            padding: 12px 16px; margin: 14px 0; border-radius: 0 6px 6px 0;
            font-size: 13px; line-height: 1.6; color: var(--text);
        }
        .consequence b { display: block; margin-bottom: 3px; }

        .help-table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 13.5px; }
        .help-table th, .help-table td {
            text-align: left; padding: 9px 12px; border-bottom: 1px solid var(--border);
            color: var(--text); vertical-align: top; line-height: 1.6;
        }
        .help-table th { font-weight: 600; color: var(--text-muted); font-size: 12px;
                         text-transform: uppercase; letter-spacing: .04em; }
        .help-table tr:last-child td { border-bottom: none; }

        .toc { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
        .toc a {
            font-size: 13px; text-decoration: none; color: var(--text-muted);
            border: 1px solid var(--border); border-radius: 20px; padding: 5px 13px;
            background: var(--surface);
        }
        .toc a:hover { color: var(--sys-accent); border-color: var(--sys-accent); }
    </style>
</head>
<body>
<?php include '../../includes/header.php'; ?>

<div class="int-container">
  <div class="help-wrap">
    <a class="back-link" href="./<?php echo htmlspecialchars($providerKey); ?>">&larr; Back to <?php echo htmlspecialchars($name); ?></a>

    <h1 class="page-title">Setting up <?php echo htmlspecialchars($name); ?></h1>
    <p class="page-subtitle">
        Connecting <?php echo htmlspecialchars($name); ?> lets an analyst hand a ticket to the development
        team without leaving FreeITSM: raise the issue, watch its status, and read the developers'
        replies on the ticket. This page walks through it from nothing, and assumes you have
        <strong>not</strong> used <?php echo htmlspecialchars($name); ?> before.
    </p>

    <div class="toc">
        <a href="#step1">1. Get a token</a>
        <a href="#step2">2. Add the connection</a>
        <a href="#step3">3. Schedule the check</a>
        <a href="#step4">4. Comments back</a>
        <a href="#step5">5. Mapping</a>
        <a href="#step6">6. Raise an issue</a>
        <a href="#trouble">Troubleshooting</a>
    </div>

    <!-- ─────────────────────────────────────────── 1 -->
    <div class="help-card" id="step1">
        <h3><span class="step-num">1</span> Get an API token</h3>
        <p>
            FreeITSM signs in to <?php echo htmlspecialchars($name); ?> as <em>you</em> — or better, as a
            dedicated account — using an <strong>API token</strong>. That is a long password created
            specifically for other software, so you never put your real one into another system, and you
            can revoke it without changing your own login.
        </p>
        <?php if ($isJira): ?>
        <p>
            The token is <strong>not</strong> in Jira itself. It lives in your Atlassian account:
        </p>
        <ol>
            <li>Go to <code>id.atlassian.com</code> &rarr; <strong>Security</strong> &rarr; <strong>API tokens</strong>.</li>
            <li>Choose <strong>Create API token</strong> and name it something you will recognise later, e.g. <code>FreeITSM</code>.</li>
            <li>Copy it now. <strong>Atlassian shows it once</strong> and never again.</li>
        </ol>
        <div class="consequence">
            <b>Worth thinking about first</b>
            Whoever owns this token is who <?php echo htmlspecialchars($name); ?> appears to be. If you use
            your own account, issues raised by FreeITSM look as though you raised them. A separate account
            (say <code>freeitsm@yourcompany</code>) keeps that tidy — but your own account is perfectly fine
            to start with, and comments you write yourself will still come back to the ticket either way.
        </div>
        <?php else: ?>
        <p class="muted">Create an API token in <?php echo htmlspecialchars($name); ?> and copy it — you will paste it in the next step.</p>
        <?php endif; ?>
    </div>

    <!-- ─────────────────────────────────────────── 2 -->
    <div class="help-card" id="step2">
        <h3><span class="step-num">2</span> Add the connection</h3>
        <p>On the <a href="./<?php echo htmlspecialchars($providerKey); ?>"><?php echo htmlspecialchars($name); ?> page</a>, press <strong>Add connection</strong>.</p>
        <table class="help-table">
            <tr><th>Field</th><th>What to put</th></tr>
            <tr><td><strong>Name</strong></td><td>Anything you will recognise — "Our Jira", "Acme's Jira".</td></tr>
            <tr><td><strong>Site URL</strong></td><td><?php echo $isJira ? 'The address you use to reach Jira, e.g. <code>https://yourcompany.atlassian.net</code>.' : 'The address of your ' . htmlspecialchars($name) . ' server.'; ?></td></tr>
            <?php if ($isJira): ?>
            <tr><td><strong>Email address</strong></td><td>The email your Atlassian account uses. <span class="muted">Leave blank for Jira Data Center / Server, which uses only a token.</span></td></tr>
            <?php endif; ?>
            <tr><td><strong>API token</strong></td><td>The token from step 1.</td></tr>
            <tr><td><strong>Company</strong></td><td>Leave as <em>All companies</em> unless this tracker belongs to one client. A ticket can only ever be escalated to a tracker its own company is allowed to use, and that rule cannot be overridden.</td></tr>
        </table>
        <p><strong>Press Test before saving.</strong> It tells you which account it connected as, or exactly what <?php echo htmlspecialchars($name); ?> objected to — which is far easier to fix now than after a rule fails at 2am.</p>
        <div class="consequence">
            <b>Coming back to edit it later?</b>
            Leave the token box <strong>empty</strong>. FreeITSM never shows you a stored token, so an empty
            box means "keep the one you already have", not "clear it".
        </div>
    </div>

    <!-- ─────────────────────────────────────────── 3 -->
    <div class="help-card" id="step3">
        <h3><span class="step-num">3</span> Schedule the check</h3>
        <p>
            FreeITSM does not sit waiting for <?php echo htmlspecialchars($name); ?> to call — it asks. Every
            few minutes a small scheduled job wakes up and puts one question to it: <em>what has changed?</em>
            Every status update and every comment arrives because that job ran.
        </p>
        <p><strong>Nothing on this page works until it is scheduled</strong>, and a new install has nothing scheduled.</p>
        <p class="muted">On Windows, in a Command Prompt opened as administrator (adjust the paths for your install):</p>
        <span class="code-block">schtasks /create /tn "FreeITSM Tracker Poll" /tr "C:\wamp64\bin\php\php8.4.0\php.exe C:\wamp64\www\freeitsm-app\cron\integration_poll.php" /sc minute /mo 5 /ru SYSTEM</span>
        <p class="muted">On Linux, in your crontab:</p>
        <span class="code-block">*/5 * * * * /usr/bin/php /var/www/freeitsm-app/cron/integration_poll.php &gt;/dev/null 2&gt;&amp;1</span>
        <p>You can also run it by hand at any time to see what it does — useful while testing:</p>
        <span class="code-block">php cron/integration_poll.php</span>
        <div class="consequence">
            <b>If you skip this</b>
            Every issue stays frozen at the status it had when it was raised, forever, and no comment
            ever reaches a ticket. There is no error and nothing in a log — a tracker nobody is asking
            about looks exactly like one where nothing has happened.
        </div>
    </div>

    <!-- ─────────────────────────────────────────── 4 -->
    <div class="help-card" id="step4">
        <h3><span class="step-num">4</span> Let comments come back</h3>
        <p>
            Tick <strong>Accept updates from <?php echo htmlspecialchars($name); ?></strong> on the connection
            (press the pencil on its row — it is under <em>Active</em>). Comments developers write on the
            issue then arrive on the linked ticket as <strong>internal notes</strong>.
        </p>
        <ul>
            <li><strong>They are always internal.</strong> Somebody writing in <?php echo htmlspecialchars($name); ?> does not know a customer might be reading, so a comment never reaches the requester unless you pass it on yourself.</li>
            <li><strong>Turning it on does not bring back old comments.</strong> The first check only marks the starting point. So write a <em>fresh</em> comment to test it, and wait for the check after that.</li>
            <li><strong>You will not get echoes.</strong> Comments FreeITSM posted itself are recognised and never re-imported.</li>
        </ul>
        <div class="consequence">
            <b>Testing it</b>
            Tick the box &rarr; let one check run &rarr; <em>then</em> write a comment in
            <?php echo htmlspecialchars($name); ?> &rarr; let the next check run. Comment before that first
            check and it will never appear, because as far as FreeITSM is concerned it happened before it
            was asked to listen.
        </div>
    </div>

    <!-- ─────────────────────────────────────────── 5 -->
    <div class="help-card" id="step5">
        <h3><span class="step-num">5</span> Mapping — the part worth ten minutes</h3>
        <p>
            Every issue has to be filed <em>somewhere</em>. Mapping is where you say where, once, instead of
            typing it into every rule you ever write. Press the <strong>arrows button</strong> on a connection's row.
        </p>

        <?php if ($isJira): ?>
        <p><strong>First, the vocabulary</strong>, because the screen assumes you know it:</p>
        <table class="help-table">
            <tr><th>Jira word</th><th>What it means</th></tr>
            <tr><td><strong>Project</strong></td><td>A container for issues, usually one per team or product. Every issue lives in exactly one.</td></tr>
            <tr><td><strong>Project key</strong></td><td>Its short code — the <code>KAN</code> in <code>KAN-6</code>. You will see it at the start of every issue number.</td></tr>
            <tr><td><strong>Issue type</strong></td><td>What kind of work it is: Task, Bug, Incident, Story. <strong>Each project decides its own list</strong>, so not every project has a "Bug".</td></tr>
            <tr><td><strong>Priority</strong></td><td>Jira's own urgency scale — often Highest / High / Medium / Low / Lowest, but projects can rename them.</td></tr>
        </table>
        <p class="muted">Not sure what your projects are called? The dropdowns on the mapping screen are filled from your real <?php echo htmlspecialchars($name); ?>, so you can simply look.</p>
        <?php endif; ?>

        <p><strong>Which project issues go in.</strong> Set the default and you are done — most installs never need more. The other rows are exceptions, and the most specific one wins:</p>
        <ul>
            <li>a <strong>department</strong> rule beats</li>
            <li>a <strong>company</strong> rule, which beats</li>
            <li>the <strong>default</strong>.</li>
        </ul>
        <p class="muted">So "the Development team's tickets go to DEV, everything else goes to our main project" is two rows.</p>

        <p><strong>Ticket type becomes issue type.</strong> Your <em>Fault</em> becomes their <em>Bug</em>, and so on. Set the default to whatever is safest — <?php echo $isJira ? '<code>Task</code> exists in almost every project' : 'the most generic type'; ?> — and add exceptions only if you need them.</p>

        <p><strong>Priority.</strong> Your urgency levels translated to theirs. Two things are deliberately different here:</p>
        <ul>
            <li><strong>There is no "everything else" row.</strong> Marking a development team's entire backlog urgent helps nobody, so anything you do not map is simply not sent — it still appears as text in the description.</li>
            <li><strong>A priority they reject will not lose your issue.</strong> Priorities are defined per project, so a project that renamed things may refuse yours. Rather than fail, FreeITSM raises the issue <em>without</em> a priority and notes why.</li>
        </ul>

        <div class="consequence">
            <b>You can ignore all of this</b>
            Mapping is optional. Skip it and you simply type a project key and issue type each time you
            escalate, exactly as you would have to anyway. Fill in the default and that stops.
        </div>
    </div>

    <!-- ─────────────────────────────────────────── 6 -->
    <div class="help-card" id="step6">
        <h3><span class="step-num">6</span> Raise an issue</h3>
        <p><strong>By hand:</strong> open a ticket, and in the <strong>Links</strong> strip choose
           <strong>Link to&hellip; &rarr; Issue tracker</strong>. Read the preview — it is exactly what the
           development team will see — then press <strong>Raise</strong>.</p>
        <p><strong>By rule:</strong> escalation is a <strong>Workflows</strong> action, so you can write
           <em>"when a ticket's type becomes Bug, raise it in <?php echo htmlspecialchars($name); ?>"</em>.
           In Workflows, add the action <strong>Escalate to issue tracker</strong>. Leave <em>Project key</em>
           and <em>Issue type</em> blank to use your mapping.</p>
        <ul>
            <li>Keep <strong>Skip if this ticket already has an issue</strong> ticked, or a trigger that fires repeatedly will hand the developers a pile of duplicates.</li>
            <li><strong>Dry run is safe</strong> — it describes what it would raise without creating anything.</li>
        </ul>
        <div class="consequence">
            <b>You cannot unsend it</b>
            An issue is visible to everyone with access to that project and FreeITSM cannot withdraw it.
            That is why there is a preview. Internal notes are never included — only the requester's own
            words and the ticket's details.
        </div>
    </div>

    <!-- ─────────────────────────────────────────── trouble -->
    <div class="help-card" id="trouble">
        <h3>If something is not working</h3>
        <table class="help-table">
            <tr><th>What you see</th><th>What it usually means</th></tr>
            <tr><td>Nothing ever changes status</td><td>The scheduled check is not running. Step 3 — this is by far the most common cause.</td></tr>
            <tr><td>Status updates, but no comments</td><td><strong>Accept updates</strong> is not ticked on that connection. Its row shows an <em>Updates on</em> badge when it is.</td></tr>
            <tr><td>Ticked it, but my test comment never arrived</td><td>It was probably written before the first check. Write a fresh one and wait — step 4.</td></tr>
            <tr><td>"rejected the credentials"</td><td>Wrong email, or the token has been revoked. Create a new token and press Test again.</td></tr>
            <tr><td>Something "&hellip;is required" naming a field</td><td>That project demands a field FreeITSM did not send — often an Epic Link. Ask whoever administers it to make the field optional, or use a different project.</td></tr>
            <tr><td>"doesn't exist or you don't have permission"</td><td>Wrong project key, or the account behind the token cannot see that project.</td></tr>
            <tr><td>"belongs to a different company than this ticket"</td><td>The tracker is reserved for one client and this ticket belongs to another. This is deliberate and cannot be overridden.</td></tr>
            <tr><td>The mapping screen says to run Database Verification</td><td>Mapping needs a table this install has not created yet. System &rarr; Database Verification.</td></tr>
        </table>
    </div>
  </div>
</div>
</body>
</html>
