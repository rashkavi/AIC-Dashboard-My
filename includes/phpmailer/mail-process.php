<?php
//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
//require 'vendor/autoload.php';

require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

function sendMail($to, $toName, $subject, $body, $ccList = [])
{
    $mail = new PHPMailer(true);

    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host       = 'smtp.titan.email';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'no-reply@aiccampuslms.com';
    $mail->Password   = 'AicLMS#@!2026Campus';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('no-reply@aiccampuslms.com', Project::getBaseName());
    $mail->addAddress($to, $toName);

    // Add CC recipients (added-by user, counsellor, etc.)
    foreach ($ccList as $ccEmail => $ccName) {
        if (!empty($ccEmail)) {
            $mail->addCC($ccEmail, $ccName ?: '');
        }
    }

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    if (!$mail->Send()) {
        echo "Error";
    } else {
        return (true);
    }
}

function sendBulkMail(array $recipients, string $subject, string $body, int $batchSize = 50): int
{
    // Normalise to email => name, drop invalid and duplicate addresses
    $list = [];
    foreach ($recipients as $key => $value) {
        $email = is_int($key) ? trim((string)$value) : trim((string)$key);
        $name  = is_int($key) ? '' : (string)$value;
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $list[strtolower($email)] = [$email, $name];
        }
    }

    if (empty($list)) {
        return 0;
    }

    $sentCount = 0;

    foreach (array_chunk($list, $batchSize) as $batch) {
        $mail = new PHPMailer(true);
        try {
            $mail->SMTPDebug  = 0;
            $mail->isSMTP();
            $mail->Host       = 'smtp.titan.email';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'no-reply@aiccampuslms.com';
            $mail->Password   = 'AicLMS#@!2026Campus';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('no-reply@aiccampuslms.com', Project::getBaseName());
            $mail->addAddress('no-reply@aiccampuslms.com', Project::getBaseName());

            foreach ($batch as [$email, $name]) {
                $mail->addBCC($email, $name);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $body)));

            $mail->send();
            $sentCount += count($batch);
        } catch (Exception $e) {
            error_log('sendBulkMail batch failed: ' . $mail->ErrorInfo);
            // continue with the next batch
        }
    }

    return $sentCount;
}
