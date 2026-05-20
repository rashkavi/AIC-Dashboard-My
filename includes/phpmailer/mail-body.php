<?php
function renderMailBody($verificationLink, $status)
{
    $currentYear = date('Y');

    // Prepare content based on status
    $baseName = Project::getBaseName();
    $baseURL = Project::getBaseURL();
    $instituteLogo = Project::getInstituteLogo();

    if ($status == 1) {
        $subjectLine = "Complete Your {$baseName} System Registration";
        $message = <<<TEXT
    <p>You have been invited to join {$baseName} System by a system administrator. 
    To get started, please verify your email address and set your password by clicking the button below:</p>
    TEXT;
        $buttonText = "Verify Email & Setup Password";
    } else {
        $subjectLine = "Password Reset Request";
        $message = <<<TEXT
    <p>You recently requested to reset your {$baseName} System password. 
    Please click the button below to proceed with creating a new password:</p>
    <p><strong>Note:</strong> This password reset link will expire in 15 minutes. 
    If you didn't request a password reset, please secure your account by contacting our support team immediately.</p>
    TEXT;
        $buttonText = "Reset Your Password";
    }

    $image = <<<TEXT
    <div class="header">
        <img src="{$baseURL}/assets/imgs/{$instituteLogo}" alt="{$baseName} Logo" class="logo">
    </div>
    TEXT;

    // Now render the email body
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$baseName} - $subjectLine</title>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
    font-family: 'Inter', sans-serif;
    line-height: 1.6;
    color: #1F2937;
    margin: 0;
    padding: 0;
    background-color: #F9FAFB;
}

.container {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
}

.header {
    text-align: center;
    padding: 30px 0;
}

.logo {
    max-width: 180px;
    height: auto;
}

/* Main Card */
.card {
    background-color: #ffffff;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
    padding: 40px;
    border-top: 5px solid #21409B;
}

h1 {
    color: #1F2937;
    font-size: 22px;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 20px;
}

p {
    margin-bottom: 18px;
    font-size: 16px;
    color: #4B5563;
}

/* Button */
.button {
    display: inline-block;
    background-color: #21409B;
    color: #1F2937 !important;
    text-decoration: none;
    padding: 14px 28px;
    font-weight: 600;
    border-radius: 6px;
    margin: 20px 0;
    text-align: center;
    transition: all 0.2s ease-in-out;
}

.button:hover {
    background-color: #18327F;
    transform: translateY(-1px);
}

/* Divider */
.divider {
    height: 1px;
    background-color: #E5E7EB;
    margin: 30px 0;
}

/* Footer */
.footer {
    text-align: center;
    padding: 20px 0;
    color: #6B7280;
    font-size: 14px;
}
    </style>
</head>
<body>
    <div class="container">
        $image
        <div class="card">
            <h1>$subjectLine</h1>
            $message
            <center>
                <a href="$verificationLink" class="button">$buttonText</a>
            </center>
            <div class="divider"></div>
            <p>Best regards,<br>The {$baseName} Team</p>
        </div>
        <div class="footer">
        <p style="margin: 10px 0; font-size: 12px; color: #718096; text-align: center;">
                    &copy;$currentYear ENLIXO Uni. All Rights Reserved |
                    Powered by
                    <a href="https://enviiz.com" target="_blank" style="text-decoration: none;">
                        <img src="https://dashboard.aiccampuslms.com/assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares (PVT) LTD" style="width: 35px; height: auto; vertical-align: middle;">
                    </a>
                </p>
            <p>
                <a href="https://enviiz.com/privacy-policy" style="text-decoration: none;font-size: 12px; color: #718096; text-align: center;">Privacy Policy</a> | 
                <a href="https://enviiz.com/terms-conditions" style="text-decoration: none;font-size: 12px; color: #718096; text-align: center;">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;

    return $html;
}
