<?php
/**
 * Verify schema.sql matches the live DB by building a scratch DB from schema.sql
 * and comparing columns + indexes table by table.
 */
require_once 'env_loader.php';
loadEnv(__DIR__ . '/.env');

$host = env("DB_HOST", "localhost");
$user = env("DB_USER", "root");
$pass = env("DB_PASS", "");
$liveDb = env("DB_NAME", "manga_reader");
$scratchDb = "schema_check_tmp";

// Connect without dbname
$root = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Clean slate
$root->exec("DROP DATABASE IF EXISTS `$scratchDb`");
$root->exec("CREATE DATABASE `$scratchDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

$pdo = new PDO("mysql:host=$host;dbname=$scratchDb;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Read + split schema.sql
$sql = file_get_contents(__DIR__ . '/schema.sql');
// Strip line comments starting with --
$sql = preg_replace('/^\s*--.*$/m', '', $sql);
// Split on semicolon at end of line
$statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)));
foreach ($statements as $stmt) {
    if ($stmt === '') continue;
    try {
        $pdo->exec($stmt);
    } catch (Exception $e) {
        echo "FAILED STATEMENT:\n" . substr($stmt, 0, 200) . "\nERROR: " . $e->getMessage() . "\n\n";
    }
}

function getColumns(PDO $pdo, string $db): array {
    $stmt = $pdo->prepare("
        SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, ORDINAL_POSITION
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = :db
        ORDER BY TABLE_NAME, ORDINAL_POSITION
    ");
    $stmt->execute([":db" => $db]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['TABLE_NAME']][$r['COLUMN_NAME']] = $r;
    }
    return $out;
}

function getIndexes(PDO $pdo, string $db): array {
    $stmt = $pdo->prepare("
        SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, NON_UNIQUE, SEQ_IN_INDEX
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = :db
        ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
    ");
    $stmt->execute([":db" => $db]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['TABLE_NAME']][$r['INDEX_NAME']][] = $r['COLUMN_NAME'] . ':' . $r['NON_UNIQUE'];
    }
    return $out;
}

$liveCols = getColumns($root, $liveDb);
$scrCols = getColumns($root, $scratchDb);
$liveIdx = getIndexes($root, $liveDb);
$scrIdx = getIndexes($root, $scratchDb);

$allTables = array_unique(array_merge(array_keys($liveCols), array_keys($scrCols)));
sort($allTables);

$problems = 0;

foreach ($allTables as $t) {
    $inLive = isset($liveCols[$t]);
    $inScr = isset($scrCols[$t]);
    if ($inLive !== $inScr) {
        echo "TABLE PRESENCE MISMATCH: $t -> live=" . ($inLive ? 'yes' : 'no') . " schema=" . ($inScr ? 'yes' : 'no') . "\n";
        $problems++;
        continue;
    }

    // Columns
    $lc = $liveCols[$t];
    $sc = $scrCols[$t];
    $allCols = array_unique(array_merge(array_keys($lc), array_keys($sc)));
    sort($allCols);
    foreach ($allCols as $c) {
        if (!isset($lc[$c])) { echo "COLUMN ONLY IN schema.sql: $t.$c\n"; $problems++; continue; }
        if (!isset($sc[$c])) { echo "COLUMN ONLY IN live DB: $t.$c\n"; $problems++; continue; }
        foreach (['COLUMN_TYPE','IS_NULLABLE','COLUMN_DEFAULT','EXTRA'] as $attr) {
            if ((string)$lc[$c][$attr] !== (string)$sc[$c][$attr]) {
                echo "COLUMN DIFF: $t.$c $attr live='" . var_export($lc[$c][$attr], true) . "' schema='" . var_export($sc[$c][$attr], true) . "'\n";
                $problems++;
            }
        }
    }

    // Indexes
    $li = $liveIdx[$t] ?? [];
    $si = $scrIdx[$t] ?? [];
    foreach (array_unique(array_merge(array_keys($li), array_keys($si))) as $ix) {
        $liv = $li[$ix] ?? null;
        $scv = $si[$ix] ?? null;
        if ($liv !== $scv) {
            echo "INDEX DIFF: $t.$ix live=" . json_encode($liv) . " schema=" . json_encode($scv) . "\n";
            $problems++;
        }
    }
}

echo "\n" . ($problems === 0 ? "OK: schema.sql matches live DB exactly." : "PROBLEMS FOUND: $problems") . "\n";

$root->exec("DROP DATABASE IF EXISTS `$scratchDb`");
