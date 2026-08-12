<?php
/* =========================================================
   Database — PDO. SQLite by default, MySQL if DB_DSN says so.
   Schema is created on first use, so there is no install step.
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $isSqlite = str_starts_with(DB_DSN, 'sqlite:');

    if ($isSqlite) {
        $path = substr(DB_DSN, strlen('sqlite:'));
        $dir  = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create storage directory: ' . $dir);
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('Storage directory is not writable: ' . $dir);
        }
    }

    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    if ($isSqlite) {
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    migrate($pdo, $isSqlite);

    return $pdo;
}

function migrate(PDO $pdo, bool $isSqlite): void
{
    $autoInc = $isSqlite
        ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
        : 'INT AUTO_INCREMENT PRIMARY KEY';

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS leads (
            id            {$autoInc},
            name          VARCHAR(120)  NOT NULL,
            company       VARCHAR(160)  NULL,
            email         VARCHAR(190)  NOT NULL,
            phone         VARCHAR(60)   NOT NULL,
            service       VARCHAR(120)  NULL,
            message       TEXT          NOT NULL,
            page          VARCHAR(190)  NULL,
            ip            VARCHAR(45)   NULL,
            user_agent    VARCHAR(255)  NULL,
            mail_customer VARCHAR(20)   NOT NULL DEFAULT 'pending',
            mail_admin    VARCHAR(20)   NOT NULL DEFAULT 'pending',
            mail_error    TEXT          NULL,
            created_at    DATETIME      NOT NULL
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_leads_created ON leads (created_at)");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(64) PRIMARY KEY,
            v TEXT NOT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id         {$autoInc},
            ip         VARCHAR(45) NOT NULL,
            created_at DATETIME    NOT NULL
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attempts_ip ON login_attempts (ip, created_at)");
}

function setting_get(string $key): ?string
{
    $stmt = db()->prepare('SELECT v FROM settings WHERE k = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    return $row === false ? null : (string) $row['v'];
}

function setting_set(string $key, string $value): void
{
    /* Portable upsert: delete then insert, inside a transaction. */
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM settings WHERE k = ?')->execute([$key]);
        $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?)')->execute([$key, $value]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
