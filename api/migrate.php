<?php
/*
 * CLI migration runner.
 * Applies SQL files in db/migrations exactly once.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be executed from CLI.\n");
    exit(1);
}

function migrationFiles(string $dir): array
{
    $files = glob($dir . '/*.sql') ?: [];
    sort($files, SORT_NATURAL);
    return $files;
}

function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';

    foreach (preg_split('/\R/', $sql) as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
            continue;
        }

        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), ';')) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
        }
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

$pdo = db();
$migrationsDir = __DIR__ . '/../db/migrations';

if (!is_dir($migrationsDir)) {
    fwrite(STDERR, "Migration directory not found: {$migrationsDir}\n");
    exit(1);
}

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB');

$appliedRows = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_fill_keys($appliedRows, true);

$pendingCount = 0;
foreach (migrationFiles($migrationsDir) as $file) {
    $version = basename($file, '.sql');

    if (isset($applied[$version])) {
        echo "SKIP  {$version}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Unable to read migration: {$file}\n");
        exit(1);
    }

    $statements = splitSqlStatements($sql);
    if (count($statements) === 0) {
        echo "SKIP  {$version} (empty)\n";
        continue;
    }

    echo "APPLY {$version}\n";
    $pdo->beginTransaction();

    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $stmt->execute([$version]);

        $pdo->commit();
        $pendingCount++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        fwrite(STDERR, "FAILED {$version}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Done. Applied {$pendingCount} migration(s).\n";
