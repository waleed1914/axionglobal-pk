<?php
/* =========================================================
   Builds and sends the two inquiry emails:
     1. Thank-you to the customer
     2. New-lead notification to the office
   ========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smtp.php';

/**
 * Builds an SMTP client and remembers it, so that when a send throws we
 * can still read the server conversation that led to the failure.
 */
function mailer(): Smtp
{
    return mailer_last(new Smtp(
        SMTP_HOST,
        SMTP_PORT,
        SMTP_SECURE,
        SMTP_USER,
        smtp_password()
    ));
}

/** Get, or set-and-get, the most recently used client. */
function mailer_last(?Smtp $set = null): ?Smtp
{
    static $last = null;

    if ($set !== null) {
        $last = $set;
    }

    return $last;
}

/**
 * The tail of the last SMTP conversation, for diagnosing a failed send —
 * the exception message alone rarely says why. Only server replies and
 * our own verbs are recorded; the password is never logged.
 */
function mailer_transcript(): string
{
    $client = mailer_last();

    if ($client === null) {
        return '';
    }

    $log = $client->log();

    return $log === [] ? '' : ' [SMTP: ' . implode(' / ', array_slice($log, -5)) . ']';
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------------------------------------------------------
   Shared shell so both mails look like the site
   --------------------------------------------------------- */

function mail_shell(string $heading, string $inner): string
{
    return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f4f6;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f6;padding:28px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:14px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">

  <tr><td style="background:#0B1B33;padding:26px 30px;">
    <div style="font-size:21px;font-weight:bold;color:#ffffff;letter-spacing:.5px;">
      AXION <span style="color:#E4C878;">GLOBAL</span>
    </div>
    <div style="font-size:10px;letter-spacing:2px;text-transform:uppercase;color:#C79A3D;padding-top:6px;">
      Global Supply &amp; Solutions
    </div>
  </td></tr>

  <tr><td style="height:3px;background:#C79A3D;"></td></tr>

  <tr><td style="padding:30px;">
    <h1 style="margin:0 0 18px;font-size:20px;color:#0B1B33;">' . e($heading) . '</h1>
    ' . $inner . '
  </td></tr>

  <tr><td style="background:#f7f6f2;padding:20px 30px;font-size:11px;line-height:1.7;color:#5C6270;">
    AXION Global &middot; Plot #4, Street 2, Tehsil Taxila, District Rawalpindi<br>
    <a href="tel:+923215797119" style="color:#5C6270;">+92 321 5797119</a> &middot;
    <a href="https://www.axionglobal-pk.com" style="color:#5C6270;">www.axionglobal-pk.com</a>
  </td></tr>

</table>
</td></tr></table>
</body></html>';
}

function detail_rows(array $lead): string
{
    $fields = [
        'Name'        => $lead['name'],
        'Company'     => $lead['company'] !== '' ? $lead['company'] : '—',
        'Email'       => $lead['email'],
        'Phone'       => $lead['phone'],
        'Service'     => $lead['service'] !== '' ? $lead['service'] : 'General Inquiry',
        'Submitted'   => $lead['created_at'],
    ];

    $rows = '';
    foreach ($fields as $label => $value) {
        $rows .= '<tr>
            <td style="padding:9px 0;font-size:12px;color:#8A8F99;width:110px;vertical-align:top;">' . e((string) $label) . '</td>
            <td style="padding:9px 0;font-size:13px;color:#101828;font-weight:bold;">' . e((string) $value) . '</td>
        </tr>';
    }

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>';
}

/* ---------------------------------------------------------
   1. Customer thank-you
   --------------------------------------------------------- */

function send_customer_ack(array $lead): void
{
    $service = $lead['service'] !== '' ? $lead['service'] : 'your inquiry';

    $inner = '
    <p style="margin:0 0 16px;font-size:14px;line-height:1.75;color:#5C6270;">
      Dear ' . e($lead['name']) . ',
    </p>
    <p style="margin:0 0 16px;font-size:14px;line-height:1.75;color:#5C6270;">
      Thank you for contacting AXION Global. We have received your inquiry regarding
      <strong style="color:#0B1B33;">' . e($service) . '</strong>, and our team will
      review your requirement and get back to you shortly.
    </p>

    <div style="background:#f7f6f2;border-left:3px solid #C79A3D;padding:16px 18px;margin:22px 0;">
      <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#C79A3D;font-weight:bold;padding-bottom:8px;">
        Your Requirement
      </div>
      <div style="font-size:13px;line-height:1.7;color:#101828;white-space:pre-wrap;">' . e($lead['message']) . '</div>
    </div>

    <p style="margin:0 0 22px;font-size:14px;line-height:1.75;color:#5C6270;">
      If your requirement is urgent, you are welcome to reach us directly on WhatsApp.
    </p>

    <a href="https://wa.me/923215797119"
       style="display:inline-block;background:#C79A3D;color:#0B1B33;font-size:13px;font-weight:bold;
              text-decoration:none;padding:13px 26px;border-radius:8px;">
      Chat with us on WhatsApp
    </a>

    <p style="margin:26px 0 0;font-size:13px;line-height:1.75;color:#5C6270;">
      Warm regards,<br><strong style="color:#0B1B33;">AXION Global</strong>
    </p>';

    mailer()->send(
        [MAIL_FROM, MAIL_FROM_NAME],
        [[$lead['email'], $lead['name']]],
        'We have received your inquiry — AXION Global',
        mail_shell('Thank you for your inquiry', $inner)
    );
}

/* ---------------------------------------------------------
   2. Office notification
   --------------------------------------------------------- */

function send_admin_notification(array $lead): void
{
    $service = $lead['service'] !== '' ? $lead['service'] : 'General Inquiry';

    $inner = '
    <p style="margin:0 0 20px;font-size:14px;line-height:1.75;color:#5C6270;">
      A new inquiry was submitted through the website.
    </p>

    ' . detail_rows($lead) . '

    <div style="background:#f7f6f2;border-left:3px solid #C79A3D;padding:16px 18px;margin:22px 0;">
      <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#C79A3D;font-weight:bold;padding-bottom:8px;">
        Requirement
      </div>
      <div style="font-size:13px;line-height:1.7;color:#101828;white-space:pre-wrap;">' . e($lead['message']) . '</div>
    </div>

    <p style="margin:0 0 6px;font-size:12px;color:#8A8F99;">
      Submitted from ' . e($lead['page']) . ' &middot; IP ' . e($lead['ip']) . '
    </p>

    <p style="margin:18px 0 0;font-size:13px;color:#5C6270;">
      Reply directly to this email to respond to ' . e($lead['name']) . '.
    </p>';

    mailer()->send(
        [MAIL_FROM, MAIL_FROM_NAME],
        [[MAIL_NOTIFY_TO, 'AXION Global']],
        'New inquiry: ' . $service . ' — ' . $lead['name'],
        mail_shell('New website inquiry', $inner),
        MAIL_REPLY_TO_CUSTOMER ? $lead['email'] : ''
    );
}
