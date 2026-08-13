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

    /* Each statement is fully fetched before the next one runs. Leaving
       results unfetched breaks on MySQL with emulated prepares off
       ("Cannot execute queries while other unbuffered queries are
       active"), which is exactly how db.php configures PDO. */
    $countSince = static function (string $since) use ($pdo): int {
        $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM leads WHERE created_at >= ?');
        $stmt->execute([$since]);
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return (int) ($row['c'] ?? 0);
    };

    $total = (int) ($pdo->query('SELECT COUNT(*) AS c FROM leads')->fetch()['c'] ?? 0);
    $today = $countSince(date('Y-m-d') . ' 00:00:00');
    $week  = $countSince(date('Y-m-d H:i:s', strtotime('-7 days')));

    $failed = (int) ($pdo->query(
        "SELECT COUNT(*) AS c FROM leads WHERE mail_customer = 'failed' OR mail_admin = 'failed'"
    )->fetch()['c'] ?? 0);

    return [
        'total'  => $total,
        'today'  => $today,
        'week'   => $week,
        'failed' => $failed,
    ];
}
