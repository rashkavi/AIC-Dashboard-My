<?php
require "includes/nav-item.php";

class load_side_menu
{
    public static function load($selectedItem)
    {
        require_once "UserClass.php";
        require_once "UserPermissionsClass.php";

        $sessionUser = SessionUser::getCurrentUserObject();
        $sessionUserRole = SessionUser::getCurrentUserRole();

        $menuItems = [
            ["Dashboard", "material-symbols:team-dashboard-outline", "dashboard", "dashboard"],
        ];

        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('users')) {
            $menuItems = array_merge($menuItems, [
                ["Users", "bx:user-pin", "users", "users"],
            ]);
        }


        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('students')) {
            $menuItems = array_merge($menuItems, [
                ["Students", "ph:student", "students", "students"],
            ]);
        }

        $menuItems = array_merge($menuItems, [
            ["Course Overview", "hugeicons:course", "branches", "course-overview"],
        ]);

        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsLecturer($sessionUserRole) || UserPermissions::validPermisisonType('super_lecture_schedule') || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
            $menuItems = array_merge($menuItems, [
                ["Lecture Schedule", "eos-icons:storage-class-outlined", "lecture-schedule", "lecture-schedule"],
            ]);
        }

        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
            $menuItems = array_merge($menuItems, [
                ["Payments", "f7:wallet", "payments", "payments"],
            ]);
        }

        if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
            $menuItems = array_merge($menuItems, [
                ["Payments", "f7:wallet", "my-payments", "payments"],
            ]);
        }

        // if (SessionUser::ifUserIsPro($sessionUserRole) ||  UserPermissions::validPermisisonType('attendance')) {
        //     $menuItems = array_merge($menuItems, [
        //         ["Attendance", "gg:check-o", "attendance", "attendance"],
        //     ]);
        // }

        if (!SessionUser::ifUserIsLecturer($sessionUserRole)) {
            $menuItems = array_merge($menuItems, [
                ["Student Policy", "hugeicons:policy", "student-policy", "student-policy"],
                ["News", "arcticons:patrika-news", "news", "news"],
                ["FAQ", "tdesign:questionnaire", "frequently-asked-questions", "faq"],
                ["Help Desk", "clarity:help-info-line", "help-desk", "help-desk"],
            ]);
        }
        //     $menuItems = array_merge($menuItems, [
        //         ["Notifications", "tdesign:notification", "notifications", "notifications"],
        //     ]);
        // }

?>
        <nav class="d-flex flex-column container-fluid p-0 m-0 vh-100 gap-1 component-theme">
            <div class="flex-grow-1 overflow-scroll p-0">
                <div class="row container-fluid overflow-hidden justify-content-center component-theme py-4 p-3 m-0">
                    <img src="" alt="<?= Project::getBaseName() ?> Logo" class="institute-logo w-100 p-0 m-0">
                </div>
                <div class="flex-grow-1 px-3 pe-0">
                    <?php
                    foreach ($menuItems as $item) {
                        $isSelected = ($item[0] === $selectedItem);
                        nav_item::loadItem($item[0], $item[1], $item[2], $item[3], !$isSelected);
                    }
                    ?>
                </div>

            </div>

            <button class="d-flex align-items-center border-0 px-3 py-2 m-3" style="background-color: var(--side-menu-user-background);" onclick="signOut();">
                <?php
                if (!empty($sessionUser["user_image"]) && file_exists('./assets/imgs/users/' . $sessionUser["user_image"])) {
                ?>
                    <img src="./assets/imgs/users/<?php echo $sessionUser["user_image"]; ?>" class="hwr-40" alt="User image">
                <?php
                } else {
                    $firstNameInitial = strtoupper(substr($sessionUser["user_fname"], 0, 1));
                    $lastNameInitial = strtoupper(substr($sessionUser["user_lname"], 0, 1));
                ?>
                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                <?php
                }
                ?>
                <div class="d-flex flex-column align-items-start mx-2 flex-grow-1">
                    <span class="text-16 main-text fw-medium"><?php
                                                                $role = ucwords(strtolower($sessionUserRole));
                                                                echo strlen($role) > 11 ? substr($role, 0, 11) . '...' : $role;
                                                                ?></span>
                    <span class=" text-12 sub-text fw-medium">Log Out</span>
                </div>
                <iconify-icon icon="ic:sharp-logout" width="20" height="20" class="text-dark-red"></iconify-icon>
            </button>
        </nav>
<?php
    }
}

?>