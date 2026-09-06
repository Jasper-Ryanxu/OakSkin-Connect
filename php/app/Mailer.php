<?php
/**
 * Mailer - 邮件发送
 * 参照 Blessing Skin / PHPMailer 风格编写。
 * 支持三种通道：socket SMTP → curl SMTP → PHP mail() 自动回退。
 */
declare(strict_types=1);

namespace App;

class Mailer
{
    private static function cfg(string $key, string $default = ''): string
    {
        return (string) Config::get('mail.' . $key, $default);
    }

    /**
     * 发送 HTML 邮件
     * 通道优先级：socket SMTP → curl SMTP → PHP mail()
     * 只要有一条通道成功即返回 true。
     */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        $host   = self::cfg('host');
        $port   = (int) self::cfg('port', '465');
        $user   = self::cfg('username');
        $pass   = self::cfg('password');
        $enc    = self::cfg('encryption', 'ssl');
        $from   = self::cfg('from_address', $user);
        // 如果 from_address 不是合法邮箱，回退到 SMTP 用户名
        if ($from && strpos($from, '@') === false) {
            Log::warning("Mailer: from_address 不是合法邮箱，已回退到 SMTP 用户名 ({$user})");
            $from = $user;
        }
        $name   = self::cfg('from_name', 'OakSkin Connect');
        $domain = 'localhost';
        if ($from) {
            $at = strpos($from, '@');
            if ($at !== false) $domain = substr($from, $at + 1);
        }

        $mailAvailable = function_exists('mail');

        // 通道 1：socket SMTP（stream_socket_client）
        if ($host && $user) {
            $ok = self::sendViaSocket($to, $subject, $htmlBody, $host, $port, $user, $pass, $enc, $from, $name, $domain);
            if ($ok) return true;
        }

        // 通道 2：curl SMTP（如果可用）
        if ($host && $user && function_exists('curl_init') && defined('CURLOPT_RCPT_TO')) {
            $ok = self::sendViaCurl($to, $subject, $htmlBody, $host, $port, $user, $pass, $enc, $from, $name);
            if ($ok) return true;
        }

        // 通道 3：PHP mail()
        if ($mailAvailable) {
            $ok = self::sendViaMail($to, $subject, $htmlBody, $from, $name);
            if ($ok) return true;
        }

        Log::error('Mailer: 所有通道均失败');
        return false;
    }

    /**
     * 发送验证码邮件
     */
    public static function sendVerifyCode(string $to, string $code): bool
    {
        $siteName = Config::get('site.name', 'OakSkin Connect');
        $subject = "{$siteName} - 邮箱验证码";
        $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f5fa;font-family:'Segoe UI',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:30px 15px;">
<table width="540" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 30px rgba(0,0,0,0.08);">
<tr><td style="padding:40px 40px 20px;text-align:center;background:linear-gradient(135deg,#6c5ce7,#a29bfe);">
<h1 style="color:#fff;margin:0;font-size:22px;">{$siteName}</h1>
<p style="color:rgba(255,255,255,0.8);margin:8px 0 0;font-size:14px;">邮箱验证码</p>
</td></tr>
<tr><td style="padding:40px 40px 30px;">
<p style="margin:0 0 20px;font-size:15px;color:#333;">你好！</p>
<p style="margin:0 0 20px;font-size:15px;color:#333;">请使用以下验证码完成注册：</p>
<div style="text-align:center;margin:30px 0;padding:20px;background:#f0f0ff;border-radius:8px;border:1px dashed #6c5ce7;">
<span style="font-size:36px;font-weight:800;letter-spacing:8px;color:#6c5ce7;font-family:monospace;">{$code}</span>
</div>
<p style="margin:0;font-size:13px;color:#999;">验证码有效期为 10 分钟，请勿泄露给他人。</p>
<p style="margin:20px 0 0;font-size:13px;color:#999;">如果这不是你本人的操作，请忽略此邮件。</p>
</td></tr>
<tr><td style="padding:20px 40px;text-align:center;border-top:1px solid #eee;">
<p style="margin:0;font-size:12px;color:#aaa;">{$siteName} &mdash; Minecraft 皮肤站</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
HTML;
        return self::send($to, $subject, $body);
    }

    // ========== 通道 1：Socket SMTP ==========

    private static function sendViaSocket(string $to, string $subject, string $body, string $host, int $port, string $user, string $pass, string $enc, string $from, string $fromName, string $domain): bool
    {
        // 连接
        $socket = self::sockConnect($host, $port, $enc);
        if (!$socket) return false;

        // 读 banner
        $line = self::sockRead($socket);
        if (!self::sockExpect($line, 220)) { fclose($socket); return false; }

        // EHLO/HELO（多主机名回退）
        $hostnames = array_unique(array_filter([$domain, gethostname(), 'localhost']));
        $greeted = false;
        foreach ($hostnames as $hn) {
            // 先 EHLO
            fwrite($socket, "EHLO {$hn}\r\n");
            $line = self::sockRead($socket);
            if (self::sockExpect($line, 250, false)) { $greeted = true; break; }
            // 再 HELO
            fwrite($socket, "HELO {$hn}\r\n");
            $line = self::sockRead($socket);
            if (self::sockExpect($line, 250, false)) { $greeted = true; break; }
        }
        if (!$greeted) { fclose($socket); return false; }

        // STARTTLS
        if ($enc === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $line = self::sockRead($socket);
            if (!self::sockExpect($line, 220)) { fclose($socket); return false; }
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                Log::error('Mailer: TLS 握手失败');
                fclose($socket);
                return false;
            }
            // 重新 EHLO
            fwrite($socket, "EHLO {$domain}\r\n");
            self::sockRead($socket);
        }

        // AUTH LOGIN
        fwrite($socket, "AUTH LOGIN\r\n");
        $line = self::sockRead($socket);
        if (!self::sockExpect($line, 334)) { fclose($socket); return false; }

        fwrite($socket, base64_encode($user) . "\r\n");
        $line = self::sockRead($socket);
        if (!self::sockExpect($line, 334)) { fclose($socket); return false; }

        fwrite($socket, base64_encode($pass) . "\r\n");
        $line = self::sockRead($socket);
        if (!self::sockExpect($line, 235)) {
            Log::error('Mailer: SMTP 认证失败');
            fclose($socket);
            return false;
        }

        // MAIL FROM
        fwrite($socket, "MAIL FROM:<{$from}>\r\n");
        $line = self::sockRead($socket);
        if (!self::sockExpect($line, 250)) { fclose($socket); return false; }

        // RCPT TO
        fwrite($socket, "RCPT TO:<{$to}>\r\n");
        $line = self::sockRead($socket);
        if (!self::sockExpect($line, 250)) {
            Log::error("Mailer: 收件人地址无效 {$to}");
            fclose($socket);
            return false;
        }

        // DATA
        fwrite($socket, "DATA\r\n");
        $line = self::sockRead($socket);
        if (!self::sockExpect($line, 354)) { fclose($socket); return false; }

        $mid = time() . '.' . bin2hex(random_bytes(6)) . '@' . $domain;
        $headers = "From: {$fromName} <{$from}>\r\n"
            . "To: <{$to}>\r\n"
            . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Message-ID: <{$mid}>\r\n"
            . "X-Mailer: OakSkin Mailer\r\n";

        fwrite($socket, $headers . "\r\n" . chunk_split(base64_encode($body)) . "\r\n.\r\n");
        $line = self::sockRead($socket);
        if (!self::sockExpect($line, 250)) {
            Log::error('Mailer: DATA 写入失败 - ' . trim($line));
            fclose($socket);
            return false;
        }

        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        Log::info('Mailer: 通道 SMTP 已发送');
        return true;
    }

    private static function sockConnect(string $host, int $port, string $enc): mixed
    {
        $errno = 0; $errstr = '';
        $timeout = 15;

        if ($enc === 'ssl') {
            $remote = "ssl://{$host}:{$port}";
            $ctx = stream_context_create(['ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]]);
            $sock = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        } else {
            $remote = "{$host}:{$port}";
            $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        }

        if (!$sock) {
            Log::error("Mailer: 连接失败 {$remote} [{$errno}] {$errstr}");
            return false;
        }
        stream_set_timeout($sock, $timeout);
        return $sock;
    }

    private static function sockRead($sock): string
    {
        $resp = '';
        while (!feof($sock)) {
            $line = fgets($sock, 1024);
            if ($line === false) break;
            $resp .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $resp;
    }

    private static function sockExpect(string $resp, int $code, bool $log = true): bool
    {
        $ok = strlen($resp) >= 3 && (int) substr($resp, 0, 3) === $code;
        if (!$ok && $log) {
            Log::error("Mailer: 预期 {$code} 实际 " . trim($resp));
        }
        return $ok;
    }

    // ========== 通道 2：cURL SMTP ==========

    private static function sendViaCurl(string $to, string $subject, string $body, string $host, int $port, string $user, string $pass, string $enc, string $from, string $fromName): bool
    {
        $url = ($enc === 'ssl' ? 'smtps://' : 'smtp://') . "{$host}:{$port}";

        $domain = 'localhost';
        if ($from) {
            $at = strpos($from, '@');
            if ($at !== false) $domain = substr($from, $at + 1);
        }
        $mid = time() . '.' . bin2hex(random_bytes(6)) . '@' . $domain;
        $headers = "From: {$fromName} <{$from}>\r\n"
            . "To: <{$to}>\r\n"
            . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Message-ID: <{$mid}>\r\n"
            . "X-Mailer: OakSkin Mailer\r\n";

        $payload = $headers . "\r\n" . chunk_split(base64_encode($body));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_MAIL_FROM => $from,
            CURLOPT_RCPT_TO => [$to],
            CURLOPT_USERPWD => "{$user}:{$pass}",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: message/rfc822'],
        ]);

        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            Log::error("Mailer: curl SMTP 失败 [{$errno}] {$error}");
            return false;
        }
        Log::info('Mailer: 通道 curl 已发送');
        return true;
    }

    // ========== 通道 3：PHP mail() ==========

    private static function sendViaMail(string $to, string $subject, string $body, string $from, string $fromName): bool
    {
        $mid = time() . '.' . bin2hex(random_bytes(6)) . '@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $headers = "From: {$fromName} <{$from}>\r\n"
            . "Reply-To: {$from}\r\n"
            . "Return-Path: {$from}\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Message-ID: <{$mid}>\r\n"
            . "X-Mailer: OakSkin Mailer\r\n";

        $params = '-f ' . $from;
        $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', chunk_split(base64_encode($body)), $headers, $params);
        if ($ok) {
            Log::info('Mailer: 通道 mail() 已发送');
        } else {
            Log::error('Mailer: mail() 发送失败');
        }
        return $ok;
    }
}