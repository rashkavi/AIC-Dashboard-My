<?php
/**
 * Diagnostic Script for sendReceiptEmail.php
 * Checks if all required libraries are installed
 */

echo "<h1>SendReceiptEmail - Library Diagnostic</h1>";
echo "<hr>";

// Check Dompdf installation
echo "<h3>1. Dompdf Library</h3>";
$dompdfPaths = [
    '../vendor/autoload.php',
    '../includes/dompdf/autoload.inc.php',
];

$dompdfFound = false;
foreach ($dompdfPaths as $path) {
    if (file_exists(__DIR__ . '/' . $path)) {
        echo "✓ Found: <code>$path</code><br>";
        $dompdfFound = true;
        break;
    } else {
        echo "✗ Not found: <code>$path</code><br>";
    }
}

if (!$dompdfFound) {
    echo "<div style='background:#fef2f2; padding:15px; border-radius:5px; color:#dc2626;'>";
    echo "<strong>MISSING!</strong> Dompdf is required for PDF generation.<br>";
    echo "Install with: <code>composer require dompdf/dompdf</code><br>";
    echo "Or download from: <a href='https://github.com/dompdf/dompdf' target='_blank'>github.com/dompdf/dompdf</a>";
    echo "</div>";
}

// Check PHPMailer
echo "<h3>2. PHPMailer Library</h3>";
$phpmailerFiles = [
    '../includes/phpmailer/src/Exception.php',
    '../includes/phpmailer/src/PHPMailer.php',
    '../includes/phpmailer/src/SMTP.php',
];

$phpmailerFound = 0;
foreach ($phpmailerFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ Found: <code>$file</code><br>";
        $phpmailerFound++;
    } else {
        echo "✗ Not found: <code>$file</code><br>";
    }
}

if ($phpmailerFound < 3) {
    echo "<div style='background:#fef2f2; padding:15px; border-radius:5px; color:#dc2626;'>";
    echo "<strong>MISSING PHPMailer files!</strong> All 3 files are required.";
    echo "</div>";
} else {
    echo "<div style='background:#f0fdf4; padding:15px; border-radius:5px; color:#16a34a;'>";
    echo "<strong>✓ All PHPMailer files found</strong>";
    echo "</div>";
}

// Check Custom Email Classes
echo "<h3>3. Custom Email Classes</h3>";
$emailClasses = [
    '../includes/phpmailer/EmailLayoutRenderer.php',
    '../includes/phpmailer/EmailNotificationService.php',
    '../includes/phpmailer/EmailTemplateContent.php',
    '../includes/phpmailer/mail-process.php',
];

$classesFound = 0;
foreach ($emailClasses as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ Found: <code>$file</code><br>";
        $classesFound++;
    } else {
        echo "✗ Not found: <code>$file</code><br>";
    }
}

// Check Database Connection
echo "<h3>4. Database Connection</h3>";
if (file_exists(__DIR__ . '/../includes/connection.php')) {
    echo "✓ Found: <code>../includes/connection.php</code><br>";
} else {
    echo "✗ Not found: <code>../includes/connection.php</code><br>";
}

// Check User Classes
echo "<h3>5. User Classes</h3>";
$userClasses = [
    '../includes/UserClass.php',
    '../includes/UserPermissionsClass.php',
    '../includes/Project.php',
];

foreach ($userClasses as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✓ Found: <code>$file</code><br>";
    } else {
        echo "✗ Not found: <code>$file</code><br>";
    }
}

echo "<hr>";
echo "<h3>Summary</h3>";

if ($dompdfFound && $phpmailerFound == 3 && $classesFound == 4) {
    echo "<div style='background:#d4edda; padding:15px; border-radius:5px; color:#155724;'>";
    echo "<strong>✓ All libraries are installed!</strong> The script should work correctly.";
    echo "</div>";
} else {
    echo "<div style='background:#fef2f2; padding:15px; border-radius:5px; color:#dc2626;'>";
    echo "<strong>✗ Some libraries are missing.</strong> Please install the missing libraries listed above.";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1 { color: #333; }
h3 { color: #555; margin-top: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
</style>
