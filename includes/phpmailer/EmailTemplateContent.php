<?php

declare(strict_types=1);

final class EmailTemplateContent
{
    public const PASSWORD_SETUP = 'password_setup';
    public const PASSWORD_RESET = 'password_reset';
    public const PASSWORD_SETUP_SUCCESS = 'password_setup_success';
    public const PAYMENT_PLAN_CREATED = 'payment_plan_created';
    public const PAYMENT_SUCCESSFUL = 'payment_successful';
    public const STUDENT_WELCOME_EMAIL = 'student_welcome_email';

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
            self::PASSWORD_SETUP_SUCCESS => [
                'subject' => "Password Setup Successful - {$baseName}",
                'heading' => 'Password Setup Successful',
                'body' => self::renderBody([
                    "Your <strong>{$baseName}</strong> LMS password has been set up successfully.",
                    "You can now log in to the LMS using your registered email address and the password you created.",
                    "<strong>Email:</strong> " . ($data['email'] ?? ''),
                    "Please keep your login details secure and do not share your password with anyone."
                ]),
                'button_text' => 'Login to LMS',
                'button_url' => $data['login_url'] ?? '',
                'params' => ['email', 'login_url'],
            ],

            self::PAYMENT_PLAN_CREATED => [
                'subject' => "Your Payment Plan Has Been Ready - {$baseName}",

                'heading' => 'Your Programme Enrollment Payment Plan is Ready',

                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",

                    "Your student payment plan has been successfully created for the selected programme.",

                    "<strong>Payment Category:</strong> " . self::value($data, 'payment_category'),

                    "<strong>Payment Plan:</strong> " . self::value($data, 'payment_plan'),
                    self::raw($data, 'payment_table'),
                    "Please ensure all payments are completed before the respective due dates.",
                ]),

                'params' => [
                    'student_name',
                    'payment_plan',
                    'payment_category',
                    'payment_table'
                ],
            ],
            self::PAYMENT_SUCCESSFUL => [
                'subject' => "Payment Successfully Received - {$baseName}",

                'heading' => 'Your Payment Has Been Successfully Processed',

                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",

                    "We are pleased to inform you that your payment has been successfully received and processed for your programme enrollment.",

                    self::detailList([
                        'Programme' => self::value($data, 'programme'),
                        'Payment Type' => self::value($data, 'payment_type'),
                        'Paid Amount' => self::value($data, 'paid_amount'),
                        'Payment Date' => self::value($data, 'payment_date'),
                    ]),

                    "Your payment has been successfully recorded in our system.",

                    "Please keep your payment confirmation and slip for future reference.",

                    "Thank you for choosing <strong>{$baseName}</strong>. We wish you success in your academic journey."
                ]),

                'button_text' => 'View Payment Slip',

                'button_url' => $data['slip_url'] ?? '',

                'params' => [
                    'student_name',
                    'programme',
                    'payment_type',
                    'paid_amount',
                    'payment_date',
                    'slip_url'
                ],
            ],
            self::STUDENT_WELCOME_EMAIL => [

                'subject' => "Welcome to {$baseName} - Your Student Account is Ready",

                'heading' => 'Welcome to ' . $baseName,

                'body' => self::renderBody([

                    "Dear " . self::value($data, 'student_name', 'Student') . ",",

                    "We are pleased to officially welcome you to <strong>{$baseName}</strong>.",

                    "Your student account has been successfully created, and your academic journey with us is now ready to begin.",

                    "You can now access the Student Portal and LMS platform using your registered email address and password.",

                    "<strong>Email Address:</strong> " . self::value($data, 'email'),

                    "Through the student portal, you will be able to access important academic and administrative services including:",

                    "<ul style='padding-left:18px; margin-top:10px;'>
                        <li>Programme & Enrollment Information</li>
                        <li>Payment Plans & Receipts</li>
                        <li>Lecture Timetables</li>
                        <li>Assignments & Examinations</li>
                        <li>Academic Announcements</li>
                        <li>Student Support Services</li>
                    </ul>",

                    "Please ensure that your login credentials remain secure and confidential at all times.",

                    "If you require any assistance regarding your enrollment or student portal access, our administration team will be happy to assist you.",

                    "We wish you success throughout your academic journey and look forward to supporting your future achievements.",

                ]),

                'button_text' => 'Access Student Portal',

                'button_url' => $data['login_url'] ?? '',

                'params' => [
                    'student_name',
                    'email',
                    'login_url'
                ],
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

    private static function raw(array $data, string $key, string $default = ''): string
    {
        return $data[$key] ?? $default;
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
