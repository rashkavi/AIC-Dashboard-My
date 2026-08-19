<header class="overflow-visible component-theme">
    <div class="col-12 px-4 pt-2 pb-2">
        <div class="d-flex align-items-center justify-content-beetween">
            <div class="flex-grow-1 d-none d-xl-block">
                <div class="h-100 w-75 d-flex align-items-center p-1">
                    <iconify-icon icon="gg:menu-left" width="24" height="24" class="sub-topic pe-3 pt-2 pb-2 cursor-pointer" id="toggleMenu"></iconify-icon>
                    <span class="text-18 fw-semibold" data-i18n="hello"></span>
                    <span class="mx-1 text-18 fw-medium"><?= $sessionUserObject['user_fname'] . " " . $sessionUserObject['user_lname'] ?></span>
                    <iconify-icon icon="noto:clapping-hands" width="24" height="24" class="sub-topic pe-3 pt-2 pb-2 cursor-pointer" id="toggleMenu"></iconify-icon>
                </div>
            </div>
            <div class="flex-grow-1 d-block d-xl-none">
                <div class="d-flex align-items-center flex-grow-1">
                    <iconify-icon icon="gg:menu-left" width="24" height="24" class="sub-topic pe-3 pt-2 pb-2 cursor-pointer" data-bs-toggle="offcanvas" data-bs-target="#offcanvas"></iconify-icon>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-end h-100 gap-2">
                <div class="d-flex align-items-center cursor-pointer" data-bs-target="#searchModal" data-bs-toggle="modal">
                    <iconify-icon icon="teenyicons:search-outline" width="16" height="16" class="text-dark-color"></iconify-icon>
                </div>
                <iconify-icon icon="vaadin:line-v" width="16" height="20" class="text-dark-color px-1"></iconify-icon>

                <div class="d-flex align-items-center justify-content-center">
                    <span class="text-dark-color text-16 fw-medium py-2 br-8">v<?= Project::getVersion() ?></span>
                </div>
                <iconify-icon icon="vaadin:line-v" width="16" height="20" class="text-dark-color px-1"></iconify-icon>
                <div class="dropdown text-decoration-none">
                    <?php
                    if (!empty($sessionUserObject["user_image"]) && file_exists('./assets/imgs/users/' . $sessionUserObject["user_image"])) {
                    ?>
                        <img src="./assets/imgs/users/<?php echo $sessionUserObject["user_image"]; ?>"
                            class="hwr-40 rounded-circle header-ud-toggle-area"
                            id="userDropdown"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                            alt="User image"
                            style="cursor: pointer;">
                    <?php
                    } else {
                        $firstNameInitial = strtoupper(substr($sessionUserObject["user_fname"], 0, 1));
                        $lastNameInitial = strtoupper(substr($sessionUserObject["user_lname"], 0, 1));
                    ?>
                        <div class="d-flex hwr-40 rounded-circle align-items-center justify-content-center fw-medium cursor-pointer bg-light-theme dark-theme header-ud-toggle-area"
                            id="userDropdown">
                            <?php echo $firstNameInitial . $lastNameInitial; ?>
                        </div>
                    <?php } ?>

                    <!-- Dropdown Menu -->
                    <ul class="dropdown-menu dropdown-menu-end user-dropdown shadow border-0 mt-2" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2"
                                href="#"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#settingsOffcanvas">
                                <iconify-icon icon="carbon:settings" width="18" height="18"></iconify-icon>
                                Settings
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <li> <a class="dropdown-item d-flex align-items-center gap-2" href="my-profile"> <iconify-icon icon="line-md:edit" width="18" height="18"></iconify-icon> Edit Profile </a> </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li> <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="api/signOut.php"> <iconify-icon icon="line-md:logout" width="18" height="18"></iconify-icon> Log Out </a> </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>
<style>
    .hover-bg:hover {
        background-color: var(--light-theme);
        cursor: pointer;
        transition: background 0.2s;
    }
</style>

<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div class="w-100 d-flex align-items-center p-1 bl-solid search-container">
                    <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="dark-theme px-3 pe-3 pt-2 pb-2"></iconify-icon>
                    <input type="text" class="h-100 sub-text form-control border-0 shadow-none live-search-input" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="live-search-input" onkeyup="getLiveSearchResults()">

                    <button type="button" class="custom-btn px-3" id="advanced-search-btn" title="Advanced Search" data-i18n="[title]advanced-search" onclick="window.location.href = 'advanced-search'">
                        <iconify-icon icon="mdi:tune-variant" width="20" height="20" class="p-0 m-0"></iconify-icon>
                    </button>
                </div>
            </div>
            <div class="modal-body">
                <div class="modal-body px-1 py-0" id="live-search-results">

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Settings Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="settingsOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>

    <div class="offcanvas-body">
        <style>
            .theme-toggle-btn {
                background-color: var(--body-color) !important;
                color: var(--dark-color) !important;
            }

            .theme-toggle-btn.btn-primary {
                background-color: var(--dark-theme) !important;
                color: white !important;
            }
        </style>
        <!-- Theme -->
        <div class="mb-3">
            <label class="text-muted small">Theme</label>

            <div class="d-flex gap-2 mt-1">
                <!-- Dark Mode -->
                <button id="lightThemeBtn"
                    class="d-flex align-items-center justify-content-center theme-toggle-btn btn rounded-pill px-3"
                    data-theme="light">
                    <iconify-icon icon="line-md:moon-alt-to-sunny-outline-loop-transition"
                        width="20" height="20" class="p-1"></iconify-icon>
                </button>

                <!-- Light Mode -->
                <button id="darkThemeBtn"
                    class="d-flex align-items-center justify-content-center theme-toggle-btn btn rounded-pill px-3"
                    data-theme="dark">
                    <iconify-icon icon="line-md:sunny-filled-loop-to-moon-filled-loop-transition"
                        width="20" height="20" class="p-1"></iconify-icon>
                </button>
            </div>
        </div>


        <div class="mb-3 d-flex justify-content-between align-items-center">
            <span>Language</span>

            <div class="dropdown">
                <button class="btn bg-content-body px-3 rounded-pill dropdown-toggle"
                    id="languageDropdown"
                    type="button"
                    data-bs-toggle="dropdown">
                    English
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item language-select" data-value="en" data-text="English" href="#">English</a></li>
                    <li><a class="dropdown-item language-select" data-value="si" data-text="Sinhala" href="#">සිංහල</a></li>
                    <li><a class="dropdown-item language-select" data-value="ta" data-text="Tamil" href="#">தமிழ்</a></li>
                </ul>
            </div>
        </div>

        <!-- Currency -->
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <span>Devices and Activities</span>
        </div>

        <div class="mt-3">
            <?php
            $userDevices = Database::search("SELECT * FROM `users_devices` 
            WHERE `users_user_id`='" . $sessionUserObject["user_id"] . "' ORDER BY `last_active` DESC LIMIT 3");

            while ($userDevice = $userDevices->fetch_assoc()) {
            ?>
                <div class="device-item d-flex align-items-center gap-3 p-3 rounded-3 bl-solid mb-2">
                    <div class="d-flex align-items-center justify-content-center icon-wrapper p-2 br-5 bg-content-body">
                        <?php
                        if ($userDevice["device_type"] == "Desktop") {
                            echo '<iconify-icon icon="solar:laptop-broken" width="26" height="26"></iconify-icon>';
                        } else if ($userDevice["device_type"] == "Mobile") {
                            echo '<iconify-icon icon="mynaui:mobile" width="26" height="26"></iconify-icon>';
                        } else {
                            echo '<iconify-icon icon="solar:tablet-broken" width="26" height="26"></iconify-icon>';
                        }
                        ?>
                    </div>

                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark-theme"><?php echo $userDevice["device_name"]; ?> | <?php echo $userDevice["os"]; ?> | <?php echo $userDevice["browser"]; ?></div>
                        <?php
                        if ($userDevice['is_active'] == 1) {
                        ?>
                            <div class="d-flex align-items-center gap-3 mt-1">
                                <span class="d-flex align-items-center justify-content-center table-btn table-btn-success fw-medium rounded-pill px-2 py-1">
                                    <iconify-icon icon="icon-park-outline:dot"></iconify-icon>
                                    <span class="me-1">
                                        <span class="rounded-circle d-inline-block table-btn-success"></span>
                                    </span>
                                    Current session
                                </span>
                            </div>
                        <?php
                        } else {
                        ?>
                            <div class="d-flex align-items-center gap-3 mt-1">
                                <span class="text-muted text-12">Last Active: <?= date("Y-m-d h:i A", strtotime($userDevice["last_active"])) ?>
                                </span>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                </div>
            <?php
            }
            ?>

        </div>
    </div>
</div>



<?php
global $setSelectItem;
class selectItem
{
    public static function setSelected($selectItem)
    {
        global $setSelectItem;
        $setSelectItem = $selectItem;
?>
        <div class="offcanvas offcanvas-start d-block d-xl-none" style="max-width: max-content;" data-bs-scroll="true" data-bs-show="false" tabindex="-1" id="offcanvas" aria-labelledby="offcanvasLabel">
            <div class="offcanvas-body overflow-hidden p-0">
                <button type="button" class="btn-close inside-close-btn" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                <?php
                load_side_menu::load($setSelectItem);
                ?>
            </div>
        </div>
<?php
    }
}
?>