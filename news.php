<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>News - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
            <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
            <link rel="stylesheet" href="./assets/plugins/richtexteditor/rte_theme_default.css" />

            <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script type="text/javascript" src="./assets/plugins/richtexteditor/rte.js"></script>
            <script type="text/javascript" src='./assets/plugins/richtexteditor/plugins/all_plugins.js'></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

        </head>

        <body class="overflow-hidden">
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("News");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("News");
                                ?>
                            </div>

                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include "./includes/components/skeleton-header.php"; ?>
                                <div class="row g-4">
                                    <?php
                                    for ($u = 0; $u < 4; $u++) {
                                    ?>
                                        <div class="col-12 col-lg-6">
                                            <?php include "./includes/components/skeleton-details-card.php"; ?>
                                        </div>
                                    <?php
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12 col-md-10">
                                                <div class="row">
                                                    <span class="main-topic lh-lg" data-i18n="news">News</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="news-desc">Stay updated with the latest announcements and platform updates.</span>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                <button class="custom-btn col-12" data-i18n="add-new" onclick="window.location = 'add-news'">Add News</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="container p-0 m-0 g-0">
                                        <div class="row g-4">
                                            <?php

                                            date_default_timezone_set('Asia/Colombo');
                                            $currentTime = time();
                                            $news = Database::search("SELECT `news_id`,`desc`,`likes`,`added_at`,`user_fname`,`user_lname`,`profile_img`,`title`,`user_role`,`likes`,`news_img`,`likes` FROM `news` 
                                                INNER JOIN `users` ON `news`.`added_by`=`users`.`user_id`
                                                INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                                                INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id`=`user_roles`.`user_role_id`
                                                INNER JOIN `news_imgs` ON `news`.`news_id`=`news_imgs`.`news_imgs_id`
                                                WHERE `news`.`status_status_id`='1' ORDER BY `news_id` DESC");

                                            if ($news->num_rows > 0) {
                                                while ($newsData = $news->fetch_assoc()) {
                                                    $comments = Database::search("SELECT COUNT(`comments_id`) as `comments` FROM `comments` WHERE `news_news_id`='" . $newsData["news_id"] . "'");
                                                    $commentsData = $comments->fetch_assoc();
                                                    $commentsCount = $commentsData["comments"];

                                                    $timeAgo = strtotime($newsData["added_at"]);
                                                    $timeDifference = $currentTime - $timeAgo;
                                                    $publishAt;

                                                    if ($timeDifference < 60) {
                                                        $publishAt = $timeDifference . ' seconds ago';
                                                    } elseif ($timeDifference < 3600) {
                                                        $publishAt = floor($timeDifference / 60) . ' minutes ago';
                                                    } elseif ($timeDifference < 86400) {
                                                        $publishAt = floor($timeDifference / 3600) . ' hours ago';
                                                    } elseif ($timeDifference < 604800) {
                                                        $publishAt = floor($timeDifference / 86400) . ' days ago';
                                                    } elseif ($timeDifference < 2419200) {
                                                        $publishAt = floor($timeDifference / 604800) . ' weeks ago';
                                                    } elseif ($timeDifference < 29030400) {
                                                        $publishAt = floor($timeDifference / 2419200) . ' months ago';
                                                    } else {
                                                        $publishAt = floor($timeDifference / 29030400) . ' years ago';
                                                    }
                                            ?>
                                                    <div class="col-12 col-lg-6 cursor-pointer" onclick="window.location.href='./news-details.php?news-id=<?= $newsData['news_id'] ?>'">
                                                        <div class="component-theme">
                                                            <div class="row p-4">
                                                                <div class="col-12 d-flex">
                                                                    <div class="col-2 col-lg-1">
                                                                        <?php
                                                                        if (!empty($newsData["profile_img"]) && file_exists('./assets/imgs/users/' . $newsData["profile_img"])) {
                                                                        ?>
                                                                            <img src="./assets/imgs/users/<?php echo $newsData["profile_img"]; ?>" class="hwr-50" alt="User image">
                                                                        <?php
                                                                        } else {
                                                                            $firstNameInitial = strtoupper(substr($newsData["user_fname"], 0, 1));
                                                                            $lastNameInitial = strtoupper(substr($newsData["user_lname"], 0, 1));
                                                                        ?>
                                                                            <div class="d-flex hwr-50 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                    <div class="col-auto d-flex flex-grow-1 px-3 flex-column justify-content-center">
                                                                        <span class="text-16 dark-color fw-semibold d-flex align-items-center">
                                                                            <?= $newsData["title"] . "." . $newsData["user_fname"] . " " . $newsData["user_lname"] ?>
                                                                            <iconify-icon icon="lets-icons:check-fill" class="dark-theme" width="20" height="20" />
                                                                        </span>

                                                                    </div>
                                                                    <div class="col-auto d-flex flex-column justify-content-center align-items-end">
                                                                        <span class="text-16 sub-text bg-light-theme px-2 py-1">About <?= $publishAt ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 d-flex">
                                                                    <hr class="w-100">
                                                                </div>
                                                                <div class="col-12 d-flex">
                                                                    <p class="text-14 sub-text lh-base text-justify">
                                                                        <?= mb_strimwidth(strip_tags($newsData['desc']), 0, 200, "..."); ?>
                                                                    </p>
                                                                </div>
                                                                <div class="col-12 d-flex">
                                                                    <?php
                                                                    if (!empty($newsData["news_img"]) && file_exists('./assets/imgs/news-imges/' . $newsData["news_img"])) {
                                                                    ?>
                                                                        <img src="./assets/imgs/news-imges/<?php echo $newsData["news_img"] ?>" alt="News image" class="w-100 h-auto">
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                                <div class="col-12 d-flex mt-4">
                                                                    <div class="d-flex justify-content-start align-items-center me-2">
                                                                        <iconify-icon icon="solar:heart-linear" width="18" height="18" class="sub-text" /></iconify-icon>
                                                                        <span class="text-14 sub-text fw-medium mx-2"><?= $newsData["likes"] ?> Likes</span>
                                                                    </div>
                                                                    <div class="d-flex justify-content-center align-items-center me-2">
                                                                        <iconify-icon icon="mingcute:message-1-line" width="18" height="18" class="sub-text"></iconify-icon>
                                                                        <span class="text-14 sub-text fw-medium mx-2"><?= $commentsCount ?> Comments</span>
                                                                    </div>
                                                                    <!-- <div class="d-flex justify-content-start align-items-center">
                                                                        <iconify-icon icon="mdi:share" width="18" height="18" class="sub-text"></iconify-icon>
                                                                        <span class="text-14 sub-text fw-medium mx-2">50 Share</span>
                                                                    </div> -->
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                            } else {
                                                ?>
                                                <div class="col-12 px-4 mt-3 h-100">
                                                    <div class="row component-theme align-items-center justify-content-center h-100">
                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                    </div>
                                                </div>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </div>

                                </div>
                                <div class="flex-grow-1"></div>
                                <footer>
                                    <?php include './includes/footer.php' ?>
                                </footer>
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
}
?>