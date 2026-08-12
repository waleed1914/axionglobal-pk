<?php
/* =========================================================
   Inquiry endpoint — receives the modal form, stores the
   lead, then sends both emails.

   The lead is written to the database BEFORE any mail is
   attempted, so a broken SMTP server can never lose an
   inquiry. Mail outcome is recorded per lead.
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'Method not allowed.');
}

/* ---------------------------------------------------------
   Read input — accepts JSON or normal form encoding
   --------------------------------------------------------- */

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = $_POST;

if (str_contains($contentType, 'application/json')) {
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

function field(array $src, string $key, int $max): string
{
    $value = isset($src[$key]) && is_scalar($src[$key]) ? (string) $src[$key] : '';
    $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');
    return mb_substr($value, 0, $max);
}

/* Honeypot — real users never fill this; bots usually do. */
if (field($input, 'website', 100) !== '') {
    /* Pretend it worked so the bot doesn't retry. */
    echo json_encode(['ok' => true]);
    exit;
}

$name    = field($input, 'name', 120);
$company = field($input, 'company', 160);
$email   = field($input, 'email', 190);
$phone   = field($input, 'phone', 60);
$service = field($input, 'service', 120);
$message = field($input, 'message', 4000);
$page    = field($input, 'page', 190);

/* ---------------------------------------------------------
   Validate
   --------------------------------------------------------- */

$errors = [];

if ($name === '')                                     $errors[] = 'name';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'email';
if ($phone === '' || !preg_match('/[0-9]{6,}/', preg_replace('/\D/', '', $phone) ?? '')) $errors[] = 'phone';
if (mb_strlen($message) < 5)                          $errors[] = 'message';

if ($errors !== []) {
    fail(422, 'Please check the highlighted fields.');
}

/* Header-injection guard — no newlines may reach a mail header. */
foreach ([$name, $email, $service] as $headerish) {
    if (preg_match('/[\r\n]/', $headerish)) {
        fail(400, 'Invalid input.');
    }
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

/* ---------------------------------------------------------
   Rate limit
   --------------------------------------------------------- */

try {
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS c FROM leads WHERE ip = ? AND created_at >= ?"
    );
    $stmt->execute([$ip, date('Y-m-d H:i:s', time() - 3600)]);
    $recent = (int) ($stmt->fetch()['c'] ?? 0);

    if ($recent >= RATE_LIMIT_PER_HOUR) {
        fail(429, 'Too many inquiries from this connection. Please try again later, or contact us on WhatsApp.');
    }
} catch (Throwable $e) {
    error_log('[axion] rate-limit check failed: ' . $e->getMessage());
}

/* ---------------------------------------------------------
   Store the lead first — never lose it to a mail failure
   --------------------------------------------------------- */

$createdAt = date('Y-m-d H:i:s');

try {
    $stmt = db()->prepare(
        "INSERT INTO leads (name, company, email, phone, service, message, page, ip, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$name, $company, $email, $phone, $service, $message, $page, $ip, $ua, $createdAt]);
    $leadId = (int) db()->lastInsertId();
} catch (Throwable $e) {
    error_log('[axion] lead insert failed: ' . $e->getMessage());
    fail(500, 'We could not record your inquiry. Please contact us on WhatsApp.');
}

$lead = compact('name', 'company', 'email', 'phone', 'service', 'message', 'page', 'ip')
      + ['created_at' => $createdAt];

/* ---------------------------------------------------------
   Send mail — failures are logged against the lead, not
   shown to the customer, because we already have their data
   --------------------------------------------------------- */

$status = ['customer' => 'pending', 'admin' => 'pending'];
$errorText = [];

try {
    send_customer_ack($lead);
    $status['customer'] = 'sent';
} catch (Throwable $e) {
    $status['customer'] = 'failed';
    $errorText[] = 'customer: ' . $e->getMessage();
    error_log('[axion] customer mail failed for lead ' . $leadId . ': ' . $e->getMessage());
}

try {
    send_admin_notification($lead);
    $status['admin'] = 'sent';
} catch (Throwable $e) {
    $status['admin'] = 'failed';
    $errorText[] = 'admin: ' . $e->getMessage();
    error_log('[axion] admin mail failed for lead ' . $leadId . ': ' . $e->getMessage());
}

try {
    db()->prepare("UPDATE leads SET mail_customer = ?, mail_admin = ?, mail_error = ? WHERE id = ?")
        ->execute([
            $status['customer'],
            $status['admin'],
            $errorText === [] ? null : implode(' | ', $errorText),
            $leadId,
        ]);
} catch (Throwable $e) {
    error_log('[axion] mail-status update failed: ' . $e->getMessage());
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
