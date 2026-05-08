<?php
function renderMailBody($verificationLink, $status)
{
    $currentYear = date('Y');
    $supportEmail = "infot@enviiz.com";
    $baseName = Project::getBaseName();
    $baseURL = Project::getBaseUrl();
    $instituteLogo = Project::getInstituteLogo();

    // Prepare content based on status
    if ($status == 1) {
        $subjectLine = "Complete Your {$baseName} System Registration";
        $message = <<<TEXT
 <p>Thank you for registering with the {$baseName} System. To complete your registration, please verify your email address and set your password by clicking the button below:</p>
                
TEXT;
        $buttonText = "Verify Email & Setup Password";
    } else {
        $subjectLine = "Password Reset Request";
        $message = <<<TEXT
<p>You recently requested to reset your Complete Your {$baseName} System Registration password. Please click the button below to proceed with creating a new password:</p>
<p><strong>Note:</strong>This password reset link will expire in 15 minutes. If you didn't request a password reset, please secure your account by contacting our support team immediately.</p>
TEXT;
        $buttonText = "Reset Your Password";
    }

    $image = <<<TEXT
    <div class="header">
        <img src="{$baseURL}/assets/imgs/{$instituteLogo}" alt="{$baseName} Logo" class="logo">
    </div>
    TEXT;

    // Professional email template
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$baseName - $subjectLine</title>
    <style type="text/css">
    /* Base Styles */
    body, html {
        margin: 0;
        padding: 0;
        font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.6;
        color: #2D3748;
        background-color: #F7F9FC;
    }

    /* Typography */
    h1, h2, h3 {
        color: #1A2A46;
        margin-top: 0;
    }

    h1 {
        font-size: 21px;
        font-weight: 600;
        margin-bottom: 20px;
    }

    p {
        margin-bottom: 16px;
        font-size: 15px;
        color: #4A5568;
    }

    a {
        color: #1A2A46;
        text-decoration: none;
        font-weight: 500;
    }

    /* Layout */
    .container {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        text-align: center;
        padding: 30px 0 20px;
    }

    .logo {
        max-width: 180px;
        height: auto;
    }

    /* Card */
    .card {
        background-color: #FFFFFF;
        border-radius: 10px;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
        padding: 40px;
        margin-bottom: 20px;
        border-top: 5px solid #F0C71F;
    }

    /* Button */
    .button-container {
        margin: 28px 0;
        text-align: center;
    }

    .button {
        display: inline-block;
        background-color: #F0C71F;
        color: #1A2A46 !important;
        text-decoration: none;
        padding: 13px 34px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 15px;
        line-height: 1.5;
        transition: background-color 0.2s ease, transform 0.15s ease;
    }

    .button:hover {
        background-color: #E2B814;
        transform: translateY(-1px);
    }

    /* Divider */
    .divider {
        height: 1px;
        background-color: #E2E8F0;
        margin: 30px 0;
        border: 0;
    }

    /* Footer */
    .footer {
        text-align: center;
        padding: 20px 0;
        color: #718096;
        font-size: 13px;
        line-height: 1.6;
    }

    .footer-links {
        margin: 12px 0;
    }

    .footer-links a {
        color: #718096;
        margin: 0 10px;
        text-decoration: none;
    }

    .footer-links a:hover {
        color: #1A2A46;
    }

    /* Responsive */
    @media only screen and (max-width: 600px) {
        .container {
            padding: 12px;
        }

        .card {
            padding: 26px;
        }

        h1 {
            font-size: 19px;
        }

        p {
            font-size: 14px;
        }

        .button {
            padding: 11px 22px;
            font-size: 14px;
        }
    }
</style>

</head>
<body>
    <div class="container">
        <!-- Header with Logo -->
       $image
        
        <!-- Main Content Card -->
        <div class="card">
            <h1>$subjectLine</h1>
            
            $message
            
            <!-- Action Button -->
            <center>
                <a href="$verificationLink" class="button">$buttonText</a>
            </center>
                                   
            <hr class="divider">
           
                     
            
            <p>Best regards,<br>The {$baseName} Team</p>
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; padding: 20px 0; color: #718096; font-size: 13px; line-height: 1.5;">
                <div style="margin: 10px 0;">
                    <a href="https://enviiz.com/privacy-policy" style="color: #718096; margin: 0 10px; text-decoration: none;font-size: 12px;">Privacy Policy</a>
                    <a href="https://enviiz.com/terms-conditions" style="color: #718096; margin: 0 10px; text-decoration: none;font-size: 12px;">Terms of Service</a>
                    <a href="https://enviiz.com/contact-us" style="color: #718096; margin: 0 10px; text-decoration: none;font-size: 12px;">Contact Us</a>
                </div>
               
                <p style="margin: 10px 0; font-size: 12px; color: #718096; text-align: center;">
                    &copy;$currentYear ENLIXO Uni. All Rights Reserved |
                    Powered by
                    <a href="https://enviiz.com" target="_blank" style="text-decoration: none;">
                        <img src="https://dashboard.timesacademylms.com/assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares (PVT) LTD" style="width: 35px; height: auto; vertical-align: middle;">
                    </a>
                </p>
        </div>
    </div>
</body>
</html>
HTML;

    return $html;
}
