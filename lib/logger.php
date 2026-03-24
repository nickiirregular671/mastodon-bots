<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function log_activity(
    ?int   $accountId,
    string $direction,
    string $activityType,
    string $activityJson,
    string $remoteActor = '',
    string $targetInbox = '',
    string $status      = 'pending'
): int {
    db_run(
        "INSERT INTO activities_log
            (account_id, direction, activity_type, activity_json, remote_actor, target_inbox, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$accountId, $direction, $activityType, $activityJson, $remoteActor, $targetInbox, $status]
    );

    $id = db_last_id();
    $lastClear = (int)(db_setting('log_last_clear') ?? 0);
    if (time() - $lastClear >= 86400) log_auto_clear();
    return $id;
}

function update_log_status(int $logId, string $status, string $error = ''): void {
    db_run(
        "UPDATE activities_log
         SET status = ?, error = ?, delivered_at = datetime('now')
         WHERE id = ?",
        [$status, $error, $logId]
    );
}

function log_auto_clear(): void {
    db_set_setting('log_last_clear', (string)time());

    // Time-based retention
    $retentionDays = (int)(db_setting('log_retention_days') ?? 0);
    if ($retentionDays > 0) {
        db_run(
            "DELETE FROM activities_log WHERE created_at < datetime('now', ?)",
            ["-{$retentionDays} days"]
        );
    }

    // Row-count cap
    $maxRows = (int)(db_setting('max_log_rows') ?? 10000);
    $count   = db_get("SELECT COUNT(*) as c FROM activities_log");
    if ((int)($count['c'] ?? 0) <= $maxRows) return;

    db_run(
        "DELETE FROM activities_log WHERE id NOT IN (
             SELECT id FROM activities_log ORDER BY id DESC LIMIT ?
         )",
        [$maxRows]
    );
}

function log_clear_all(?int $accountId = null): int {
    if ($accountId !== null) {
        $stmt = db_run("DELETE FROM activities_log WHERE account_id = ?", [$accountId]);
    } else {
        $stmt = db_run("DELETE FROM activities_log");
    }
    return $stmt->rowCount();
}

function log_get_recent(int $limit = 100, ?int $accountId = null, string $direction = '', string $eventType = ''): array {
    $where  = [];
    $params = [];

    if ($accountId !== null) {
        $where[]  = "account_id = ?";
        $params[] = $accountId;
    }
    if (!empty($direction)) {
        $where[]  = "direction = ?";
        $params[] = $direction;
    }
    if (!empty($eventType)) {
        $where[]  = "activity_type = ?";
        $params[] = $eventType;
    }

    $sql = "SELECT l.*, a.username
            FROM activities_log l
            LEFT JOIN accounts a ON a.id = l.account_id";
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql    .= " ORDER BY l.id DESC LIMIT ?";
    $params[] = $limit;

    return db_all($sql, $params);
}
