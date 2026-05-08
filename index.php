<?php
require_once './includes/UserClass.php';
require_once './includes/Project.php';
if (SessionUser::hasValidSession()) {
    header("Location: ./dashboard");
} else {
    $email = null;
    $password = null;
    $rememberMe = null;
    if (isset($_COOKIE['times_academy_lms__user_email'])) {
        $email = $_COOKIE['times_academy_lms__user_email'];
    }
    if (isset($_COOKIE['times_academy_lms__user_password'])) {
        $password = $_COOKIE['times_academy_lms__user_password'];
    }

?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>Admin Dashboard | <?= Project::getBaseName() ?></title>

        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.css">
        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

    </head>

    <body class="overflow-hidden">
        <div class="notifications"></div>
        <!-- Include Section -->
        <!-- <?php include './includes/loader.php' ?> -->
        <!-- Main Content Section (Initially Hidden) -->
        <div class="vh-100 container-fluid overflow-hidden">
            <div class="row h-100 align-items-center justify-content-center">
                <div class="col-12 col-xl-6 component-theme">
                    <div class="row align-items-center justify-content-center p-0">
                        <div class="col-10 h-100">
                            <div class="d-flex flex-column align-items-center justify-content-center" style="height: calc(100vh - 2rem);">
                                <div class="col-12 col-lg-6 p-0 text-center">
                                    <img src="./assets/imgs/<?= Project::getInstituteLogo() ?>" class="h-auto enviiz-logo institute-logo" alt="<?= Project::getBaseName() ?>">
                                </div>

                                <div class="col-12 mt-2 mb-2 text-center">
                                    <p class="text-24 sub-topic-color fw-medium lh-lg" data-i18n="staff-login"></p>
                                </div>

                                <div class="col-12 col-lg-10 col-xxl-8 mb-4 mb-md-2 mb-xl-4 justify-content-start">
                                    <label class="form-label text-14 sub-topic-color" data-i18n="email">Email</label>
                                    <input type="email" class="form-control custom-input" id="email" style="height: 3.2rem;" <?= $email != null ? "value='" . $email . "'" : "" ?> />
                                    <span id="email-feedback" class="text-dark-red text-12"></span>
                                </div>

                                <div class="col-12 col-lg-10 col-xxl-8 mb-4 mb-md-2 mb-xl-4 justify-content-start">
                                    <label class="form-label text-14 sub-topic-color" data-i18n="password">Password</label>
                                    <div class="d-flex align-items-center justify-content-end" style="height: 3.2rem;">
                                        <input type="password" class="h-100 w-100 sub-text form-control custom-input" id="password" <?= $password != null ? "value='" . $password . "'" : "" ?>>
                                        <iconify-icon icon="tabler:eye-off" width="20" height="20" class="sub-text password-toggle" id="togglePassword" onclick="togglePasswordVisibility();"></iconify-icon>
                                    </div>
                                    <span id="password-feedback" class="text-dark-red text-12"></span>
                                </div>
                                <div class="col-12 col-lg-10 col-xxl-8">
                                    <div class="row align-items-center">
                                        <div class="col-6 mb-4 mb-md-2 mb-xl-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="rememberMe">
                                                <label for="rememberme" class="text-14 sub-text" data-i18n="rememberme">Remember Me</label>
                                            </div>
                                        </div>
                                        <div class="col-6 text-end mb-4 mb-md-2 mb-xl-4">
                                            <a class="text-14 dark-theme cursor-pointer" data-i18n="forgotp" onclick="forgottenPassword();">Forgot Password?</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-lg-10 col-xxl-8">
                                    <button class="w-100 custom-btn d-flex align-items-center justify-content-center" onclick="signIn(this);">
                                        <span class="text-16 fw-semibold pe-3" data-i18n="signin">Sign In</span>
                                        <iconify-icon icon="rivet-icons:arrow-right" width="16" height="16" class="text-white"></iconify-icon>
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex flex-column pb-4 m-0 align-items-center justify-content-end" style="height: 2rem;">
                                <p class="text-12 sub-text p-0 m-1 g-0 text-center">&copy;<?php echo date("Y"); ?> ENLIXO Uni. All Rights Reserved | Powered by
                                    <a href="https://enviiz.com" target="_blank" class="text-decoration-none footer-link">
                                        <img src="./assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares (PVT) LTD" style="width: 40px;height: auto;" class="enviiz-logo">
                                    </a>
                                </p>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="col-xl-6 d-none d-xl-block p-0">
                    <div class="row vh-100 d-flex align-items-center justify-content-center">
                        <div id="loginPageImageCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-indicators">
                                <button type="button" data-bs-target="#loginPageImageCarousel" data-bs-slide-to="0" class="active" aria-current="true"></button>
                                <button type="button" data-bs-target="#loginPageImageCarousel" data-bs-slide-to="1"></button>
                                <button type="button" data-bs-target="#loginPageImageCarousel" data-bs-slide-to="2"></button>
                            </div>
                            <div class="carousel-inner">
                                <div class="carousel-item active">
                                    <div class="carousel-overlay"></div>
                                    <img src="./assets/imgs/slide-img/slide-img-1.png" class="d-block w-100" alt="Slide 1">
                                </div>
                                <div class="carousel-item">
                                    <div class="carousel-overlay"></div>
                                    <img src="./assets/imgs/slide-img/slide-img-2.png" class="d-block w-100" alt="Slide 2">
                                </div>
                                <div class="carousel-item">
                                    <div class="carousel-overlay"></div>
                                    <img src="./assets/imgs/slide-img/slide-img-3.png" class="d-block w-100" alt="Slide 3">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
    </body>

    </html>
<?php
}
?>