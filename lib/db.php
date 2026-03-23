<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $path = BASE_PATH . '/data/activitypub.sqlite';
    $dir  = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec("PRAGMA journal_mode = WAL");
    $pdo->exec("PRAGMA foreign_keys = ON");
    $pdo->exec("PRAGMA synchronous = NORMAL");

    db_migrate($pdo);

    return $pdo;
}

function db_migrate(PDO $pdo): void {
    $migDir = BASE_PATH . '/migrations';
    $files  = glob($migDir . '/*.sql') ?: [];
    sort($files);

    // On a brand-new DB the settings table doesn't exist yet — run 0000 first
    // without trying to track it, then create the tracking entry afterwards.
    $settingsExists = (bool)$pdo->query(
        "SELECT name FROM sqlite_master WHERE type='table' AND name='settings'"
    )->fetch();

    if (!$settingsExists) {
        // Fresh install: run 0000 to create all tables including settings
        $init = $migDir . '/0000_initial_schema.sql';
        if (file_exists($init)) {
            $pdo->exec(file_get_contents($init));
        }
        $settingsExists = true;
    }

    foreach ($files as $file) {
        $name = basename($file, '.sql');
        $key  = 'migration_' . $name;

        $row = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $row->execute([$key]);
        if ($row->fetch()) continue; // already applied

        try {
            $pdo->exec(file_get_contents($file));
        } catch (PDOException) {
            // Tolerate "duplicate column" errors from schema drift
        }

        $pdo->prepare("INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)")
            ->execute([$key, date('Y-m-d H:i:s')]);
    }
}

function db_get(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_all(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_run(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_last_id(): int {
    return (int)db()->lastInsertId();
}

function db_setting(string $key, ?string $default = null): ?string {
    $row = db_get("SELECT value FROM settings WHERE key = ?", [$key]);
    return $row ? $row['value'] : $default;
}

function db_set_setting(string $key, string $value): void {
    db_run(
        "INSERT INTO settings(key,value) VALUES(?,?)
         ON CONFLICT(key) DO UPDATE SET value=excluded.value",
        [$key, $value]
    );
}

function get_account_by_username(string $username): ?array {
    return db_get(
        "SELECT * FROM accounts WHERE username = ? COLLATE NOCASE",
        [$username]
    );
}

function get_account_by_id(int $id): ?array {
    return db_get("SELECT * FROM accounts WHERE id = ?", [$id]);
}

function get_all_accounts(): array {
    return db_all("SELECT * FROM accounts ORDER BY username ASC");
}
