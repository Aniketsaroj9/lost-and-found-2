<?php
declare(strict_types=1);

/**
 * Helper to send emails and log them to a file for debugging/localhost.
 */
/**
 * Helper to send emails using SMTP sockets (no local mail server required).
 * Logs to file for verification.
 */
function lf_send_email(string $to, string $subject, string $message): bool
{
    // Log file setup
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    $logFile = $logDir . '/email.log';
    
    // 1. Prepare Content
    $timestamp = date('Y-m-d H:i:s');
    $logHead = "[$timestamp] [SMTP] To: $to | Subject: $subject";
    
    // 2. SMTP Logic
    try {
        if (SMTP_USER === 'YOUR_EMAIL@gmail.com') {
            throw new Exception("SMTP credentials not configured in config.php");
        }

        $socket = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 15);
        if (!$socket) throw new Exception("Could not connect to SMTP host: $errstr");

        $response = server_parse($socket, "220");
        
        fwrite($socket, "EHLO " . SMTP_HOST . "\r\n");
        server_parse($socket, "250");

        fwrite($socket, "STARTTLS\r\n");
        server_parse($socket, "220");

        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        fwrite($socket, "EHLO " . SMTP_HOST . "\r\n");
        server_parse($socket, "250");

        fwrite($socket, "AUTH LOGIN\r\n");
        server_parse($socket, "334");

        fwrite($socket, base64_encode(SMTP_USER) . "\r\n");
        server_parse($socket, "334");

        // Strip spaces from App Password if present
        $cleanPass = str_replace(' ', '', SMTP_PASS);
        fwrite($socket, base64_encode($cleanPass) . "\r\n");
        server_parse($socket, "235");

        fwrite($socket, "MAIL FROM: <" . SMTP_USER . ">\r\n");
        server_parse($socket, "250");

        fwrite($socket, "RCPT TO: <" . $to . ">\r\n");
        server_parse($socket, "250");

        fwrite($socket, "DATA\r\n");
        server_parse($socket, "354");

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/plain; charset=utf-8\r\n";
        $headers .= "From: Lost & Found <" . SMTP_FROM . ">\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";

        fwrite($socket, "$headers\r\n$message\r\n.\r\n");
        server_parse($socket, "250");

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        // Success Log
        $logEntry = "$logHead - SENT OK\n" . str_repeat("-", 50) . "\n$message\n" . str_repeat("=", 50) . "\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        return true;

    } catch (Exception $e) {
        // Error Log
        $errorMsg = $e->getMessage();
        $logEntry = "$logHead - FAILED: $errorMsg\n" . str_repeat("=", 50) . "\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        return false;
    }
}

function server_parse($socket, $response) {
    $server_response = '';
    while (substr($server_response, 3, 1) != ' ') {
        if (!($server_response = fgets($socket, 256))) {
            throw new Exception("Error while fetching server response codes.");
        }
    }
    if (substr($server_response, 0, 3) != $response) {
        throw new Exception("Unable to send email: " . $server_response);
    }
    return $server_response;
}
