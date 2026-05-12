<?php

declare(strict_types=1);

final class EmailTemplateContent
{
    public const PASSWORD_SETUP = 'password_setup';
    public const PASSWORD_RESET = 'password_reset';


    public static function get(string $type, array $data = []): array
    {
        $baseName = Project::getBaseName();
        $baseURL  = Project::getBasePortalUrl();

        $templates = [

            self::PASSWORD_SETUP => [
                'subject' => "Complete Your {$baseName} System Registration",
                'heading' => "Complete Your {$baseName} System Registration",
                'body' => self::renderBody([
                    "Thank you for creating an account with <strong>{$baseName}</strong>.",
                    "To complete your registration, please verify your email address and set up your password by clicking the button below:"
                ]),
                'button_text' => 'Verify Email & Setup Password',
                'button_url' => $data['action_url'] ?? '',
                'params' => ['action_url'],
            ],

            self::PASSWORD_RESET => [
                'subject' => "Password Reset Request - {$baseName}",
                'heading' => 'Password Reset Request',
                'body' => self::renderBody([
                    "You recently requested to reset your <strong>{$baseName}</strong> system password.",
                    "Please click the button below to proceed with creating a new password.",
                    "<strong>Note:</strong> This password reset link will expire in 15 minutes. If you didn't request a password reset, please contact support immediately."
                ]),
                'button_text' => 'Reset Your Password',
                'button_url' => $data['action_url'] ?? '',
                'params' => ['action_url'],
            ],
        ];

        if (!isset($templates[$type])) {
            throw new InvalidArgumentException("Unsupported email template type: {$type}");
        }

        self::validateRequiredParams($templates[$type]['params'], $data, $type);

        return $templates[$type];
    }

    private static function validateRequiredParams(array $required, array $data, string $type): void
    {
        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required parameter '{$key}' for email type '{$type}'");
            }
        }
    }

    private static function value(array $data, string $key, string $default = ''): string
    {
        return htmlspecialchars((string)($data[$key] ?? $default), ENT_QUOTES, 'UTF-8');
    }

    private static function renderBody(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if ($block === '') {
                continue;
            }
            $html .= "<p>{$block}</p>";
        }
        return $html;
    }

    private static function detailList(array $items): string
    {
        $html = '<table class="detail-table" cellpadding="0" cellspacing="0" border="0" width="100%">';
        foreach ($items as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $html .= '
                <tr>
                    <td class="detail-label" style="padding:6px 0; font-weight:600; width:180px;">' . htmlspecialchars($label) . '</td>
                    <td class="detail-value" style="padding:6px 0;">' . htmlspecialchars((string)$value) . '</td>
                </tr>
            ';
        }
        $html .= '</table>';
        return $html;
    }
}
