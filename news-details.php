<?php
require_once './includes/UserClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();

    if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['news-id']) && !empty($_GET['news-id']))) {
        header("Location: ./news");
    } else {
        $news_id = $_GET["news-id"];
        date_default_timezone_set('Asia/Colombo');
        $currentTime = time();
        $isHasNews = Database::search("SELECT `news_id`,`desc`,`likes`,`added_at`,`user_fname`,`user_lname`,`profile_img`,`title`,`user_role`,`likes`,`news_img`,`likes` FROM `news` 
            INNER JOIN `users` ON `news`.`added_by`=`users`.`user_id`
            INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
            INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id`=`user_roles`.`user_role_id`
            INNER JOIN `news_imgs` ON `news`.`news_id`=`news_imgs`.`news_imgs_id`
            WHERE `news`.`news_id`='" . $news_id . "'");

        if ($isHasNews->num_rows == 0) {
            header("Location: ./news");
        } else {
            $newsData = $isHasNews->fetch_assoc();

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
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>News View - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div class="row p-0">
                        <div class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("News");
                            ?>
                        </div>
                        <div class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("News");
                                    ?>
                                </div>

                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php include "./includes/components/skeleton-header.php"; ?>
                                    <div class="row g-4 d-flex">
                                        <div class="col-12 col-lg-8 d-flex flex-grow-1">
                                            <?php include "./includes/components/skeleton-details-card.php"; ?>
                                        </div>
                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                            <div class="component-theme">
                                                <?php
                                                for ($i = 0; $i < 6; $i++) {
                                                    include "./includes/components/skeleton-card.php";
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 pe-4 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">News View</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./news">News</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer">News View</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                    <div class="dropdown w-100">
                                                        <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            Action
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                            <li><a class="dropdown-item" href="edit-news?news-id=<?= $news_id ?>">Edit News</a></li>
                                                            <li><a class="dropdown-item" onclick="removeNews(<?= $news_id ?>)">Remove News</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="container p-0 m-0 g-0">
                                            <div class="row g-4 d-flex">
                                                <div class="col-12 col-lg-8 cursor-pointer d-flex">
                                                    <div class="component-theme">
                                                        <div class="row p-4 d-flex flex-grow-1">
                                                            <div class="col-12 d-flex">
                                                                <div class="col-2 col-lg-1">
                                                                    <?php

                                                                    if (!empty($newsData["profile_img"]) && file_exists('./assets/imgs/users/' . $newsData["profile_img"])) {
                                                                    ?>
                                                                        <img src="./assets/imgs/users/<?= $newsData["profile_img"]; ?>" class="hwr-50 p-0" alt="News publisher image">
                                                                    <?php
                                                                    } else {
                                                                        $firstNameInitial = strtoupper(substr($newsData["user_fname"], 0, 1));
                                                                        $lastNameInitial = strtoupper(substr($newsData["user_lname"], 0, 1));
                                                                    ?>
                                                                        <div class="d-flex hwr-50 dark-theme bg-light-theme align-items-center justify-content-center fw-medium text-16"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                                <div class="col-7 col-lg-8 d-flex flex-column justify-content-center">
                                                                    <span class="text-16 dark-color fw-semibold d-flex align-items-center">
                                                                        <?= $newsData["title"] . "." . $newsData["user_fname"] . " " . $newsData["user_lname"] ?>
                                                                        <iconify-icon icon="lets-icons:check-fill" class="dark-theme" width="20" height="20" />
                                                                    </span>

                                                                    <div class="d-flex flex-row align-items-center">
                                                                        <span class="text-14 highlight-theme fw-medium">@<?= $newsData["user_role"] ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-3 d-flex flex-column justify-content-center align-items-end">
                                                                    <span class="text-16 sub-text bg-light-theme px-2 py-1">About <?= $publishAt ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 d-flex">
                                                                <hr class="w-100">
                                                            </div>
                                                            <div class="col-12 d-flex">
                                                                <?php
                                                                if (!empty($newsData["news_img"]) && file_exists('./assets/imgs/news-imges/' . $newsData["news_img"])) {
                                                                ?>
                                                                    <img src="./assets/imgs/news-imges/<?= $newsData["news_img"] ?>" alt="News image" class="w-100 h-auto">
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>
                                                            <div class="col-12">
                                                                <?= $newsData['desc'] ?></p>
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
                                                                    <span class="text-14 sub-text fw-medium mx-2">50 Shair</span>
                                                                </div> -->
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-lg-4 d-lg-flex">
                                                    <div class="component-theme w-100 d-flex flex-column">
                                                        <div class="row p-4 flex-grow-1 d-flex flex-column">
                                                            <!-- Header Section -->
                                                            <div class="col-12 d-flex align-items-center" style="height: 60px;">
                                                                <div class="col-6 d-flex align-items-center">
                                                                    <h5 class="dark-color fw-medium m-0">Comments ( <?= $commentsCount ?> )</h5>
                                                                </div>
                                                                <div class="col-6 d-flex justify-content-end">
                                                                    <iconify-icon icon="ri:arrow-up-down-line" width="18" height="18" class="sub-text"></iconify-icon>
                                                                    <h5 class="text-14 sub-text fw-medium mx-2">Most Recently</h5>
                                                                    <iconify-icon icon="formkit:down" width="18" height="18" class="sub-text"></iconify-icon>
                                                                </div>
                                                            </div>
                                                            <div class="col-12 d-flex">
                                                                <hr class="w-100">
                                                            </div>

                                                            <!-- Comments Section -->
                                                            <div class="col-12" id="comment-section" style="overflow-y: auto;max-height: 80vh;">
                                                                <?php

                                                                if ($commentsCount > 0) {
                                                                    $comments = Database::search("SELECT `comment`,`added_at`,`first_name`,`last_name`,`profile_img`,`title` FROM `comments`
                                                                        INNER JOIN `students` ON `comments`.`students_student_id`=`students`.`student_id`
                                INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                                                                        INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                                                                        WHERE `news_news_id` = '" . $newsData['news_id'] . "' ORDER BY `comments_id` ASC");

                                                                    while ($comment = $comments->fetch_assoc()) {

                                                                        $commentTimeAgo = strtotime($comment["added_at"]);
                                                                        $currentTime = time();
                                                                        $CommentTimeDifference = $currentTime - $commentTimeAgo;
                                                                        $commentedAt;

                                                                        if ($CommentTimeDifference < 60) {
                                                                            $commentedAt = $CommentTimeDifference . ' seconds ago';
                                                                        } elseif ($CommentTimeDifference < 3600) {
                                                                            $commentedAt = floor($CommentTimeDifference / 60) . ' minutes ago';
                                                                        } elseif ($CommentTimeDifference < 86400) {
                                                                            $commentedAt = floor($CommentTimeDifference / 3600) . ' hours ago';
                                                                        } elseif ($CommentTimeDifference < 604800) {
                                                                            $commentedAt = floor($CommentTimeDifference / 86400) . ' days ago';
                                                                        } elseif ($CommentTimeDifference < 2419200) {
                                                                            $commentedAt = floor($CommentTimeDifference / 604800) . ' weeks ago';
                                                                        } elseif ($CommentTimeDifference < 29030400) {
                                                                            $commentedAt = floor($CommentTimeDifference / 2419200) . ' months ago';
                                                                        } else {
                                                                            $commentedAt = floor($CommentTimeDifference / 29030400) . ' years ago';
                                                                        }
                                                                ?>
                                                                        <div class="col-12 d-flex mb-3">
                                                                            <div class="col-1">
                                                                                <?php

                                                                                if (!empty($comment["profile_img"]) && file_exists('./assets/imgs/students/' . $comment["profile_img"])) {
                                                                                ?>
                                                                                    <img src="./assets/imgs/students/<?= $comment["profile_img"] ?>" class="hwr-40 p-0" alt="Commented student image">
                                                                                <?php
                                                                                } else {
                                                                                    $firstNameInitial = strtoupper(substr($comment["first_name"], 0, 1));
                                                                                    $lastNameInitial = strtoupper(substr($comment["last_name"], 0, 1));
                                                                                ?>
                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium text-14"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </div>
                                                                            <div class="col-7 col-lg-8 d-flex flex-column justify-content-center mx-3">
                                                                                <div class="d-flex flex-row align-items-center">
                                                                                    <span class="text-16 dark-color fw-semibold"><?= $comment["title"] . "." . $comment["first_name"] . " " . $comment["last_name"] ?></span>
                                                                                    <span class="text-14 sub-text mx-2"><?= $commentedAt ?></span>
                                                                                </div>
                                                                                <div class="flex-row mt-2">
                                                                                    <p class="text-14 main-text-color lh-base"><?= $comment["comment"] ?></p>
                                                                                </div>
                                                                                <div class="d-flex flex-row">
                                                                                    <div class="d-flex justify-content-start align-items-center me-2 bg-body">
                                                                                        <iconify-icon icon="mdi:like" width="18" height="18" class="sub-text"></iconify-icon>
                                                                                        <span class="text-14 sub-text fw-medium mx-2">50</span>
                                                                                    </div>
                                                                                    <div class="d-flex justify-content-center align-items-center me-2">
                                                                                        <iconify-icon icon="mdi:dislike" width="18" height="18" class="sub-text"></iconify-icon>
                                                                                        <span class="text-14 sub-text fw-medium mx-2">50</span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                <?php
                                                                    }
                                                                }

                                                                ?>

                                                            </div>
                                                        </div>


                                                    </div>
                                                </div>
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
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php
        }
    }
}
?>