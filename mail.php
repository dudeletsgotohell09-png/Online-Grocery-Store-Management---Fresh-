<?php
/**
 * includes/mail.php
 * Small wrapper around PHP's built-in mail() function.
 *
 * On a developer machine (XAMPP/WAMP) without an SMTP server, set
 * MAIL_LOG_ONLY = true in config.php — every email body will be written
 * to PHP's error_log so you can still read reset links and order
 * confirmations during testing.
 *
 * On a real server, configure php.ini's [mail function] (or use sendmail /
 * an SMTP relay), set MAIL_LOG_ONLY = false, and the messages will be sent.
 */

/**
 * Send an HTML email.
 *
 * @param string $to      Recipient email address.
 * @param string $subject Subject line (UTF-8 safe).
 * @param string $html    HTML body.
 * @param string|null $replyTo  Optional Reply-To address.
 * @return bool True if accepted for delivery (or logged), false on hard failure.
 */
function send_mail(string $to, string $subject, string $html, ?string $replyTo = null): bool {
    $from     = MAIL_FROM;
    $fromName = MAIL_FROM_NAME;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$from}>\r\n";
    if ($replyTo) {
        $headers .= "Reply-To: {$replyTo}\r\n";
    }
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    // Always log — useful even on production for debugging.
    $logLine = sprintf(
        "[mail] to=%s subject=%s body=%s",
        $to,
        $subject,
        preg_replace('/\s+/', ' ', strip_tags($html))
    );
    error_log($logLine);

    if (MAIL_LOG_ONLY) {
        // Pretend success; the body is in error_log.
        return true;
    }

    try {
        return mail($to, $encodedSubject, $html, $headers);
    } catch (Throwable $e) {
        error_log('[mail] send failed: ' . $e->getMessage());
        return false;
    }
}

/** Build a simple, on-brand HTML wrapper for plain content. */
function mail_template(string $title, string $bodyHtml): string {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
         . '<meta name="viewport" content="width=device-width,initial-scale=1.0">'
         . '<title>' . htmlspecialchars($title) . '</title></head>'
         . '<body style="margin:0;padding:0;background:#F5F6F2;font-family:system-ui,Arial,sans-serif;color:#1B211C;">'
         . '<div style="max-width:560px;margin:0 auto;padding:32px 24px;">'
         . '<div style="font-size:1.4rem;font-weight:800;color:#23553A;margin-bottom:16px;">'
         . '<span style="color:#EE8A22;">&#127807;</span> Fresh</div>'
         . '<h1 style="font-size:1.3rem;margin:0 0 14px;color:#163A26;">' . htmlspecialchars($title) . '</h1>'
         . '<div style="background:#FFFFFF;border:1px solid #E4E7E0;border-radius:12px;padding:24px;color:#1B211C;font-size:0.95rem;line-height:1.55;">'
         . $bodyHtml
         . '</div>'
         . '<p style="font-size:0.78rem;color:#8D968E;margin-top:18px;text-align:center;">'
         . 'Fresh Grocery &middot; CS12012 Web Development project</p>'
         . '</div></body></html>';
}
