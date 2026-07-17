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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['group-id']) && !empty($_GET['group-id']))) {
            header("Location: ./intakes");
        } else {

            $id = $_GET['group-id'];
            $intake = Database::search("SELECT * FROM `intakes_has_payment_groups` 
            INNER JOIN `intakes` ON `intakes_has_payment_groups`.`intakes_intake_id` = `intakes`.`intake_id`
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
             INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intakes_has_payment_group_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                header("Location: ./intakes");
            } else {
                $intakeData = $intake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 2, $intakeData['intake_id']))) {
                    header("Location: ./intake-details?intake-id=" . $id);
                } else {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Add Payment to Intake Payment Groups - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body>
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "./includes/side-menu.php";
                                    load_side_menu::load("Users");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("Users");
                                            ?>
                                        </div>

                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-table.php"; ?>
                                        </div>

                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-8">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg" data-i18n="">Intake Payment Groups</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?faculty-id=<?= $intakeData['faculties_faculty_id'] ?>'><?= $intakeData["faculty"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intakes?programe-id=<?= $intakeData['programme_id'] ?>">...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-details?intake-id=<?= $intakeData['intake_id'] ?>"><?= $intakeData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Intake Payment Groups</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-lg-4 mt-3 mt-lg-0">
                                                            <div class="row align-items-center justify-content-end gx-3 h-100">
                                                                <?php
                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 2, $id)) {
                                                                ?>
                                                                    <div class="col-6 mt-3 mt-lg-0 m-md-0">
                                                                        <button class="custom-btn w-100" data-i18n="add-new" type="button" onclick="addPaymentIntakePaymentGroup(this, <?= $id ?>);">
                                                                            Add New
                                                                        </button>
                                                                    </div>
                                                                <?php
                                                                }
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center">
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Payment Category</label>
                                                            <select name="payment_category" class="form-select custom-select mt-2" id="payment-category">
                                                                <option value="0">- Select -</option>
                                                                <?php
                                                                $categories = Database::search("SELECT * FROM `payment_category` 
                                                            INNER JOIN currencies ON currencies.currency_id = payment_category.currencies_currency_id");

                                                                while ($category = $categories->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?= $category['payment_category_id'] ?>"><?= $category['payment_category'] ?> (Currency: <?= $category['code'] ?>)</option>
                                                                <?php
                                                                }

                                                                ?>
                                                            </select>
                                                            <span class="" id="payment-category-feedback"></span>
                                                        </div>

                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Payment Type</label>
                                                            <select name="payment_type" class="form-select custom-select mt-2" id="payment-type">
                                                                <option value="0">- Select -</option>
                                                                <?php
                                                                $types = Database::search("SELECT * FROM `payment_type`");

                                                                while ($type = $types->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?= $type['payment_type_id'] ?>"><?= $type['payment_type'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-dark-red text-12" id="payment-type-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Payment Plan</label>
                                                            <select name="payment_plan" class="form-select custom-select mt-2" id="payment-plan">
                                                                <option value="0">- Select -</option>
                                                                <?php
                                                                $plans = Database::search("SELECT * FROM `payment_plan`");

                                                                while ($plan = $plans->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?= $plan['payment_plan_id'] ?>"><?= $plan['payment_plan'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-dark-red text-12" id="payment-plan-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label for="" class="text-14 main-text required">Amount</label>
                                                            <input type="number" class="form-control custom-input mt-2" id="amount">
                                                            <span class="text-dark-red text-12" id="amount-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Discount Type</label>
                                                            <select id="discount-type" class="form-select custom-select mt-2" onchange="changeDiscountType(this)">
                                                                <option selected value="0">- Select -</option>
                                                                <?php
                                                                $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                while ($discountType = $discountTypes->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $discountType['discount_type_id'] ?>"><?php echo $discountType['discount_type'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label class="text-14 main-text not-required" id="discount-label" for="">Discount</label>
                                                            <input type="number" class="form-control custom-input mt-2" id="discount" min="0">
                                                            <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-14 main-text" id="discount-note-label" for="">Discount Note</label>
                                                            <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                            <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Due Date</label>
                                                            <input type="date" class="form-control custom-input mt-2" id="due-date" min="0">
                                                            <span id="due-date-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 d-flex align-items-center">
                                                            <input type="checkbox" class="form-check-input hw-20 m-0 p-0" id="enable-panelty" onchange="enablePanelty(this)">
                                                            <label class="text-14 main-text px-3" for="">Enable Panelty Payment</label>
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex flex-column d-none" id="panelty-option-area">
                                                            <label class="text-14 main-text required" for="">Panelty Options</label>
                                                            <select id="panelty-option" class="form-select custom-select mt-2" onchange="paneltyOptionChange(this)">
                                                                <option selected value="0">- Select -</option>
                                                                <?php
                                                                $options = Database::search("SELECT * FROM `panelty_payment_option`");
                                                                while ($option = $options->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $option['panelty_payment_option_id'] ?>"><?php echo $option['panelty_payment_option'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span id="panelty-option-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column d-none" id="per-day-panelty-category-area">
                                                            <label class="text-14 main-text required" for="">Panelty Categories</label>
                                                            <div class="table-responsive mt-3">
                                                                <table class="table table-bordered">
                                                                    <thead>
                                                                        <tr>
                                                                            <th scope="col"></th>
                                                                            <th scope="col">Id</th>
                                                                            <th scope="col">Type</th>
                                                                            <th scope="col">Amount/Precentage</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        $paneltyTypes = Database::search("
                                                              SELECT 
                                                                  ppc.`panelty_amount`,
                                                                  ppc.`due_dates`,
                                                                  c.`code`,
                                                                  c.`currency_id`,
                                                                  ppt.`panelty_payments_type`,
                                                                  ppt.`panelty_payments_type_id`,
                                                                  ppo.`panelty_payment_option`,
                                                                  ppo.`panelty_payment_option_id`,
                                                                  ppc.`panelty_payment_category_id`
                                                              FROM 
                                                                  `panelty_payment_category` AS ppc
                                                              INNER JOIN 
                                                                  `panelty_payments_type` AS ppt
                                                                  ON ppc.`panelty_payments_type_panelty_payments_type_id`
                                                                  = ppt.`panelty_payments_type_id`
                                                              INNER JOIN 
                                                                  `panelty_payment_option` AS ppo
                                                                  ON ppc.`panelty_payment_option_panelty_payment_option_id`
                                                                  = ppo.`panelty_payment_option_id`
                                                              LEFT JOIN 
                                                                  `currencies` AS c
                                                                  ON ppc.`currencies_currency_id`
                                                                  = c.`currency_id`
                                                                  WHERE ppo.`panelty_payment_option_id` = '1'
                                                          ");

                                                                        if ($paneltyTypes->num_rows > 0) {


                                                                            while ($paneltyType = $paneltyTypes->fetch_assoc()) {
                                                                        ?>
                                                                                <tr>
                                                                                    <td class="allign-middle">
                                                                                        <input type="checkbox" class="form-check-input hw-20" data-option="1" onchange="checkPaneltyCategory(this,<?php echo $paneltyType['panelty_payment_category_id'] ?>);">
                                                                                    </td>
                                                                                    <td><?= $paneltyType['panelty_payment_category_id'] ?></td>
                                                                                    <td><?= $paneltyType['panelty_payment_option'] ?></td>
                                                                                    <td>
                                                                                        <?php
                                                                                        if ($paneltyType['panelty_payments_type_id'] == 1) {
                                                                                            echo $paneltyType['code'] . " " . $paneltyType['panelty_amount'];
                                                                                        } else {
                                                                                            echo $paneltyType['panelty_amount'] . "%";
                                                                                        }
                                                                                        ?>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php
                                                                            }
                                                                        } else {
                                                                            ?>
                                                                            <tr class="justify-content-center align-middle">
                                                                                <td colspan="4" class="align-middle justify-content-center">
                                                                                    <div class="d-flex flex-column align-items-center">
                                                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <span id="panelty-option-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column d-none" id="custom-panelty-category-area">
                                                            <label class="text-14 main-text required" for="">Panelty Categories</label>
                                                            <div class="table-responsive mt-3">
                                                                <table class="table table-bordered">
                                                                    <thead>
                                                                        <tr>
                                                                            <th scope="col"></th>
                                                                            <th scope="col">Id</th>
                                                                            <th scope="col">Type</th>
                                                                            <th scope="col">Amount/Precentage</th>
                                                                            <th scope="col">Due Dates</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        $paneltyTypes2 = Database::search("
                                                              SELECT 
                                                                  ppc.`panelty_amount`,
                                                                  ppc.`due_dates`,
                                                                  c.`code`,
                                                                  c.`currency_id`,
                                                                  ppt.`panelty_payments_type`,
                                                                  ppt.`panelty_payments_type_id`,
                                                                  ppo.`panelty_payment_option`,
                                                                  ppo.`panelty_payment_option_id`,
                                                                  ppc.`panelty_payment_category_id`
                                                              FROM 
                                                                  `panelty_payment_category` AS ppc
                                                              INNER JOIN 
                                                                  `panelty_payments_type` AS ppt
                                                                  ON ppc.`panelty_payments_type_panelty_payments_type_id`
                                                                  = ppt.`panelty_payments_type_id`
                                                              INNER JOIN 
                                                                  `panelty_payment_option` AS ppo
                                                                  ON ppc.`panelty_payment_option_panelty_payment_option_id`
                                                                  = ppo.`panelty_payment_option_id`
                                                              LEFT JOIN 
                                                                  `currencies` AS c
                                                                  ON ppc.`currencies_currency_id`
                                                                  = c.`currency_id`
                                                                  WHERE ppo.`panelty_payment_option_id` = '2'
                                                          ");

                                                                        if ($paneltyTypes2->num_rows > 0) {

                                                                            while ($paneltyType = $paneltyTypes2->fetch_assoc()) {
                                                                        ?>
                                                                                <tr>
                                                                                    <td class="allign-middle">
                                                                                        <input type="checkbox" class="form-check-input hw-20" data-option="2" data-category-id="<?= $paneltyType['panelty_payment_category_id'] ?>" data-dates="<?= $paneltyType['due_dates'] ?>" onchange="checkPaneltyCategory(this,<?php echo $paneltyType['panelty_payment_category_id'] ?>);">
                                                                                    </td>
                                                                                    <td><?= $paneltyType['panelty_payment_category_id'] ?></td>
                                                                                    <td><?= $paneltyType['panelty_payment_option'] ?></td>
                                                                                    <td>
                                                                                        <?php
                                                                                        if ($paneltyType['panelty_payments_type_id'] == 1) {
                                                                                            echo $paneltyType['code'] . " " . $paneltyType['panelty_amount'];
                                                                                        } else {
                                                                                            echo $paneltyType['panelty_amount'] . "%";
                                                                                        }
                                                                                        ?>
                                                                                    </td>
                                                                                    <td><?= $paneltyType['due_dates'] ?></td>
                                                                                </tr>
                                                                            <?php
                                                                            }
                                                                        } else {
                                                                            ?>
                                                                            <tr class="justify-content-center align-middle">
                                                                                <td colspan="4" class="align-middle justify-content-center">
                                                                                    <div class="d-flex flex-column align-items-center">
                                                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <span id="panelty-option-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex justify-content-end">
                                                            <button class="custom-btn col-12 col-lg-4" onclick="addPaymentIntakePaymentGroup(this, <?= $id ?>);">Add New</button>
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
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>