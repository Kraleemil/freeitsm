<?php
/**
 * Generator: rebuild includes/db_verify_indexes.php from database/freeitsm.sql.
 *
 * freeitsm.sql is the source of truth for the schema. This script extracts every
 * named secondary index (UNIQUE KEY / FULLTEXT KEY / KEY / INDEX — not PRIMARY,
 * not FOREIGN KEY) with its columns, and writes them as a PHP array the Database
 * Verification endpoint uses to restore any index a grown install is missing.
 *
 * Run after adding or changing an index in freeitsm.sql:
 *   php scripts/gen_db_verify_indexes.php
 *
 * Then review the diff and commit both files together. Keeping this list in step
 * with freeitsm.sql is the same discipline as db_verify's $schema (see the
 * Database-Verification-Developer-Guide wiki page).
 */

$root = dirname(__DIR__);
$sqlPath = $root . '/database/freeitsm.sql';
$outPath = $root . '/includes/db_verify_indexes.php';

// The parse lives in one place, shared with db_verify's drift self-check, so the
// generator and the checker can never disagree about what an index "is".
require_once $root . '/includes/db_verify_index_parse.php';

$sql = file_get_contents($sqlPath);
if ($sql === false) {
    fwrite(STDERR, "Cannot read $sqlPath\n");
    exit(1);
}

$rows = dbVerifyParseIndexesFromSql($sql);   // [ [table, name, type, cols], ... ]

$out  = "<?php\n";
$out .= "/**\n";
$out .= " * GENERATED — do not edit by hand.\n";
$out .= " *\n";
$out .= " * Every named secondary index in database/freeitsm.sql: [table, name, type,\n";
$out .= " * columns], where type is 'unique', 'fulltext' or 'key'. Consumed by\n";
$out .= " * api/system/db_verify.php to restore indexes a grown install is missing.\n";
$out .= " * Regenerate with scripts/gen_db_verify_indexes.php after changing an index in\n";
$out .= " * freeitsm.sql.\n";
$out .= " */\n";
$out .= "return [\n";
foreach ($rows as $r) {
    // $r = [table, name, type, cols]
    $out .= sprintf(
        "    ['%s', '%s', '%s', '%s'],\n",
        $r[0], $r[1], $r[2], $r[3]
    );
}
$out .= "];\n";

file_put_contents($outPath, $out);

// Report the breakdown, not just the total — a FULLTEXT index silently parsed as
// an ordinary KEY would still make the count look right.
$byType = array_count_values(array_column($rows, 2));
ksort($byType);
$parts = [];
foreach ($byType as $t => $n) $parts[] = "$n $t";
fwrite(STDERR, sprintf("Wrote %d indexes to %s (%s)\n", count($rows), $outPath, implode(', ', $parts)));
