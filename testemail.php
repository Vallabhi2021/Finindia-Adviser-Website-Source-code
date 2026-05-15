<?php 

// Create log file
$logFile = __DIR__ . '/test-email.log';
function log_test($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "<div style='margin:10px 0; padding:10px; background:#f0f0f0; border-left:3px solid #333;'>[$timestamp] $message</div>";
}

log_test("=== SMTP SEND EMAIL TEST ===");

$configs = [
    [
        'name' => 'Port 587 (TLS)',
        'host' => 'finindiaadvisors.com',
        'port' => 587,
        'username' => 'admin@finindiaadvisors.com',
        'password' => 'Cpanel@2020',
        'encryption' => 'tls'
    ],
    [
        'name' => 'Port 465 (SSL)',
        'host' => 'ssl://finindiaadvisors.com',
        'port' => 465,
        'username' => 'admin@finindiaadvisors.com',
        'password' => 'Cpanel@2020',
        'encryption' => 'ssl'
    ],
    [
        'name' => 'Port 25 (None)',
        'host' => 'finindiaadvisors.com',
        'port' => 25,
        'username' => 'admin@finindiaadvisors.com',
        'password' => 'Cpanel@2020',
        'encryption' => 'none'
    ]
];

$recipient = 'bkweb11@gmail.com';
$subject = 'Test Email - ' . date('Y-m-d H:i:s');
$body = "<h2>Test Email from FinIndia</h2>";
$body .= "<p>This is a test email sent via SMTP.</p>";
$body .= "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

log_test("Target recipient: $recipient\n");

$emailSent = false;

foreach ($configs as $config) {
    if ($emailSent) break;
    
    log_test("\n--- Trying: {$config['name']} ---");
    
    try {
        $host = $config['host'];
        $port = $config['port'];
        
        log_test("Connecting to $host:$port");
        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        
        if (!$socket) {
            log_test("✗ Connection failed: $errstr ($errno)");
            continue;
        }
        
        // Read greeting - handle multi-line
        $response = '';
        do {
            $line = fgets($socket, 512);
            $response .= $line;
        } while ($line && strlen($line) > 3 && $line[3] === '-');
        
        if (strpos($response, '220') !== 0) {
            log_test("✗ Invalid SMTP response");
            fclose($socket);
            continue;
        }
        
        log_test("✓ Server: " . trim($response));
        
        // Function to send command and read response - handle multi-line
        $sendCmd = function($cmd, $socket) {
            fwrite($socket, $cmd . "\r\n");
            $resp = '';
            do {
                $line = fgets($socket, 512);
                if (!$line) break;
                $resp .= $line;
            } while (strlen($line) > 3 && $line[3] === '-');
            return $resp;
        };
        
        // Send EHLO
        $resp = $sendCmd("EHLO finindiaadvisors.com", $socket);
        if (strpos($resp, '250') !== 0) {
            log_test("✗ EHLO failed: " . htmlspecialchars(substr($resp, 0, 50)));
            fclose($socket);
            continue;
        }
        log_test("✓ EHLO OK");
        
        // Handle encryption
        if ($config['encryption'] === 'tls') {
            log_test("Starting TLS...");
            $resp = $sendCmd("STARTTLS", $socket);
            if (strpos($resp, '220') !== 0) {
                log_test("✗ STARTTLS failed: " . htmlspecialchars(substr($resp, 0, 50)));
                fclose($socket);
                continue;
            }
            
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                log_test("✗ TLS encryption failed");
                fclose($socket);
                continue;
            }
            log_test("✓ TLS enabled");
            
            $resp = $sendCmd("EHLO finindiaadvisors.com", $socket);
        } elseif ($config['encryption'] === 'ssl') {
            log_test("Note: SSL mode - already encrypted on connection");
        }
        
        // Authenticate
        log_test("Authenticating...");
        $resp = $sendCmd("AUTH LOGIN", $socket);
        if (strpos($resp, '334') !== 0) {
            log_test("✗ AUTH LOGIN not supported: " . trim($resp));
            fclose($socket);
            continue;
        }
        
        $resp = $sendCmd(base64_encode($config['username']), $socket);
        if (strpos($resp, '334') !== 0) {
            log_test("✗ Username rejected");
            fclose($socket);
            continue;
        }
        
        $resp = $sendCmd(base64_encode($config['password']), $socket);
        if (strpos($resp, '235') !== 0) {
            log_test("✗ Authentication failed: " . trim($resp));
            fclose($socket);
            continue;
        }
        log_test("✓ Authentication successful");
        
        // Send mail
        log_test("Sending email...");
        $resp = $sendCmd("MAIL FROM:<{$config['username']}>", $socket);
        if (strpos($resp, '250') !== 0) {
            log_test("✗ MAIL FROM rejected");
            fclose($socket);
            continue;
        }
        
        $resp = $sendCmd("RCPT TO:<$recipient>", $socket);
        if (strpos($resp, '250') !== 0) {
            log_test("✗ RCPT TO rejected: " . trim($resp));
            fclose($socket);
            continue;
        }
        
        $resp = $sendCmd("DATA", $socket);
        if (strpos($resp, '354') !== 0) {
            log_test("✗ DATA command rejected");
            fclose($socket);
            continue;
        }
        
        // Build message
        $message = "From: {$config['username']}\r\n";
        $message .= "To: $recipient\r\n";
        $message .= "Subject: $subject\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-type: text/html; charset=UTF-8\r\n";
        $message .= "X-Mailer: FinIndia SMTP Handler\r\n\r\n";
        $message .= $body . "\r\n.";
        
        fwrite($socket, $message . "\r\n");
        $resp = fgets($socket, 512);
        
        if (strpos($resp, '250') !== 0) {
            log_test("✗ Server rejected message: " . trim($resp));
            fclose($socket);
            continue;
        }
        
        log_test("✓ Email accepted by server!");
        
        $sendCmd("QUIT", $socket);
        fclose($socket);
        
        $emailSent = true;
        log_test("\n=== ✓ EMAIL SENT SUCCESSFULLY ===");
        log_test("Configuration: {$config['name']}");
        log_test("To: $recipient");
        
        echo "<div style='margin:20px 0; padding:15px; background:#d4edda; border:1px solid #28a745; color:#155724;'>";
        echo "<h3>✓ EMAIL SENT SUCCESSFULLY</h3>";
        echo "<p><strong>Configuration that worked:</strong></p>";
        echo "<ul>";
        echo "<li>Host: {$config['host']}</li>";
        echo "<li>Port: {$config['port']}</li>";
        echo "<li>Encryption: {$config['encryption']}</li>";
        echo "<li>Username: {$config['username']}</li>";
        echo "</ul>";
        echo "<p>Email sent to: <strong>$recipient</strong></p>";
        echo "<p>Check your inbox and spam folder.</p>";
        echo "</div>";
        
    } catch (Exception $e) {
        log_test("✗ Exception: " . $e->getMessage());
    }
}

if (!$emailSent) {
    log_test("\n=== ✗ EMAIL FAILED ===");
    log_test("Could not send email with any configuration");
    
    echo "<div style='margin:20px 0; padding:15px; background:#f8d7da; border:1px solid #f5c6cb; color:#721c24;'>";
    echo "<h3>✗ EMAIL FAILED</h3>";
    echo "<p>Could not connect or authenticate to any SMTP server.</p>";
    echo "<p><strong>Actions to try:</strong></p>";
    echo "<ul>";
    echo "<li>Check cPanel for correct SMTP hostname</li>";
    echo "<li>Verify username and password are correct</li>";
    echo "<li>Ensure firewall allows outgoing SMTP connections</li>";
    echo "<li>Contact hosting support about SMTP relay</li>";
    echo "</ul>";
    echo "</div>";
}

log_test("\n=== TEST COMPLETE ===");

?>

<style>
body { 
    font-family: 'Courier New', monospace; 
    margin: 20px; 
    background: #f5f5f5;
}
h1 { color: #333; background: #ddd; padding: 10px; }
div { font-size: 13px; }
ul { margin: 10px 0; padding-left: 20px; }
li { margin: 5px 0; }
</style>

<h1>📧 SMTP Email Send Test</h1>
<p><strong>Attempting to send email to bkweb11@gmail.com...</strong></p>
<p>Check test-email.log for full output</p>