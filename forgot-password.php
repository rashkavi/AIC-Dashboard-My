<?php
require_once './includes/UserClass.php';
require_once "./includes/Project.php";
if (SessionUser::hasValidSession()) {
    header("Location: ./dashboard");
} else {
    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['token']) && !empty($_GET['token']))) {
        header("Location: ./index");
    } else {
        $token = $_GET['token'];
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Forgot Password - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/forgot-pw-style.css">

            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        </head>

        <body onload="initPasswordSetup('<?= $token ?>',2);">
            <div class="notifications"></div>
            <div class="container">
                <div class="card">
                    <img src="./assets/imgs/<?= Project::getInstituteLogo() ?>" alt="<?= Project::getBaseName() ?> Logo" class="enviiz-logo institute-logo">

                    <h1>Setup Your Password</h1>
                    <p class="subtitle">Secure your <?= Project::getBaseName() ?> account with a strong password</p>

                    <form id="passwordSetupForm">
                        <!-- Verification Code -->

                        <!-- Password -->
                        <div class="form-group">
                            <label for="password" class="input-label">New Password</label>
                            <div class="input-field">
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Create your password"
                                    required
                                    minlength="8">
                                <span class="password-toggle" id="togglePassword">Show</span>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="password-hints">
                                <div class="hint-item" id="lengthHint">
                                    <span class="hint-icon">✗</span>
                                    <span>At least 8 characters</span>
                                </div>
                                <div class="hint-item" id="numberHint">
                                    <span class="hint-icon">✗</span>
                                    <span>Contains a number</span>
                                </div>
                                <div class="hint-item" id="specialHint">
                                    <span class="hint-icon">✗</span>
                                    <span>Contains a special character</span>
                                </div>
                            </div>
                            <div class="error-message" id="passwordError">Password must meet all requirements</div>
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-group">
                            <label for="confirmPassword" class="input-label">Confirm Password</label>
                            <div class="input-field">
                                <input
                                    type="password"
                                    id="confirmPassword"
                                    name="confirmPassword"
                                    placeholder="Re-enter your password"
                                    required>
                                <span class="password-toggle" id="toggleConfirmPassword">Show</span>
                            </div>
                            <div class="error-message" id="confirmError">Passwords don't match</div>
                        </div>
                        <span class="text-dark-red text-12" id="codeError"></span>
                        <button type="submit" class="btn" id="submitBtn" disabled>Confirm & Continue</button>
                    </form>

                    <a href="<?= Project::getBaseUrl() ?>" class="back-link">Back to login</a>
                </div>
            </div>

            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

        </body>

        </html>

    <?php
    }
    ?>
<?php
}
?>