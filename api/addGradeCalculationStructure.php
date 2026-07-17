<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $subject = isset($decoded['subject']) ? $decoded['subject'] : '';
                    $gradeScales = isset($decoded['gradeScales']) ? $decoded['gradeScales'] : '';
                    $gradeType = isset($decoded['gradeType']) ? $decoded['gradeType'] : '';
                    $gradeTypeData = isset($decoded['gradeTypeData']) ? $decoded['gradeTypeData'] : '';

                    if (empty($subject)) {
                        $status->subject = 'Subject is required';
                    } else {
                        $isValidSubject = Database::search("SELECT * FROM `subjects` WHERE `subject_id` = '" . $subject . "'");
                        if ($isValidSubject->num_rows == 0) {
                            $status->subject = 'Invalid subject id';
                        } else {
                            $subjectData = $isValidSubject->fetch_assoc();

                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademic($sessionUserRole) || UserPermissions::hasPermission('subject', 29, $id))) {
                                $status->error = 'You do not have permission to add grade calculation structure to this subject';
                            } else {

                                if (empty($gradeScales) || !is_array($gradeScales)) {
                                    $status->grade_scales = "At least one grade scale is required.";
                                } else {
                                    $gradeScalesErrors = [];

                                    foreach ($gradeScales as $index => $scale) {

                                        if ($scale["minMark"] === null) {
                                            $gradeScalesErrors[$index] = "Grade scale row " . ($index + 1) . " has missing min mark field.";
                                        }
                                        if ($scale["maxMark"] === null) {
                                            $gradeScalesErrors[$index] = "Grade scale row " . ($index + 1) . " has missing max mark field.";
                                        }
                                        if ($scale["grade"] === "") {
                                            $gradeScalesErrors[$index] = "Grade scale row " . ($index + 1) . " has missing grade field.";
                                        }
                                    }

                                    if (!empty($gradeScalesErrors)) {
                                        $status->grade_scales = $gradeScalesErrors;
                                    }
                                }

                                $isValidGradeType = Database::search("SELECT * FROM `grading_type` WHERE `grading_type_id` = '" . $gradeType . "'");
                                if ($isValidGradeType->num_rows == 0) {
                                    $status->gradeType = 'Invalid grade type';
                                } else {
                                    if ($gradeType == 2) {
                                        if (empty($gradeTypeData)) {
                                            $status->category = "Category weights are required.";
                                        } else {
                                            $categoryErrors = [];
                                            $categoryWeight = 0;
                                            foreach ($gradeTypeData as $index => $row) {

                                                if ($row["weight"] === null) {
                                                    $categoryErrors[$row["category_id"]] =
                                                        "Weight is required.";
                                                } else {
                                                    $categoryWeight += $row["weight"];
                                                    if ($categoryWeight > 100) {
                                                        $categoryErrors[$row["category_id"]] =
                                                            "Category weight cannot be more than 100.";
                                                    }
                                                }
                                            }

                                            if ($categoryWeight != 100) {
                                                $status->category = "Category weight total must be 100.";
                                            }

                                            if (!empty($categoryErrors)) {
                                                $status->category = $categoryErrors;
                                            }
                                        }
                                    } elseif ($gradeType == 4) {

                                        if (empty($gradeTypeData)) {
                                            $status->rubric = "Rubric data is required.";
                                        } else {

                                            $rubricsErrors = [];
                                            $totalWeight = 0;
                                            $totalMarks = 0;

                                            $rubricCount = 1;

                                            foreach ($gradeTypeData as $rIndex => $rubric) {

                                                $rubricErrors = [];

                                                if (empty($rubric["rubricName"])) {
                                                    $rubricErrors["rubricName"] = "Rubric name is required.";
                                                }

                                                if (empty($rubric["rubricWeight"])) {
                                                    $rubricErrors["rubricWeight"] = "Rubric weight is required.";
                                                } else {
                                                    $totalWeight += $rubric["rubricWeight"];

                                                    if ($totalWeight > 100) {
                                                        $rubricErrors["rubricWeight"] = "Rubric weight cannot be more than 100.";
                                                    }
                                                }

                                                if ($totalWeight != 100) {
                                                    $rubricErrors["rubricWeight"] = "Rubric weight total must be 100.";
                                                }

                                                if (empty($rubric["criterias"])) {
                                                    $rubricErrors["criterias"] = "At least one criteria is required.";
                                                } else {

                                                    $criteriaCount = 1;
                                                    $totalWeight = 0;
                                                    $totalMarks = 0;
                                                    foreach ($rubric["criterias"] as $cIndex => $criteria) {
                                                        if (empty($criteria["criteriaName"])) {
                                                            $rubricErrors["criteria"][$criteriaCount]["name"] = "Criteria name is required.";
                                                        }

                                                        if (empty($criteria["weight"])) {
                                                            $rubricErrors["criteria"][$criteriaCount]["weight"] = "Criteria weight is required.";
                                                        } else {
                                                            $totalWeight += $criteria["weight"];

                                                            if ($totalWeight > 100) {
                                                                $rubricErrors["criteria"][$criteriaCount]["weight"] = "Criteria weight cannot be more than 100.";
                                                            }
                                                        }

                                                        if ($totalWeight != 100) {
                                                            $rubricErrors["criteria"][$criteriaCount]["weight"] = "Criteria weight total must be 100.";
                                                        }

                                                        if (empty($criteria["maxMark"])) {
                                                            $rubricErrors["criteria"][$criteriaCount]["maxMark"] = "Criteria max mark is required.";
                                                        } else {
                                                            $totalMarks += $criteria["maxMark"];
                                                            if ($totalMarks > 100) {
                                                                $rubricErrors["criteria"][$criteriaCount]["maxMark"] = "Criteria max mark cannot be more than 100.";
                                                            }
                                                        }

                                                        $criteriaCount++;
                                                    }
                                                }

                                                if (!empty($rubricErrors)) {
                                                    $rubricsErrors[$rubricCount] = $rubricErrors;
                                                }
                                                $rubricCount++;
                                            }

                                            if (!empty($rubricsErrors)) {
                                                $status->rubric = $rubricsErrors;
                                            }
                                        }
                                    }
                                }

                                $requiredProperties = [
                                    'gradeType',
                                    'gradeScales',
                                    'category',
                                    'rubric'
                                ];
                                $allPropertiesExist = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allPropertiesExist = false;
                                        break;
                                    }
                                }
                                if ($allPropertiesExist) {

                                    Database::iud("UPDATE `subjects` SET `grading_type_grading_type_id` = '" . $gradeType . "' WHERE `subject_id` = '" . $subject . "'");

                                    foreach ($gradeScales as $index => $scale) {
                                        $gradeScale = $scale["grade"];
                                        $minMark = $scale["minMark"];
                                        $maxMark = $scale["maxMark"];

                                        $columns = "`min_mark`,`max_mark`,`grade`,`subjects_subject_id`";
                                        $values = "'" . $scale["minMark"] . "', '" . $scale["maxMark"] . "', '" . $scale["grade"] . "', '" . $subject . "'";

                                        if (!empty($scale["gpa"])) {
                                            $columns .= ", `gpa`";
                                            $values .= ", '" . $scale["gpa"] . "'";
                                        }

                                        Database::iud("INSERT INTO `grade_scales` (" . $columns . ") VALUES (" . $values . ")");
                                    }

                                    if ($gradeType == 2) {
                                        foreach ($gradeTypeData as $index => $cat) {

                                            $columns = "`grading_category_type_grading_category_type_id`,`weight`,`subjects_subject_id`";
                                            $values = "'" . $cat['category_id'] . "', '" . $cat['weight'] . "', '" . $subject . "'";

                                            Database::iud("INSERT INTO `grading_category` (" . $columns . ") VALUES (" . $values . ")");
                                        }
                                    } else if ($gradeType == 4) {
                                        foreach ($gradeTypeData as $index => $rubricData) {
                                            $rubricName = $rubricData["rubricName"];
                                            $rubricWeight = $rubricData["rubricWeight"];
                                            $criterias = $rubricData["criterias"];

                                            $columns = "`rubric_name`,`weight`,`subjects_subject_id`";
                                            $values = "'" . $rubricName . "', '" . $rubricWeight . "', '" . $subject . "'";

                                            Database::iud("INSERT INTO `rubrics` (" . $columns . ") VALUES (" . $values . ")");
                                            $rubric_id = Database::$connection->insert_id;

                                            foreach ($criterias as $criteriaIndex => $criteria) {
                                                $criteriaName = $criteria["criteriaName"];
                                                $criteriaWeight = $criteria["weight"];
                                                $criteriaMaxMark = $criteria["maxMark"];

                                                $columns = "`criteria_name`,`weight`,`max_score`,`rubrics_rubric_id`";
                                                $values = "'" . $criteriaName . "', '" . $criteriaWeight . "', '" . $criteriaMaxMark . "', '" . $rubric_id . "'";

                                                Database::iud("INSERT INTO `rubric_criteria` (" . $columns . ") VALUES (" . $values . ")");
                                            }
                                        }
                                    }

                                    $status->done = "Grade calculation structure added successfully.";
                                }
                            }
                        }
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
echo (json_encode($status));
