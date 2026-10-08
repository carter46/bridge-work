<?php
// sendmail.php - Form submission handler with PHPMailer

// Include PHPMailer library (adjusted for PHPMailer-7.0.1 folder)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer-7.0.1/src/Exception.php';
require 'PHPMailer-7.0.1/src/PHPMailer.php';
require 'PHPMailer-7.0.1/src/SMTP.php';

// ===== CONFIGURATION =====
// Email where you want to receive applications
$recipient_email = "info@hubjobplatform.com";

// SMTP Settings (configure these with your hosting provider's SMTP settings)
// TODO: Update these values with your actual SMTP configuration
$smtp_host = "smtp.yourdomain.com"; // e.g., smtp.gmail.com, smtp.yourhost.com, mail.yourdomain.com
$smtp_port = 587; // Usually 587 for TLS or 465 for SSL
$smtp_username = "your-smtp-username@yourdomain.com"; // Your SMTP username
$smtp_password = "your-smtp-password"; // Your SMTP password
$smtp_encryption = "tls"; // "tls" or "ssl"

// From email (should match your domain or SMTP username)
$from_email = "noreply@hubjobplatform.com"; // Change to match your domain
$from_name = "Hubjob Platform";

// ===== PROCESS FORM =====
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form data
    $fullname = isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : '';
    $phone = isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '';
    $email = isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '';
    $age = isset($_POST['age']) ? htmlspecialchars($_POST['age']) : '';
    $nationality = isset($_POST['nationality']) ? htmlspecialchars($_POST['nationality']) : '';
    $nationality_code = isset($_POST['nationality_code']) ? htmlspecialchars($_POST['nationality_code']) : '';
    $work_type = isset($_POST['work_type']) ? htmlspecialchars($_POST['work_type']) : '';
    $role = isset($_POST['role']) ? htmlspecialchars(trim($_POST['role'])) : '';
    
    // Set JSON content type header
    header('Content-Type: application/json');
    
    // Validate required fields
    if (empty($fullname) || empty($phone) || empty($email) || empty($age) || empty($nationality) || empty($work_type)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
        exit;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
        exit;
    }
    
    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_username;
        $mail->Password   = $smtp_password;
        $mail->SMTPSecure = $smtp_encryption;
        $mail->Port       = $smtp_port;
        $mail->CharSet    = 'UTF-8';
        
        // Recipients
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($recipient_email); // Where you receive applications
        $mail->addReplyTo($email, $fullname); // Reply-to the applicant's email
        
        // Attachments (CV file)
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] == UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['cv']['tmp_name'];
            $file_name = $_FILES['cv']['name'];
            $file_size = $_FILES['cv']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Allowed file extensions
            $allowed_extensions = ['pdf', 'doc', 'docx', 'rtf', 'txt'];
            
            // Check file size (2MB max) and file type
            if ($file_size <= 2097152 && in_array($file_ext, $allowed_extensions)) {
                $mail->addAttachment($file_tmp, $file_name);
            }
        }
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = "New Job Application - " . $fullname . ($role !== '' ? " (" . html_entity_decode($role, ENT_QUOTES, 'UTF-8') . ")" : "");

        $role_field = $role !== '' ?
            "<div class='field'><div class='label'>Position of Interest:</div><div class='value'>{$role}</div></div>" : "";
        
        $cv_attachment = (isset($_FILES['cv']) && $_FILES['cv']['error'] == UPLOAD_ERR_OK) ? 
            "<div class='field'><div class='label'>CV Attached:</div><div class='value'>" . htmlspecialchars($_FILES['cv']['name']) . "</div></div>" : "";
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #070235; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #555; }
                .value { color: #222; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Job Application Received</h2>
                </div>
                <div class='content'>
                    <div class='field'>
                        <div class='label'>Full Name:</div>
                        <div class='value'>{$fullname}</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Email:</div>
                        <div class='value'>{$email}</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Phone:</div>
                        <div class='value'>{$phone}</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Age:</div>
                        <div class='value'>{$age}</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Nationality:</div>
                        <div class='value'>{$nationality} ({$nationality_code})</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Work Type:</div>
                        <div class='value'>{$work_type}</div>
                    </div>
                    {$role_field}
                    {$cv_attachment}
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $cv_text = (isset($_FILES['cv']) && $_FILES['cv']['error'] == UPLOAD_ERR_OK) ? 
            "\nCV Attached: " . htmlspecialchars($_FILES['cv']['name']) : "";
        $role_text = $role !== '' ? "\nPosition of Interest: {$role}" : "";
        
        $mail->AltBody = "
New Job Application Received

Full Name: {$fullname}
Email: {$email}
Phone: {$phone}
Age: {$age}
Nationality: {$nationality} ({$nationality_code})
Work Type: {$work_type}{$role_text}{$cv_text}
        ";
        
        // Send email
        $mail->send();
        
        // Success response
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Thank you! Your application has been submitted successfully. We will contact you shortly.'
        ]);
        
    } catch (Exception $e) {
        // Error response
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Sorry, there was an error sending your application. Please try again later or contact us directly.'
        ]);
        
        // Log error (optional - for debugging)
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
    }
    
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
}
?>
