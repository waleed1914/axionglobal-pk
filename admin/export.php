<?php
/* =========================================================
   Lead export.

   Writes CSV with a UTF-8 BOM and CRLF line endings, which
   is what Excel expects — it opens with correct columns and
   correct accented characters on both Windows and Mac.
   Honours the same search filter as the table.
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_leads.php';

require_admin();

$search = (string) ($_GET['q'] ?? '');
$rows   = leads_all($search);

$filename = 'axion-leads-' . date('Y-m-d-His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'wb');

/* BOM — without it Excel mis-reads UTF-8. */
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'ID',
    'Received',
    'Name',
    'Company',
    'Email',
    'Phone',
    'Service',
    'Requirement',
    'Submitted From',
    'Customer Email',
    'Office Email',
    'IP Address',
], ',', '"', '');

foreach ($rows as $r) {
    fputcsv($out, [
        (string) $r['id'],
        (string) $r['created_at'],
        (string) $r['name'],
        (string) ($r['company'] ?? ''),
        (string) $r['email'],
        /* Leading apostrophe stops Excel turning +92… into a formula
           or mangling it into scientific notation. */
        "'" . (string) $r['phone'],
        (string) ($r['service'] ?? ''),
        (string) $r['message'],
        (string) ($r['page'] ?? ''),
        (string) $r['mail_customer'],
        (string) $r['mail_admin'],
        (string) ($r['ip'] ?? ''),
    ], ',', '"', '');
}

fclose($out);
exit;
