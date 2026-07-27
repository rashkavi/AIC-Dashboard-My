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
    public const RECEIPT_EMAIL = 'receipt_email';


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

                    "Greetings from <strong>{$baseName}</strong>!",

                    "Congratulations and welcome to <strong>{$baseName}</strong>!",

                    "We are delighted to welcome you to the <strong>" . self::value($data, 'programme_name') . "</strong> at {$baseName}. We look forward to supporting you throughout your academic journey and wish you every success in your studies.",

                    "<strong>Activate Your LMS Account</strong>",

                    "Please use the LMS Activation Button provided below to activate your Learning Management System (LMS) account.",

                    "When you click the activation button, you will be prompted to create your own LMS password. Please choose a secure password and keep it confidential.",

                    self::inlineButton('Activate Your LMS Account', $data['login_url'] ?? ''),

                    "The LMS is your official student portal, where you can access all important academic and administrative information throughout your studies.",

                    "Through the LMS, you can access:",

                    "<ul style='padding-left:18px; margin-top:10px;'>
                        <li>Your Microsoft Teams username</li>
                        <li>Your Microsoft Teams temporary password (Welcome@123)</li>
                        <li>Your class schedule and academic calendar</li>
                        <li>Module information and learning materials</li>
                        <li>Announcements and important notices</li>
                        <li>Assignment and assessment information</li>
                        <li>Your payment details and payment history</li>
                    </ul>",

                    "<strong>Microsoft Teams</strong>",

                    "All online lectures will be conducted via Microsoft Teams.",

                    "Please log in to the LMS to obtain your Microsoft Teams username and sign in using the temporary password <strong>Welcome@123</strong>. You will be required to change your Microsoft Teams password upon your first login.",

                    "We recommend downloading the Microsoft Teams desktop or mobile application and signing in before your first class.",

                    "<strong>Payments</strong>",

                    "You can view your outstanding and completed payment details through the LMS.",

                    "After making a payment, please upload your payment receipt to the LMS for verification. This will help us update your payment records promptly.",

                    "<strong>During Your Studies</strong>",

                    "<ul style='padding-left:18px; margin-top:10px;'>
                        <li>Attendance at scheduled lectures is compulsory.</li>
                        <li>Lecture materials, assignments, and announcements will be available through Microsoft Teams and the LMS.</li>
                        <li>Assignments must be submitted before the specified deadlines. Late submissions may be subject to the academic regulations of your programme.</li>
                        <li>If you miss a lecture, please review the available lecture recordings (where applicable) to stay up to date with the course content.</li>
                        <li>Please check your LMS and email regularly, as important announcements and timetable updates will be communicated through these platforms.</li>
                    </ul>",

                    "<strong>WhatsApp Communication</strong>",

                    "An official WhatsApp group will be created for your intake to share important announcements and reminders. Please join the group once the invitation is shared.",

                    "We are excited to have you as part of the {$baseName} community and wish you a rewarding and successful learning experience.",

                    "Welcome to {$baseName}!",

                    "Kind regards,<br>Academic Affairs {$baseName}",

                ]),

                'params' => [
                    'student_name',
                    'programme_name',
                    'login_url'
                ],
            ],

            // Add this constant to the class

            // Add this to the $templates array in the get() method:
            self::RECEIPT_EMAIL => [
                'subject' => "Payment Receipt — " . ($data['receipt_number'] ?? 'Receipt'),
                'heading' => 'Your Payment Receipt',
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "Thank you for your payment. Your official payment receipt is attached to this email.",
                    self::detailList([
                        'Receipt No'    => self::value($data, 'receipt_number'),
                        'Programme'     => self::value($data, 'programme'),
                        'Intake'        => self::value($data, 'intake'),
                        'Total Paid'    => self::value($data, 'total_paid'),
                        'Balance Due'   => self::value($data, 'balance_due'),
                        'Payment Date'  => self::value($data, 'payment_date'),
                    ]),
                    "Please keep this receipt for your records. This is a computer-generated receipt and does not require a physical signature.",
                ]),
                'button_text' => '',
                'button_url'  => '',
                'params' => [
                    'student_name',
                    'receipt_number',
                    'programme',
                    'intake',
                    'total_paid',
                    'balance_due',
                    'payment_date',
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

    private static function inlineButton(string $text, string $url): string
    {
        if ($url === '') {
            return '';
        }
        return '
        <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0;">
            <tr>
                <td align="center">
                    <table cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="center" bgcolor="#21409B" style="border-radius:6px;">
                                <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"
                                   target="_blank"
                                   style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:600;
                                          color:#ffffff; text-decoration:none; border-radius:6px; font-family:Arial,sans-serif;">
                                    ' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    ';
    }
}
