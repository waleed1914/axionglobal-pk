<?php
/* =========================================================
   Minimal SMTP client — no Composer, no dependencies.

   Speaks enough SMTP to authenticate and send one HTML mail.
   Supports STARTTLS (587) and implicit TLS (465), AUTH LOGIN
   and AUTH PLAIN.
   ========================================================= */

declare(strict_types=1);

class SmtpException extends RuntimeException {}

final class Smtp
{
    /** @var resource|null */
    private $sock = null;
    private array $log = [];

    /* The timeout applies to the connect and to every read that follows.
       Two mails are sent per inquiry, so it must stay well under
       max_execution_time (30s by default) — otherwise an unreachable
       mail server kills the request instead of failing cleanly. */
    public function __construct(
        private string $host,
        private int $port,
        private string $secure,     // 'tls' | 'ssl' | ''
        private string $user,
        private string $pass,
        private int $timeout = 8
    ) {}

    public function log(): array { return $this->log; }

    /**
     * @param array{0:string,1:string} $from  [address, name]
     * @param array<array{0:string,1:string}> $to
     */
    public function send(array $from, array $to, string $subject, string $htmlBody, string $replyTo = ''): void
    {
        if ($to === []) {
            throw new SmtpException('No recipients given.');
        }

        /* connect() inside the try: authentication can fail after the
           socket is already open, and that socket must still be closed. */
        try {
            $this->connect();

            $this->cmd('MAIL FROM:<' . $from[0] . '>', [250]);

            foreach ($to as $rcpt) {
                $this->cmd('RCPT TO:<' . $rcpt[0] . '>', [250, 251]);
            }

            $this->cmd('DATA', [354]);
            $this->write($this->buildMessage($from, $to, $subject, $htmlBody, $replyTo));
            $this->expect([250]);

            $this->cmd('QUIT', [221], true);
        } finally {
            $this->close();
        }
    }

    /* ---------------- connection ---------------- */

    private function connect(): void
    {
        $transport = ($this->secure === 'ssl') ? 'ssl://' : 'tcp://';

        $ctx = stream_context_create([
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true],
        ]);

        $errno = 0;
        $errstr = '';
        $sock = @stream_socket_client(
            $transport . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $ctx
        );

        if ($sock === false) {
            throw new SmtpException("Cannot reach {$this->host}:{$this->port} — {$errstr} ({$errno})");
        }

        $this->sock = $sock;
        stream_set_timeout($this->sock, $this->timeout);

        $this->expect([220]);

        $ehlo = $this->clientName();
        $this->cmd('EHLO ' . $ehlo, [250]);

        if ($this->secure === 'tls') {
            $this->cmd('STARTTLS', [220]);

            $ok = @stream_socket_enable_crypto(
                $this->sock,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );
            if ($ok !== true) {
                throw new SmtpException('STARTTLS negotiation failed.');
            }

            /* Must re-issue EHLO on the encrypted channel. */
            $this->cmd('EHLO ' . $ehlo, [250]);
        }

        if ($this->user !== '') {
            $this->authenticate();
        }
    }

    private function authenticate(): void
    {
        try {
            $this->cmd('AUTH LOGIN', [334]);
            $this->cmd(base64_encode($this->user), [334]);
            $this->cmd(base64_encode($this->pass), [235]);
        } catch (SmtpException $e) {
            /* Some servers only offer PLAIN. */
            $plain = base64_encode("\0" . $this->user . "\0" . $this->pass);
            $this->cmd('AUTH PLAIN ' . $plain, [235]);
        }
    }

    private function close(): void
    {
        if (is_resource($this->sock)) {
            @fclose($this->sock);
        }
        $this->sock = null;
    }

    private function clientName(): string
    {
        $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
        /* EHLO must be a domain literal, not an IP without brackets. */
        return filter_var($host, FILTER_VALIDATE_IP) ? '[' . $host . ']' : $host;
    }

    /* ---------------- protocol ---------------- */

    private function cmd(string $line, array $expect, bool $ignoreFailure = false): string
    {
        $this->write($line . "\r\n");
        try {
            return $this->expect($expect);
        } catch (SmtpException $e) {
            if ($ignoreFailure) return '';
            throw $e;
        }
    }

    private function write(string $data): void
    {
        if (!is_resource($this->sock)) {
            throw new SmtpException('Connection closed.');
        }
        if (@fwrite($this->sock, $data) === false) {
            throw new SmtpException('Failed writing to SMTP socket.');
        }
    }

    private function expect(array $codes): string
    {
        $response = '';

        while (true) {
            $line = @fgets($this->sock, 1024);

            if ($line === false) {
                $meta = is_resource($this->sock) ? stream_get_meta_data($this->sock) : [];
                if (!empty($meta['timed_out'])) {
                    throw new SmtpException('SMTP server timed out.');
                }
                throw new SmtpException('SMTP connection dropped.');
            }

            $response .= $line;
            $this->log[] = rtrim($line);

            /* Multi-line replies look like "250-…"; the last is "250 …". */
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $codes, true)) {
            throw new SmtpException('SMTP error: ' . trim($response));
        }

        return $response;
    }

    /* ---------------- message ---------------- */

    private function buildMessage(array $from, array $to, string $subject, string $html, string $replyTo): string
    {
        $toHeader = implode(', ', array_map(
            fn(array $r): string => $this->addressHeader($r[0], $r[1]),
            $to
        ));

        $boundaryDomain = substr(strrchr($from[0], '@') ?: '@localhost', 1);

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->addressHeader($from[0], $from[1]),
            'To: ' . $toHeader,
            'Subject: ' . $this->encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $boundaryDomain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: AXION Global Site',
        ];

        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $this->addressHeader($replyTo, '');
        }

        /* Normalise to CRLF, then dot-stuff so a line that is just
           "." doesn't terminate DATA early. */
        $body = preg_replace('/\r\n|\r|\n/', "\r\n", $html) ?? '';
        $body = preg_replace('/^\./m', '..', $body) ?? '';

        return implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n";
    }

    private function addressHeader(string $address, string $name): string
    {
        if ($name === '') {
            return '<' . $address . '>';
        }
        return $this->encodeHeader($name) . ' <' . $address . '>';
    }

    private function encodeHeader(string $text): string
    {
        /* Plain ASCII needs no encoding. */
        if (preg_match('/^[\x20-\x7E]*$/', $text)) {
            return $text;
        }
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }
}
