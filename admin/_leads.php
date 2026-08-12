<?php
/* =========================================================
   Shared lead filtering, so the table and the export always
   return exactly the same rows.
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

/**
 * @return array{0:string,1:array} [whereClause, params]
 */
function leads_filter(string $search): array
{
    $search = trim($search);

    if ($search === '') {
        return ['', []];
    }

    $like = '%' . $search . '%';

    $where = ' WHERE (name LIKE ? OR company LIKE ? OR email LIKE ? OR phone LIKE ? OR service LIKE ? OR message LIKE ?)';

    return [$where, [$like, $like, $like, $like, $like, $like]];
}

function leads_count(string $search): int
{
    [$where, $params] = leads_filter($search);

    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM leads' . $where);
    $stmt->execute($params);

    return (int) ($stmt->fetch()['c'] ?? 0);
}

function leads_page(string $search, int $limit, int $offset): array
{
    [$where, $params] = leads_filter($search);

    $sql = 'SELECT * FROM leads' . $where . ' ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function leads_all(string $search): array
{
    [$where, $params] = leads_filter($search);

    $stmt = db()->prepare('SELECT * FROM leads' . $where . ' ORDER BY id DESC');
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function leads_stats(): array
{
    $pdo = db();

    $total = (int) ($pdo->query('SELECT COUNT(*) AS c FROM leads')->fetch()['c'] ?? 0);

    $today = $pdo->prepare('SELECT COUNT(*) AS c FROM leads WHERE created_at >= ?');
    $today->execute([date('Y-m-d') . ' 00:00:00']);

    $week = $pdo->prepare('SELECT COUNT(*) AS c FROM leads WHERE created_at >= ?');
    $week->execute([date('Y-m-d H:i:s', strtotime('-7 days'))]);

    $failed = (int) ($pdo->query("SELECT COUNT(*) AS c FROM leads WHERE mail_customer = 'failed' OR mail_admin = 'failed'")->fetch()['c'] ?? 0);

    return [
        'total'  => $total,
        'today'  => (int) ($today->fetch()['c'] ?? 0),
        'week'   => (int) ($week->fetch()['c'] ?? 0),
        'failed' => $failed,
    ];
}
