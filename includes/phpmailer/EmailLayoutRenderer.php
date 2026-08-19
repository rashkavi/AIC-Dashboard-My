<?php

declare(strict_types=1);

final class EmailLayoutRenderer
{
    public static function render(array $template, string $extraCss = ''): string
    {
        $currentYear   = date('Y');
        $baseName      = Project::getBaseName();
        $baseURL       = Project::getBaseURL();
        $instituteLogo = Project::getInstituteLogo();

        $subjectLine = $template['subject'] ?? $baseName;
        $heading     = $template['heading'] ?? $subjectLine;
        $body        = $template['body'] ?? '';
        $buttonText  = $template['button_text'] ?? '';
        $buttonUrl   = $template['button_url'] ?? '';

        $buttonHtml = '';
        if (!empty($buttonText) && !empty($buttonUrl)) {
            $buttonHtml = '
                <center>
                    <a href="' . htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8') . '" class="button">' . htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8') . '</a>
                </center>
            ';
        }
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$baseName} - {$subjectLine}</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
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
            width: 250px !important;
            max-width: 250px !important;
            height: auto !important;
        }
        .card {
            background-color: #f5f7fa;
            padding: 40px;
        }
        h1 {
            color: #21409B;
            font-size: 20px;
            font-weight: 600;
            margin-top: 0;
            margin-bottom: 20px;
        }
        p {
            margin-bottom: 20px;
            font-size: 16px;
            color: #4a5568;
        }
        .button {
            display: inline-block;
            background-color: #21409B;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            font-weight: 500;
            margin: 20px 0;
            text-align: center;
        }
        .button:hover {
            background-color: #18327F;
        }
        .footer {
            text-align: center;
            padding: 10px 0;
            color: #718096;
            font-size: 14px;
        }
        .divider {
            height: 1px;
            background-color: #e2e8f0;
            margin: 30px 0;
        }
        .detail-table {
            margin: 10px 0 20px 0;
            border-collapse: collapse;
        }
        .detail-label,
        .detail-value {
            font-size: 15px;
            color: #4a5568;
            vertical-align: top;
        }

        {$extraCss}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{$baseURL}/assets/imgs/{$instituteLogo}" alt="{$baseName} Logo" class="logo">
        </div>

        <div class="card">
            <h1>{$heading}</h1>
            {$body}
            {$buttonHtml}
            <div class="divider"></div>
            <p>Best regards,<br>The {$baseName} Team</p>
        </div>

        <div class="footer">
            <p style="margin: 10px 0; font-size: 12px; color: #718096; text-align: center;">
                &copy;{$currentYear} ENLIXO Uni. All Rights Reserved |
                Powered by
                <a href="https://enviiz.com" target="_blank" style="text-decoration: none;">
                    <img src="https://dashboard.summersetlms.com/assets/imgs/common/enviiz-theme.webp"
                         alt="ENVIIZ Softwares (PVT) LTD"
                         style="width: 35px; height: auto; vertical-align: middle;">
                </a>
            </p>
            <p>
                <a href="https://enviiz.com/privacy-policy" style="text-decoration: none; font-size: 12px; color: #718096;">Privacy Policy</a> |
                <a href="https://enviiz.com/terms-conditions" style="text-decoration: none; font-size: 12px; color: #718096;">Terms of Service</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
