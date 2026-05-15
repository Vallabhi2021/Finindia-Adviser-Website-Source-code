<?php
// sendmailer.php
// Universal mail handler with SMTP authentication

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// SMTP Configuration with credentials
$smtpConfig = [
    'host' => 'finindiaadvisors.com',
    'port' => 25,
    'username' => 'admin@finindiaadvisors.com',
    'password' => 'Cpanel@2020',
    'encryption' => 'none'
];

// $recipient = 'bkweb11@gmail.com';
$recipient = 'gaurav.sondhi@vallabhicapital.com';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedResumeExt = ['pdf','doc','docx'];

// Error logging function
function log_mail_error($message) {
    $logFile = __DIR__ . '/mail-errors.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Helpers
function safe($v){ return trim(filter_var($v, FILTER_SANITIZE_STRING)); }

// SMTP Sender Class
class SMTPSender {
    private $host, $port, $username, $password, $socket;
    
    public function __construct($host, $port, $username, $password) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->socket = null;
    }
    
    public function send($to, $subject, $body, $replyToEmail = null, $attachmentPath = null) {
        try {
            // Connect
            $this->socket = @fsockopen($this->host, $this->port, $errno, $errstr, 10);
            if (!$this->socket) {
                throw new Exception("Cannot connect to SMTP server: $errstr");
            }
            
            // Read greeting - handle multi-line responses
            $response = '';
            do {
                $line = fgets($this->socket, 512);
                if (!$line) break;
                $response .= $line;
            } while ($line && strlen($line) > 3 && $line[3] === '-');
            
            if (strpos($response, '220') !== 0) {
                throw new Exception("Invalid SMTP server response");
            }
            
            // Send EHLO
            $resp = $this->sendCommand("EHLO finindiaadvisors.com");
            if (strpos($resp, '250') !== 0) {
                throw new Exception("EHLO failed: " . trim($resp));
            }
            
            // Authenticate
            $resp = $this->sendCommand("AUTH LOGIN");
            if (strpos($resp, '334') !== 0) {
                throw new Exception("AUTH LOGIN not supported");
            }
            
            $resp = $this->sendCommand(base64_encode($this->username));
            if (strpos($resp, '334') !== 0) {
                throw new Exception("Username rejected");
            }
            
            $resp = $this->sendCommand(base64_encode($this->password));
            if (strpos($resp, '235') !== 0) {
                throw new Exception("Authentication failed: " . trim($resp));
            }
            
            // Send mail - ALWAYS from the SMTP username to avoid relay restrictions
            $resp = $this->sendCommand("MAIL FROM:<{$this->username}>");
            if (strpos($resp, '250') !== 0) {
                throw new Exception("MAIL FROM rejected: " . trim($resp));
            }
            
            $resp = $this->sendCommand("RCPT TO:<$to>");
            if (strpos($resp, '250') !== 0) {
                throw new Exception("RCPT TO rejected: " . trim($resp));
            }
            
            $resp = $this->sendCommand("DATA");
            if (strpos($resp, '354') !== 0) {
                throw new Exception("DATA command rejected");
            }
            
            // Build message with attachment support
            $boundary = '----' . md5(uniqid(time()));
            $message = "From: {$this->username}\r\n";
            $message .= "To: $to\r\n";
            $message .= "Subject: $subject\r\n";
            if ($replyToEmail) {
                $message .= "Reply-To: $replyToEmail\r\n";
            }
            $message .= "MIME-Version: 1.0\r\n";
            
            if ($attachmentPath && file_exists($attachmentPath)) {
                $message .= "Content-type: multipart/mixed; boundary=\"$boundary\"\r\n";
                $message .= "X-Mailer: FinIndia SMTP Handler\r\n\r\n";
                
                // Text part
                $message .= "--$boundary\r\n";
                $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $message .= $body . "\r\n\r\n";
                
                // Attachment part
                $filename = basename($attachmentPath);
                $fileContent = file_get_contents($attachmentPath);
                $fileContentEncoded = chunk_split(base64_encode($fileContent));
                
                $message .= "--$boundary\r\n";
                $message .= "Content-Type: application/octet-stream; name=\"$filename\"\r\n";
                $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $message .= $fileContentEncoded . "\r\n";
                $message .= "--$boundary--";
            } else {
                $message .= "Content-type: text/plain; charset=UTF-8\r\n";
                $message .= "X-Mailer: FinIndia SMTP Handler\r\n\r\n";
                $message .= $body;
            }
            
            $message .= "\r\n.";
            
            fwrite($this->socket, $message . "\r\n");
            $resp = fgets($this->socket, 512);
            
            if (strpos($resp, '250') !== 0) {
                throw new Exception("Server rejected message: " . trim($resp));
            }
            
            // Quit
            $this->sendCommand("QUIT");
            @fclose($this->socket);
            
            log_mail_error("Email sent successfully to $to from {$this->username}");
            return true;
            
        } catch (Exception $e) {
            if ($this->socket) @fclose($this->socket);
            log_mail_error("SMTP Error in send(): " . $e->getMessage());
            throw $e;
        }
    }
    
    private function sendCommand($command) {
        if (!$this->socket) {
            throw new Exception("Socket not connected");
        }
        
        fwrite($this->socket, $command . "\r\n");
        $resp = '';
        $attempts = 0;
        
        do {
            $line = fgets($this->socket, 512);
            if (!$line) {
                break;
            }
            $resp .= $line;
            $attempts++;
            
            if ($attempts > 100) {
                throw new Exception("Timeout reading response");
            }
        } while (strlen($line) > 3 && $line[3] === '-');
        
        return $resp;
    }
}

function send_html_mail($to, $subject, $htmlBody, $replyToEmail = null, $attachmentPath = null, $additionalHeaders = ''){
    global $smtpConfig;
    
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid recipient email address');
    }
    
    if (!$subject || empty($subject)) {
        throw new Exception('Email subject cannot be empty');
    }
    
    if (!$htmlBody || empty($htmlBody)) {
        throw new Exception('Email body cannot be empty');
    }
    
    // Remove dangerous characters from subject
    $subject = str_replace(["\r", "\n", "%0a", "%0d"], '', $subject);
    
    try {
        log_mail_error("Sending email to: $to, Subject: $subject" . ($attachmentPath ? ", Attachment: " . basename($attachmentPath) : ''));
        
        $smtp = new SMTPSender(
            $smtpConfig['host'],
            $smtpConfig['port'],
            $smtpConfig['username'],
            $smtpConfig['password']
        );
        
        $result = $smtp->send($to, $subject, $htmlBody, $replyToEmail, $attachmentPath);
        log_mail_error("Email sent successfully" . ($attachmentPath ? " with attachment" : ''));
        return $result;
        
    } catch (Exception $e) {
        log_mail_error("SMTP Send failed: " . $e->getMessage());
        throw new Exception('Email sending failed. Our team has been notified. Error: ' . $e->getMessage());
    }
}

$form_type = isset($_POST['form_type']) ? $_POST['form_type'] : '';

if (!$form_type) {
    // header('Location: thankyou.php');
    exit;
}

try {
    if ($form_type === 'contact' || $form_type === 'footer') {
        $name = safe($_POST['name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $message = safe($_POST['message'] ?? '');
        $service = safe($_POST['service'] ?? '');
        $organization = safe($_POST['organization'] ?? '');

        if (!$name || !$email || !preg_match('/^[0-9]{10}$/', $phone)) {
            throw new Exception('Validation failed - please fill all required fields correctly.');
        }

        $subject = ($form_type === 'contact') ? "Website Contact: $name" : "Request Call Back: $name";

        $html = "╔════════════════════════════════════════╗\r\n";
        $html .= "║      FinIndia Advisors                  ║\r\n";
        $html .= "║   New Form Submission Received          ║\r\n";
        $html .= "╚════════════════════════════════════════╝\r\n\r\n";
        
        $html .= "SUBMISSION TYPE:\r\n";
        $html .= "─ " . $subject . "\r\n\r\n";
        
        $html .= "CONTACT DETAILS:\r\n";
        $html .= "─ Name: " . htmlspecialchars($name) . "\r\n";
        $html .= "─ Email: " . htmlspecialchars($email) . "\r\n";
        $html .= "─ Phone: " . htmlspecialchars($phone) . "\r\n";
        
        if ($organization) {
            $html .= "\r\nORGANIZATION:\r\n";
            $html .= "─ " . htmlspecialchars($organization) . "\r\n";
        }
        
        if ($service) {
            $html .= "\r\nSERVICE INTEREST:\r\n";
            $html .= "─ " . htmlspecialchars($service) . "\r\n";
        }
        
        if ($message) {
            $html .= "\r\nMESSAGE:\r\n";
            $html .= "─ " . htmlspecialchars($message) . "\r\n";
        }
        
        $html .= "\r\n════════════════════════════════════════\r\n";
        $html .= "© 2025 FinIndia Advisors\r\n";
        $html .= "This is an automated message. Do not reply.\r\n";

        send_html_mail($recipient, $subject, $html, $email);

        header('Location: thankyou.php');
        exit;

    } elseif ($form_type === 'career') {
        $fullName = safe($_POST['fullName'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $city = safe($_POST['city'] ?? '');
        $experience = safe($_POST['experience'] ?? '');
        $qualification = safe($_POST['qualification'] ?? '');
        $ctc = safe($_POST['ctc'] ?? '');
        $coverLetter = safe($_POST['coverLetter'] ?? '');
        $job = safe($_POST['job'] ?? '');

        if (!$fullName || !$email || !preg_match('/^[0-9]{10}$/', $phone) || !$city || !$experience || !$qualification) {
            throw new Exception('Validation failed - please fill all required fields correctly.');
        }

        $subject = "Job Application: " . ($job ? $job : 'Application') . " - $fullName";

        $html = "╔════════════════════════════════════════╗\r\n";
        $html .= "║      FinIndia Advisors                  ║\r\n";
        $html .= "║      Job Application Received           ║\r\n";
        $html .= "╚════════════════════════════════════════╝\r\n\r\n";
        
        $html .= "JOB POSITION:\r\n";
        $html .= "─ " . htmlspecialchars($job) . "\r\n\r\n";
        
        $html .= "APPLICANT INFORMATION:\r\n";
        $html .= "─ Full Name: " . htmlspecialchars($fullName) . "\r\n";
        $html .= "─ Email: " . htmlspecialchars($email) . "\r\n";
        $html .= "─ Phone: " . htmlspecialchars($phone) . "\r\n";
        $html .= "─ Location: " . htmlspecialchars($city) . "\r\n\r\n";
        
        $html .= "PROFESSIONAL DETAILS:\r\n";
        $html .= "─ Experience: " . htmlspecialchars($experience) . "\r\n";
        $html .= "─ Qualification: " . htmlspecialchars($qualification) . "\r\n";
        $html .= "─ Expected CTC: " . htmlspecialchars($ctc) . "\r\n";

        $resumePath = null;
        
        // Handle resume upload
        if (isset($_FILES['resume']) && is_uploaded_file($_FILES['resume']['tmp_name'])) {
            $file = $_FILES['resume'];
            if ($file['size'] > $maxFileSize) throw new Exception('Resume file too large');
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedResumeExt)) throw new Exception('Invalid resume file type');

            $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            $newName = 'resume_' . time() . '_' . preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file['name']);
            $dest = $uploadDir . DIRECTORY_SEPARATOR . $newName;
            if (!move_uploaded_file($file['tmp_name'], $dest)) throw new Exception('Could not save resume');

            $resumePath = $dest;
            $html .= "\r\nRESUME:\r\n";
            $html .= "─ File: " . htmlspecialchars($file['name']) . "\r\n";
            $html .= "─ Status: Attached to Email\r\n";
        }
        
        if ($coverLetter) {
            $html .= "\r\nCOVER LETTER:\r\n";
            $html .= htmlspecialchars($coverLetter) . "\r\n";
        }
        
        $html .= "\r\n════════════════════════════════════════\r\n";
        $html .= "© 2025 FinIndia Advisors\r\n";
        $html .= "This is an automated message. Do not reply.\r\n";

        send_html_mail($recipient, $subject, $html, $email, $resumePath);

        header('Location: thankyou.php');
        exit;

    } else {
        throw new Exception('Unknown form type');
    }
} catch (Exception $ex) {
    // On error, return error message without redirecting
    http_response_code(400);
    die('Error: ' . htmlspecialchars($ex->getMessage()));
}
