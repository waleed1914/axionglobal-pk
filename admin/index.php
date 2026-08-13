<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_leads.php';

require_admin();

$search  = (string) ($_GET['q'] ?? '');
$perPage = 25;
$page    = max(1, (int) ($_GET['page'] ?? 1));

$total  = leads_count($search);
$pages  = max(1, (int) ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows  = leads_page($search, $perPage, $offset);
$stats = leads_stats();

function page_url(int $n, string $search): string
{
    $q = ['page' => $n];
    if ($search !== '') $q['q'] = $search;
    return '?' . http_build_query($q);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leads — AXION Global Admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="topbar">
  <div class="topbar__brand">AXION <span>GLOBAL</span> &nbsp;Admin</div>
  <div class="topbar__right">
    <a class="topbar__link" href="/home" target="_blank" rel="noopener">View website</a>
    <a class="topbar__link" href="logout.php">Sign out</a>
  </div>
</div>

<div class="wrap">

  <div class="stats">
    <div class="stat">
      <div class="stat__label">Total leads</div>
      <div class="stat__value"><?= (int) $stats['total'] ?></div>
    </div>
    <div class="stat">
      <div class="stat__label">Today</div>
      <div class="stat__value"><?= (int) $stats['today'] ?></div>
    </div>
    <div class="stat">
      <div class="stat__label">Last 7 days</div>
      <div class="stat__value"><?= (int) $stats['week'] ?></div>
    </div>
    <div class="stat">
      <div class="stat__label">Email failures</div>
      <div class="stat__value" style="<?= $stats['failed'] > 0 ? 'color:#C0392B' : '' ?>"><?= (int) $stats['failed'] ?></div>
    </div>
  </div>

  <div class="toolbar">
    <form class="search" method="get">
      <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search name, company, email, phone, service or message…">
      <button type="submit" class="btn btn--ghost">Search</button>
      <?php if ($search !== ''): ?>
        <a class="btn btn--ghost" href="index.php">Clear</a>
      <?php endif; ?>
    </form>

    <a class="btn" href="export.php<?= $search !== '' ? '?q=' . urlencode($search) : '' ?>">
      Download Excel (CSV)
    </a>
  </div>

  <div class="card">
    <?php if ($rows === []): ?>

      <div class="empty">
        <strong><?= $search !== '' ? 'No matching leads' : 'No leads yet' ?></strong>
        <?= $search !== ''
              ? 'Try a different search term.'
              : 'Inquiries submitted through the website will appear here.' ?>
      </div>

    <?php else: ?>

      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Contact</th>
              <th>Service</th>
              <th>Requirement</th>
              <th>Email sent</th>
              <th>Received</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td style="color:#8A8F99;"><?= (int) $r['id'] ?></td>

                <td>
                  <div class="cell-name"><?= h((string) $r['name']) ?></div>
                  <?php if (($r['company'] ?? '') !== ''): ?>
                    <div class="cell-sub"><?= h((string) $r['company']) ?></div>
                  <?php endif; ?>
                  <div class="cell-sub">
                    <a href="mailto:<?= h((string) $r['email']) ?>"><?= h((string) $r['email']) ?></a>
                  </div>
                  <div class="cell-sub">
                    <a href="tel:<?= h((string) $r['phone']) ?>"><?= h((string) $r['phone']) ?></a>
                  </div>
                </td>

                <td>
                  <span class="tag"><?= h(($r['service'] ?? '') !== '' ? (string) $r['service'] : 'General Inquiry') ?></span>
                </td>

                <td class="cell-msg"><?= h((string) $r['message']) ?></td>

                <td>
                  <div>
                    <span class="pill pill--<?= $r['mail_customer'] === 'sent' ? 'ok' : 'bad' ?>">
                      Customer
                    </span>
                  </div>
                  <div style="margin-top:5px;">
                    <span class="pill pill--<?= $r['mail_admin'] === 'sent' ? 'ok' : 'bad' ?>">
                      Office
                    </span>
                  </div>
                  <?php if (($r['mail_error'] ?? '') !== ''): ?>
                    <div class="cell-sub" title="<?= h((string) $r['mail_error']) ?>" style="color:#C0392B;cursor:help;">
                      error
                    </div>
                  <?php endif; ?>
                </td>

                <td class="cell-when">
                  <?= h(date('d M Y', strtotime((string) $r['created_at']))) ?><br>
                  <span style="color:#8A8F99;"><?= h(date('H:i', strtotime((string) $r['created_at']))) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pager">
      <div>
        Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?>
      </div>
      <div class="pager__links">
        <?php if ($page > 1): ?>
          <a href="<?= h(page_url($page - 1, $search)) ?>">Previous</a>
        <?php else: ?>
          <span>Previous</span>
        <?php endif; ?>

        <span>Page <?= $page ?> of <?= $pages ?></span>

        <?php if ($page < $pages): ?>
          <a href="<?= h(page_url($page + 1, $search)) ?>">Next</a>
        <?php else: ?>
          <span>Next</span>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

</body>
</html>
