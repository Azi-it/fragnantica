<?php
// a small SMTP client: sends one email with an HTML body and optional attachments.
// used by send_mail() in includes/mail.php with the Mailtrap settings from includes/settings.php.
//
// the conversation with the server looks like this:
//   EHLO -> STARTTLS -> EHLO -> AUTH LOGIN -> MAIL FROM -> RCPT TO -> DATA -> (the email) -> . -> QUIT

// build the full email (headers + body) in MIME format.
// $attachments: list of array('name' => 'receipt.pdf', 'type' => 'application/pdf', 'data' => raw bytes)
function mime_message($to, $subject, $html, $attachments = array())
{
    $domain = substr(strrchr(MAIL_FROM, '@'), 1) ?: 'localhost';
    $mixed = 'mixed_' . bin2hex(random_bytes(12));
    $alt = 'alt_' . bin2hex(random_bytes(12));

    // a plain-text copy for email programs that do not show HTML
    $text = trim(html_entity_decode(strip_tags(str_replace(array('<br>', '</p>', '</tr>', '</h2>', '</h3>', '</div>'), "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n\s*\n+/", "\n\n", $text);

    $headers = array(
        'Date: ' . date('r'),
        'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $mixed . '"',
    );

    $body = "--{$mixed}\r\n"
        . "Content-Type: multipart/alternative; boundary=\"{$alt}\"\r\n\r\n"
        . "--{$alt}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text)) . "\r\n"
        . "--{$alt}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "\r\n"
        . "--{$alt}--\r\n";

    foreach ($attachments as $file) {
        $name = str_replace(array('"', "\r", "\n"), '', $file['name']);
        $body .= "--{$mixed}\r\n"
            . "Content-Type: {$file['type']}; name=\"{$name}\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n"
            . chunk_split(base64_encode($file['data'])) . "\r\n";
    }
    $body .= "--{$mixed}--\r\n";

    return implode("\r\n", $headers) . "\r\n\r\n" . $body;
}

// read one reply from the server (a reply can span several lines: "250-..." then "250 ...")
// and check that its code is one we expect. returns the reply text.
function smtp_expect($socket, $codes)
{
    $reply = '';
    while (($line = fgets($socket, 1024)) !== false) {
        $reply .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    $code = (int)substr($reply, 0, 3);
    if (!in_array($code, (array)$codes, true)) {
        throw new RuntimeException('SMTP server said: ' . trim($reply ?: 'nothing (connection closed)'));
    }
    return $reply;
}

function smtp_command($socket, $command, $codes)
{
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $codes);
}

// send a ready-made MIME message. throws RuntimeException if anything goes wrong.
function smtp_send($to, $message)
{
    $prefix = SMTP_ENCRYPTION === 'ssl' ? 'ssl://' : 'tcp://';
    $context = stream_context_create(array('ssl' => array(
        'verify_peer' => true,
        'verify_peer_name' => true,
        'peer_name' => SMTP_HOST,
    )));
    $socket = @stream_socket_client($prefix . SMTP_HOST . ':' . (int)SMTP_PORT, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        throw new RuntimeException("Cannot connect to " . SMTP_HOST . ":" . SMTP_PORT . " ({$errstr})");
    }
    stream_set_timeout($socket, 20);

    try {
        smtp_expect($socket, 220);
        $hostname = gethostname() ?: 'localhost';
        smtp_command($socket, "EHLO {$hostname}", 250);

        if (SMTP_ENCRYPTION === 'tls') {
            smtp_command($socket, 'STARTTLS', 220);
            $methods = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $methods |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }
            if (!stream_socket_enable_crypto($socket, true, $methods)) {
                throw new RuntimeException('Could not start an encrypted (TLS) connection');
            }
            smtp_command($socket, "EHLO {$hostname}", 250);
        }

        if (SMTP_USERNAME !== '') {
            smtp_command($socket, 'AUTH LOGIN', 334);
            smtp_command($socket, base64_encode(SMTP_USERNAME), 334);
            smtp_command($socket, base64_encode(SMTP_PASSWORD), 235);
        }

        smtp_command($socket, 'MAIL FROM:<' . MAIL_FROM . '>', 250);
        smtp_command($socket, "RCPT TO:<{$to}>", array(250, 251));
        smtp_command($socket, 'DATA', 354);

        // a line that starts with "." would end the email early, so double it ("dot stuffing")
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($socket, $message . "\r\n.\r\n");
        smtp_expect($socket, 250);

        // the email is accepted at this point, so a server that hangs up early is fine
        try {
            smtp_command($socket, 'QUIT', 221);
        } catch (RuntimeException $e) {
        }
    } finally {
        fclose($socket);
    }
}
