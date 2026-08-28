<?php
/**
 * Storage persistence — does what FreeITSM writes actually survive an update?
 *
 * ⚠️ THE PROBLEM THIS EXISTS FOR (issue #109, and the tail of #102).
 *
 * Under Docker, `docker compose up -d --build` does not update the running
 * container. It builds a new image and REPLACES the container, discarding
 * everything written inside it. Only volumes — storage that lives outside the
 * container — are carried across.
 *
 * So a directory that is not on a volume is emptied every time the operator
 * updates. And the way that surfaces is unusually cruel: the database IS on a
 * volume, so it survives intact. What is left is a complete and correct set of
 * records pointing at files that no longer exist, and the honest message for
 * that state — "recorded but missing from storage" — reads to the operator as
 * data corruption rather than as a missing mount.
 *
 * 🔑 THE ONLY MOMENT ANYTHING CAN BE DONE ABOUT IT IS BEFORE THE REBUILD. Once
 * the container is replaced the old one is removed and its writable layer goes
 * with it; the files are not recoverable from Docker by any route. That is why
 * this check exists at all: an after-the-fact explanation is worth very little,
 * and a warning in documentation only reaches people who go looking. This one
 * reaches them where they already are.
 *
 * ⚠️ THE RULE IS "IS IT ON THE ROOT FILESYSTEM", NOT "IS IT A MOUNT POINT".
 *
 * The obvious test — does this directory's device differ from its PARENT's,
 * i.e. is it a mount point — raises a false alarm on a perfectly safe and
 * reasonably common setup. Somebody who bind-mounts the whole application
 * directory has every one of these folders on the host, entirely safe, and not
 * one of them is a mount point in its own right. Telling that operator their
 * data is about to be destroyed would be worse than saying nothing, because a
 * check that cries wolf is switched off and then never believed again.
 *
 * Comparing against the device of "/" instead answers the question actually
 * being asked: is this on the container's own writable layer, which is thrown
 * away, or on something else, which is not. A directory inside a mounted parent
 * is correctly reported as safe. Verified against four cases — named volume,
 * bind mount, directory inside a mounted parent, and nothing mounted at all.
 *
 * ⚠️ NEVER GUESS "AT RISK". Every uncertainty resolves to 'unknown', which the
 * callers report as an unknown rather than folding into the warning count. A
 * container is the only place this can be answered, and even there stat() can
 * fail; an alarm raised on a failed stat() is an alarm about nothing.
 *
 * 🔑 THE DIRECTORY LIST BELOW IS THE SAME LIST AS THE ONE IN .gitignore — the
 * "Upload directories" and "Module-specific runtime content" blocks. It is
 * maintained by hand in both places, which is precisely the shape of failure
 * that caused #109: the deployment recipe belongs to no module, so nobody
 * updating a module thinks to revisit it. ⚠️ ADD A DIRECTORY HERE WHENEVER YOU
 * ADD ONE THERE.
 */

if (!defined('STORAGE_PERSISTENCE_LOADED')) {
    define('STORAGE_PERSISTENCE_LOADED', true);
}

/**
 * Is this PHP running inside a container?
 *
 * /.dockerenv is created by the Docker daemon in every container it starts.
 * Absent means either a native install (WAMP, XAMPP, LAMP, a plain Debian box)
 * or a non-Docker runtime such as Podman.
 *
 * Being wrong in that second direction is deliberately the safe way round: an
 * unrecognised runtime produces silence, never a false warning. Nothing in this
 * file should ever have anything to say to somebody running on WAMP.
 */
function storagePersistenceInContainer(): bool
{
    return file_exists('/.dockerenv');
}

/**
 * Every directory the running application writes user files into.
 *
 * 'path'  absolute, resolved from the application root
 * 'rel'   how an operator would recognise it, and what goes in a volume line
 * 'label' plain English — what an operator loses if this one is not persisted
 */
function storagePersistenceDirectories(): array
{
    $root = dirname(__DIR__);

    $dirs = [
        ['rel' => 'tickets/attachments',              'label' => 'Attachments that arrived on inbound email'],
        ['rel' => 'change-management/attachments',    'label' => 'Files attached to a change record'],
        ['rel' => 'uploads',                          'label' => 'Documents attached to tickets, notes, assets and articles, and asset import files'],
        ['rel' => 'recordings',                       'label' => 'Screen recordings made from the self-service portal'],
        ['rel' => 'lms/content',                      'label' => 'Uploaded course content and SCORM packages'],
        ['rel' => 'contracts/rfp-builder/uploads',    'label' => 'Files uploaded to an RFP'],
        ['rel' => 'system/uploads/branding',          'label' => 'Your logo and other branding images'],
        ['rel' => 'war-room/attachments',             'label' => 'Files shared in a war room'],
    ];

    $out = [];
    foreach ($dirs as $d) {
        $d['path'] = $root . '/' . $d['rel'];
        $out[] = $d;
    }

    // The encryption key sits outside the application root and is configurable,
    // so it is resolved rather than hard-coded. It is listed last because it is
    // the least likely to be wrong and by far the most serious if it is: mailbox
    // passwords and integration credentials are encrypted with it, and losing it
    // does not corrupt them, it makes them permanently unreadable.
    if (defined('ENCRYPTION_KEY_PATH') && ENCRYPTION_KEY_PATH !== '') {
        $keyDir = dirname((string) ENCRYPTION_KEY_PATH);
        if ($keyDir !== '' && $keyDir !== '.') {
            $out[] = [
                'rel'      => $keyDir,
                'path'     => $keyDir,
                'label'    => 'The encryption key — mailbox passwords and integration credentials cannot be read without it',
                'critical' => true,
            ];
        }
    }

    return $out;
}

/**
 * Assess one directory. Returns 'persisted' | 'at_risk' | 'missing' | 'unknown'.
 */
function storagePersistenceStatus(string $dir, ?int $rootDev = null): string
{
    if (!is_dir($dir)) {
        // Not an alarm. Several of these are created on first use by
        // uploadPrepareDir(), so an install that has never had an RFP upload
        // legitimately has no RFP folder.
        return 'missing';
    }

    if ($rootDev === null) {
        $rootStat = @stat('/');
        if ($rootStat === false || !isset($rootStat['dev'])) return 'unknown';
        $rootDev = (int) $rootStat['dev'];
    }

    $s = @stat($dir);
    if ($s === false || !isset($s['dev'])) return 'unknown';

    return ((int) $s['dev'] !== $rootDev) ? 'persisted' : 'at_risk';
}

/**
 * The whole picture, for whichever screen is asking.
 *
 * 'applicable' is false on anything that is not a container, and every caller
 * must check it before showing the operator anything at all. On a native
 * install the question is meaningless — `git pull` does not delete these
 * folders — and a warning there would be pure noise.
 */
function storagePersistenceReport(): array
{
    $report = [
        'applicable'   => storagePersistenceInContainer(),
        'root_device'  => null,
        'directories'  => [],
        'at_risk'      => 0,
        'persisted'    => 0,
        'unknown'      => 0,
        'missing'      => 0,
        'critical_at_risk' => false,
    ];

    if (!$report['applicable']) {
        return $report;
    }

    $rootStat = @stat('/');
    $rootDev  = ($rootStat !== false && isset($rootStat['dev'])) ? (int) $rootStat['dev'] : null;
    $report['root_device'] = $rootDev;

    foreach (storagePersistenceDirectories() as $d) {
        $status = storagePersistenceStatus($d['path'], $rootDev);
        $d['status'] = $status;
        $report['directories'][] = $d;

        if ($status === 'at_risk') {
            $report['at_risk']++;
            if (!empty($d['critical'])) $report['critical_at_risk'] = true;
        } elseif ($status === 'persisted') {
            $report['persisted']++;
        } elseif ($status === 'unknown') {
            $report['unknown']++;
        } else {
            $report['missing']++;
        }
    }

    return $report;
}

/**
 * The volume lines an operator would need to add, ready to paste.
 *
 * Only the directories actually at risk are offered. A block that lists
 * everything, including what is already correct, invites the operator to paste
 * over a working configuration.
 */
function storagePersistenceSuggestedVolumes(array $report): array
{
    $lines = [];
    foreach ($report['directories'] as $d) {
        if ($d['status'] !== 'at_risk') continue;
        $name = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($d['rel'], '/')));
        $name = trim($name, '-');
        if ($name === '') continue;
        $lines[] = '      - ' . $name . ':' . $d['path'];
    }
    return $lines;
}
