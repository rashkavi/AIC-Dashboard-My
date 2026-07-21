<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $sessionUserObject = SessionUser::getCurrentUserObject();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $faculty = isset($decoded['faculty']) ? $decoded['faculty'] : 0;
                    $programme = isset($decoded['programmeId']) ? $decoded['programmeId'] : 0;
                    $intake = isset($decoded['intakeId']) ? $decoded['intakeId'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10; 
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $query = "SELECT * FROM `intake_has_payment_plans`
                                            INNER JOIN `payment_plan` ON `intake_has_payment_plans`.`payment_plan_payment_plan_id` = `payment_plan_id`
                                            INNER JOIN `payment_category` ON `intake_has_payment_plans`.`payment_category_payment_category_id` = `payment_category_id` 
                                            INNER JOIN `currencies` ON `payment_category`.`currencies_currency_id` = `currency_id`
                                            INNER JOIN `intakes` ON `intake_has_payment_plans`.`intakes_intake_id` = `intakes`.`intake_id`
                                            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                                            ";

                    if ($search_text != '') {
                        $query .= " WHERE (`payment_plan_name` LIKE '%$search_text%' OR `intake` LIKE '%$search_text%' OR `programme` LIKE '%$search_text%' OR `faculty` LIKE '%$search_text%')";
                    }

                    if ($faculty != 0) {
                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `faculties`.`faculty_id` = '" . $faculty . "'";
                        } else {
                            $query .= " AND `faculties`.`faculty_id` = '" . $faculty . "'";
                        }
                    }

                    if ($programme != 0) {
                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `programmes`.`programme_id` = '" . $programme . "'";
                        } else {
                            $query .= " AND `programmes`.`programme_id` = '" . $programme . "'";
                        }
                    }

                    if ($intake != 0) {
                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `intakes`.`intake_id` = '" . $intake . "'";
                        } else {
                            $query .= " AND `intakes`.`intake_id` = '" . $intake . "'";
                        }
                    }

                    $query .= " ORDER BY `intake_has_payment_plans`.`date_added` DESC";

                    $results = Database::search($query);
                    $pageno = $pageno > 0 ? $pageno : 1;
                    $results_count = $results->num_rows;
                    $result_per_page = 10;  // Consistent variable name

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $plans = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                    if ($plans->num_rows == 0) {
?>
                        <div class="col-12 px-4 py-3 component-theme">
                            <div class="row align-items-center justify-content-center">
                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                            </div>
                        </div>
                        <?php
                    } else {
                        while ($data = $plans->fetch_assoc()) {
                        ?>
                            <div class="col-12 bl-solid py-3 m-0 g-0 mt-3">
                                <div class="row p-0 g-0 m-0">
                                    <div class="col-12 position-relative">

                                        <?php
                                        if ($data['is_default_plan'] == 1) {
                                        ?>
                                            <span class="default-ribbon">Default</span>
                                        <?php
                                        }
                                        ?>
                                        <!-- Default Badge -->
                                        <div class="row payment-plan-content pe-4">
                                            <div class="col-8 col-xl-8"
                                                data-bs-toggle="collapse"
                                                role="button"
                                                data-bs-target="#collapse-<?= $data['intake_has_payment_plan_id'] ?>"
                                                aria-expanded="false">

                                                <p class="text-16 dark-color fw-bold m-0">
                                                    <?= $data['faculty'] ?> - <?= $data['programme'] ?> - <?= $data['intake'] ?>
                                                </p>

                                                <p class="text-16 sub-text fw-bold m-0">
                                                    <?= $data['payment_plan_name'] ?> - <?= $data['payment_category'] ?>
                                                </p>

                                                <span class="text-14 sub-text mt-1">Payment Plan : </span>
                                                <span class="text-14 sub-text mt-1 fw-medium">
                                                    <?= $data['payment_plan'] ?> | <?= $data['code'] ?> <?= number_format($data['amount']) ?>
                                                </span>
                                            </div>

                                            <?php
                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments')) {
                                                $isValidPlan = Database::search("SELECT * FROM `payment_plan_has_students_has_intakes` WHERE `intake_has_payment_plan_id` = '" . $data['intake_has_payment_plan_id'] . "'");
                                                if ($isValidPlan->num_rows == 0) {
                                            ?>
                                                    <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                        <button class="custom-danger-btn w-100" onclick="window.location.href='./edit-intake-payment-plan?plan-id=<?= $data['intake_has_payment_plan_id'] ?>'">Edit Payment Plan</button>
                                                    </div>

                                                    <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                        <button class="custom-danger-btn w-100" onclick="confirmDeletePlan(<?= $data['intake_has_payment_plan_id'] ?>)">Delete Payment Plan</button>
                                                    </div>
                                                <?php
                                                } else {
                                                ?>
                                                    <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                        <button class="custom-danger-btn w-100" data-bs-target="#warningModal" data-bs-toggle="modal">Edit Payment Plan</button>
                                                    </div>

                                                    <div class="col-6 col-xl-2 mt-2 mt-xl-0">
                                                        <button class="custom-danger-btn w-100" data-bs-target="#warningModal" data-bs-toggle="modal">Delete Payment Plan</button>
                                                    </div>
                                            <?php
                                                }
                                            } ?>
                                        </div>
                                    </div>
                                    <div class="overflow-x-auto p-0 mt-3 g-0 collapse" id="collapse-<?= $data['intake_has_payment_plan_id'] ?>" data-bs-target="#accordionParent" aria-expanded="false" aria-controls="accordionParent">
                                        <div class="p-4 g-0">
                                            <?php
                                            $planHasPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments`
                                                                            INNER JOIN `payment_type` ON `intake_payments_plan_has_payments`.`payment_type_payment_type_id` = `payment_type`.`payment_type_id`
                                                                            WHERE `intake_has_payment_plan_id`='" . $data['intake_has_payment_plan_id'] . "'");
                                            if ($planHasPayments->num_rows > 0) {
                                                $planId = $data['intake_has_payment_plan_id'];
                                            ?>
                                                <div class="accordion" id="fees-type">
                                                    <?php
                                                    for ($x = 0; $x < $planHasPayments->num_rows; $x++) {
                                                        $data = $planHasPayments->fetch_assoc();
                                                        $itemId = "plan{$planId}_item{$data['intake_payments_plan_has_payment_id']}";
                                                    ?>
                                                        <div class="accordion-item br-0" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>">
                                                            <h2 class="accordion-header br-0" id="heading-<?= $itemId ?>">
                                                                <button class="accordion-button br-0 <?= $x != 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $itemId ?>" aria-expanded="<?= $x == 0 ? 'true' : 'false' ?>" aria-controls="collapse-<?= $itemId ?>">
                                                                    <?= $data['payment_type'] ?>
                                                                </button>
                                                            </h2>
                                                            <div id="collapse-<?= $itemId ?>" class="accordion-collapse collapse <?= $x == 0 ? 'show' : '' ?>" aria-labelledby="heading-<?= $itemId ?>" data-bs-parent="#fees-type">
                                                                <div class="accordion-body">
                                                                    <div class="col-12 component-theme">
                                                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                <label for="" class="text-14 main-text required">Amount</label>
                                                                                <input type="number" class="form-control custom-input mt-2" id="amount" value="<?= $data['amount'] ?>">
                                                                                <span class="text-dark-red text-12" id="amount-feedback"></span>
                                                                            </div>
                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                <label class="text-14 main-text required" for="">Discount Type</label>
                                                                                <select id="discount-type" class="form-select custom-select mt-2" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" onchange="changenstallmentDiscountType(this)">
                                                                                    <?php
                                                                                    $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                                    while ($discountType = $discountTypes->fetch_assoc()) {
                                                                                    ?>
                                                                                        <option value="<?php echo $discountType['discount_type_id'] ?>" <?= $data['discount_type_discount_type_id'] == $discountType['discount_type_id'] ? 'selected' : '' ?> data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>"><?php echo $discountType['discount_type'] ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-lg-4 d-flex flex-column <?= $data['discount_type_discount_type_id'] == 1 ? 'd-none' : '' ?>" id="discount-amount-area">
                                                                                <label class="text-14 main-text required" id="discount-label" for="">Discount</label>
                                                                                <input type="number" class="form-control custom-input mt-2" id="discount" min="0" value="<?= $data['discount'] ?>">
                                                                                <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 d-flex flex-column <?= $data['discount_type_discount_type_id'] == 1 ? 'd-none' : '' ?>" id="discount-note-area">
                                                                                <label class="text-14 main-text" id="discount-note-label" for="">Discount Note</label>
                                                                                <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                                <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                <label class="text-14 main-text not-required" for="">Due Date</label>
                                                                                <input type="date" class="form-control custom-input mt-2" id="due-date" min="0" value="<?= $data['due_date'] ?>">
                                                                                <span id="due-date-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 d-flex align-items-center">
                                                                                <input type="checkbox" class="form-check-input hw-20 m-0 p-0" id="enable-panelty" <?= $data['panelty_payment_option_panelty_payment_option_id'] != 1 ? 'checked' : '' ?> data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" onchange="enablePanelty(this)">
                                                                                <label class="text-14 main-text px-3" for="">Enable Panelty Payment</label>
                                                                            </div>

                                                                            <div class="col-12 col-lg-4 d-flex flex-column <?= $data['panelty_payment_option_panelty_payment_option_id'] == 1 ? 'd-none' : '' ?>" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" id="panelty-option-area">
                                                                                <label class="text-14 main-text required" for="">Panelty Options</label>
                                                                                <select id="panelty-option" class="form-select custom-select mt-2" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" onchange="paneltyOptionChange(this)">
                                                                                    <option selected value="0">- Select -</option>
                                                                                    <?php
                                                                                    $options = Database::search("SELECT * FROM `panelty_payment_option` WHERE `panelty_payment_option_id` != 1");
                                                                                    while ($option = $options->fetch_assoc()) {
                                                                                    ?>
                                                                                        <option value="<?php echo $option['panelty_payment_option_id'] ?>" <?= $data['panelty_payment_option_panelty_payment_option_id'] == $option['panelty_payment_option_id'] ? 'selected' : '' ?>><?php echo $option['panelty_payment_option'] ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <span id="panelty-option-feedback" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" class="text-dark-red text-12"></span>
                                                                            </div>

                                                                            <div class="col-12 <?= $data['panelty_payment_option_panelty_payment_option_id'] != 1 && $data['panelty_payment_option_panelty_payment_option_id'] != 4 ? '' : 'd-none' ?>" id="panelty-flat-area" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>">
                                                                                <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                    <?php
                                                                                    $amountType = 0;
                                                                                    $amount = 0;
                                                                                    $panelties = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` = '" . $data['intake_payments_plan_has_payment_id'] . "'");
                                                                                    if ($panelties->num_rows > 0) {
                                                                                        $paneltiesData = $panelties->fetch_assoc();
                                                                                        $amountType = $paneltiesData['panelty_payment_type_panelty_payment_type_id'];
                                                                                        $amount = $paneltiesData['amount'];
                                                                                    }
                                                                                    ?>
                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                        <label for="" class="text-14 main-text required">Panelty Amount Type</label>
                                                                                        <select class="form-select custom-select mt-2" id="flat-type">
                                                                                            <option value="0">- Select -</option>
                                                                                            <?php
                                                                                            $types = Database::search("SELECT * FROM `panelty_payments_type`");

                                                                                            while ($type = $types->fetch_assoc()) {
                                                                                            ?>
                                                                                                <option value="<?php echo $type['panelty_payments_type_id']; ?>" <?= $amountType == $type['panelty_payments_type_id'] ? 'selected' : '' ?>><?php echo $type['panelty_payments_type']; ?></option>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </select>
                                                                                        <span class="text-12 text-dark-red" id="flat-type-feedback"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                        <label for="" class="text-14 main-text required" id="amount-label">Amount</label>
                                                                                        <input type="number" class="form-control custom-input mt-2" id="flat-amount" value="<?= $amount ?>">
                                                                                        <span class="text-12 text-dark-red" id="flat-amount-feedback"></span>
                                                                                    </div>
                                                                                </div>
                                                                            </div>


                                                                            <div class="col-12 d-flex flex-column <?= $data['panelty_payment_option_panelty_payment_option_id'] != 4 ? 'd-none' : '' ?>" id="panelty-custom-area" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>">
                                                                                <div class="row p-0 m-0" id="custom-slots">

                                                                                    <?php
                                                                                    $panelties = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` = '" . $data['intake_payments_plan_has_payment_id'] . "'");
                                                                                    if ($panelties->num_rows > 0) {
                                                                                        for ($y = 0; $y < $panelties->num_rows; $y++) {
                                                                                            $panelty = $panelties->fetch_assoc();
                                                                                    ?>
                                                                                            <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                                <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                                    <div class="d-flex justify-content-between">
                                                                                                        <span class="text-14 sub-topic">Custom Date Slot - <?= $y + 1 ?></span>
                                                                                                    </div>

                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                        <label class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                        <select class="form-select custom-select mt-2 panelty-type">
                                                                                                            <option value="0">- Select -</option>
                                                                                                            <option value="1" <?= $panelty['panelty_payment_type_panelty_payment_type_id'] == 1 ? 'selected' : '' ?>>Fixed</option>
                                                                                                            <option value="2" <?= $panelty['panelty_payment_type_panelty_payment_type_id'] == 2 ? 'selected' : '' ?>>Percentage</option>
                                                                                                        </select>
                                                                                                    </div>

                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                        <label class="text-14 main-text required">Amount</label>
                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-amount" value="<?= $panelty['amount'] ?>">
                                                                                                    </div>

                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                        <label class="text-14 main-text required">Days</label>
                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-days" value="<?= $panelty['days'] ?>">
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        <?php
                                                                                        }
                                                                                    } else {
                                                                                        ?>
                                                                                        <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                            <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                                <div class="d-flex justify-content-between">
                                                                                                    <span class="text-14 sub-topic">Custom Date Slot - 1</span>
                                                                                                </div>

                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                    <label class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                    <select class="form-select custom-select mt-2 panelty-type">
                                                                                                        <option value="0">- Select -</option>
                                                                                                        <option value="1">Fixed</option>
                                                                                                        <option value="2">Percentage</option>
                                                                                                    </select>
                                                                                                </div>

                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                    <label class="text-14 main-text required">Amount</label>
                                                                                                    <input type="number" class="form-control custom-input mt-2 panelty-amount">
                                                                                                </div>

                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                    <label class="text-14 main-text required">Days</label>
                                                                                                    <input type="number" class="form-control custom-input mt-2 panelty-days">
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    <?php
                                                                                    }
                                                                                    ?>

                                                                                </div>

                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            <?php
                                            }
                                            ?>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        <?php
                        }
                        ?>
                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                            <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                <div class="row p-4 align-items-center">
                                    <!-- Previous Button (col-1) -->
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handlePaymentPlansPagination('previousPage')">
                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                        <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
                                    </button>
                                    <!-- Page Numbers (col-10, centered) -->
                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                        <ul class="pagination-list d-flex mb-0">
                                            <?php
                                            $shudderPages1 = false;
                                            $shudderPages2 = false;

                                            function renderPageButton($i, $isActive = false)
                                            {
                                                $activeClass = $isActive ? 'active' : '';
                                                $action = $isActive ? '' : "onclick=\"handlePaymentPlansPagination('goToPage', '{$i}')\"";
                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                            }

                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                    renderPageButton($i, $pageno == $i);
                                                } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                    $shudderPages1 = true;
                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                    $shudderPages2 = true;
                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                }
                                            }
                                            ?>
                                        </ul>
                                    </div>
                                    <!-- Next Button (col-1) -->
                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handlePaymentPlansPagination('nextPage',<?= $pageno ?>,'<?= $number_of_pages ?>')">
                                        <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    </button>
                                </div>
                            </nav>
                        </div>
<?php
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
