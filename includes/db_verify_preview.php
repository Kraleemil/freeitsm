<?php
/**
 * Database Verification — READ-ONLY preview of what a run would change.
 *
 * ── Why this exists (F10) ────────────────────────────────────────────────────
 *
 * db_verify.php's own header said it "is idempotent and never drops anything".
 * That was not true and had not been for some time: the file contains DROP COLUMN
 * for six columns, plus DROP INDEX and DROP FOREIGN KEY. An administrator reading
 * the header would reasonably press the button without taking a backup, which is
 * exactly the decision the sentence was influencing. The comment is now corrected,
 * and this file exists so the answer to "what will this do to my database?" is
 * something you can read BEFORE it happens rather than in the results afterwards.
 *
 * ── What this is NOT ─────────────────────────────────────────────────────────
 *
 * ⚠️ This is not the migration running with a flag turned off, and it is important
 * not to describe it as one. A true statement-level dry run would mean threading a
 * $dryRun through roughly two hundred exec() calls in a 2,300-line procedural
 * script, and MySQL cannot help: DDL causes an implicit COMMIT, so the usual trick
 * of running it inside a transaction and rolling back does not work for schema
 * changes. Attempting it would put a new conditional in front of every migration in
 * the app — the single most dangerous refactor available in this codebase — to make
 * a preview slightly more precise.
 *
 * Instead this INSPECTS the database and reports the differences it can see. It
 * opens no transaction, writes nothing, and calls none of db_verify's migration
 * code, so it cannot itself break anything.
 *
 * The consequence, stated plainly rather than buried: the destructive list below is
 * MAINTAINED BY HAND. If someone adds a DROP to db_verify.php and does not add it
 * here, the preview will under-report. DB_VERIFY_DESTRUCTIVE is the register of
 * that promise, and the test suite asserts the two files agree.
 */

/**
 * Every destructive operation db_verify.php can perform, with the probe that says
 * whether it would fire on THIS database right now.
 *
 * kind:   'column' | 'index'
 * table:  the table it acts on
 * name:   the column or index it removes
 * why:    what an administrator needs to understand about the loss
 */
const DB_VERIFY_DESTRUCTIVE = [
    ['kind' => 'column', 'table' => 'tickets', 'name' => 'status',
     'why'  => 'Legacy free-text status, replaced by status_id. Dropped only after the values have been migrated into ticket_statuses.'],
    ['kind' => 'column', 'table' => 'tickets', 'name' => 'priority',
     'why'  => 'Legacy free-text priority, replaced by priority_id. Dropped only after migration into ticket_priorities.'],
    ['kind' => 'column', 'table' => 'tickets', 'name' => 'requester_email',
     'why'  => 'Superseded by the users table via user_id. Dropped once every ticket has a requester row.'],
    ['kind' => 'column', 'table' => 'tickets', 'name' => 'requester_name',
     'why'  => 'Superseded by the users table via user_id.'],
    ['kind' => 'column', 'table' => 'ticket_rota_entries', 'name' => 'location',
     'why'  => 'Removed when the rota moved to a separate locations table.'],
    ['kind' => 'column', 'table' => 'warroom_messages', 'name' => 'team_id',
     'why'  => 'War Room channels replaced per-team rooms. Any team association on old messages is lost.'],
    ['kind' => 'column', 'table' => 'warroom_presence', 'name' => 'team_id',
     'why'  => 'Same change as warroom_messages.'],
    // ⚠️ This one was missed when the register was first written, and the drift
    // test in tests/security-findings caught it rather than a person. That is the
    // test earning its place: a hand-maintained list of destructive operations is
    // exactly the kind of thing that silently falls behind the code it describes.
    ['kind' => 'column', 'table' => 'assets', 'name' => 'supplier',
     'why'  => 'Legacy free-text supplier name, replaced by supplier_id pointing at the suppliers table. Dropped only after each name has been matched or inserted there — but any name that failed to migrate is lost with the column.'],
    ['kind' => 'index',  'table' => 'ticket_types', 'name' => 'uq_ticket_types_name',
     'why'  => 'Global unique name, dropped so the same type name can exist per company. No data is lost.'],
    ['kind' => 'index',  'table' => 'warroom_messages', 'name' => 'ix_warroom_messages_team',
     'why'  => 'Index on the team_id column being removed above. No data is lost.'],
];

/** Does this table exist? */
function dbPreviewTableExists(PDO $conn, string $dbName, string $table): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    $stmt->execute([$dbName, $table]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Does this column exist? */
function dbPreviewColumnExists(PDO $conn, string $dbName, string $table, string $column): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?");
    $stmt->execute([$dbName, $table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Does this index exist? */
function dbPreviewIndexExists(PDO $conn, string $dbName, string $table, string $index): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?");
    $stmt->execute([$dbName, $table, $index]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Compare the declared schema against the live database.
 *
 * @return array{
 *   tables_to_create: string[],
 *   columns_to_add: array<int,array{table:string,column:string,definition:string}>,
 *   destructive: array<int,array{kind:string,table:string,name:string,why:string}>,
 *   summary: array{creates:int,adds:int,drops:int},
 *   read_only: bool
 * }
 */
function dbVerifyPreview(PDO $conn, array $schema, string $dbName): array
{
    $tablesToCreate = [];
    $columnsToAdd   = [];

    foreach ($schema as $table => $columns) {
        if (!dbPreviewTableExists($conn, $dbName, $table)) {
            $tablesToCreate[] = $table;
            continue;   // the whole table is new; listing every column would be noise
        }
        foreach ($columns as $column => $definition) {
            if (!dbPreviewColumnExists($conn, $dbName, $table, $column)) {
                $columnsToAdd[] = [
                    'table'      => $table,
                    'column'     => $column,
                    'definition' => $definition,
                ];
            }
        }
    }

    // Only report a drop when its target is actually present: on an up-to-date
    // database this list is empty, and an empty list is the answer that lets
    // somebody press the button without taking a backup first.
    $destructive = [];
    foreach (DB_VERIFY_DESTRUCTIVE as $op) {
        if (!dbPreviewTableExists($conn, $dbName, $op['table'])) {
            continue;
        }
        $present = $op['kind'] === 'column'
            ? dbPreviewColumnExists($conn, $dbName, $op['table'], $op['name'])
            : dbPreviewIndexExists($conn, $dbName, $op['table'], $op['name']);
        if ($present) {
            $destructive[] = $op;
        }
    }

    return [
        'tables_to_create' => $tablesToCreate,
        'columns_to_add'   => $columnsToAdd,
        'destructive'      => $destructive,
        'summary'          => [
            'creates' => count($tablesToCreate),
            'adds'    => count($columnsToAdd),
            'drops'   => count($destructive),
        ],
        // Stated in the payload so the UI can never imply anything was changed.
        'read_only'        => true,
    ];
}
