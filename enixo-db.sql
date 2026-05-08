-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.0.32 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.3.0.6589
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table lms_enviiz_latest_no_data.answers
CREATE TABLE IF NOT EXISTS `answers` (
  `answer_id` int NOT NULL AUTO_INCREMENT,
  `answer` text NOT NULL,
  `answer_order` int NOT NULL,
  `questions_question_id` int NOT NULL,
  PRIMARY KEY (`answer_id`),
  KEY `fk_answers_questions1_idx` (`questions_question_id`),
  CONSTRAINT `fk_answers_questions1` FOREIGN KEY (`questions_question_id`) REFERENCES `questions` (`question_id`)
) ENGINE=InnoDB AUTO_INCREMENT=389 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.answers: ~8 rows (approximately)
INSERT INTO `answers` (`answer_id`, `answer`, `answer_order`, `questions_question_id`) VALUES
	(1, 'Sample Answer 1', 1, 1),
	(2, 'Sample Answer 2', 2, 1),
	(3, 'Sample Answer 3', 3, 1),
	(4, 'Sample Answer 4', 4, 1),
	(5, 'Answer 1', 1, 2),
	(6, 'Answer 2', 2, 2),
	(7, 'Answer 3', 3, 2),
	(8, 'Answer 4', 4, 2);

-- Dumping structure for table lms_enviiz_latest_no_data.assignments
CREATE TABLE IF NOT EXISTS `assignments` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `assignment_code` varchar(45) NOT NULL,
  `assignment` varchar(45) NOT NULL,
  `attachment` varchar(45) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `added_date` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  `subjects_subject_id` int NOT NULL,
  `assignments_order` int NOT NULL,
  `assignment_status_assignment_status_id` int NOT NULL,
  `late_submission_end_date` datetime DEFAULT NULL,
  `recorrection_apply_end_date` datetime DEFAULT NULL,
  `plagiarism_status_plagiarism_status_id` int NOT NULL,
  PRIMARY KEY (`assignment_id`),
  KEY `fk_assignments_users1_idx` (`users_user_id`),
  KEY `fk_assignments_subjects1_idx` (`subjects_subject_id`),
  KEY `fk_assignments_assignment_status1_idx` (`assignment_status_assignment_status_id`),
  KEY `fk_assignments_plagiarism_status1_idx` (`plagiarism_status_plagiarism_status_id`),
  CONSTRAINT `fk_assignments_assignment_status1` FOREIGN KEY (`assignment_status_assignment_status_id`) REFERENCES `assignment_status` (`assignment_status_id`),
  CONSTRAINT `fk_assignments_plagiarism_status1` FOREIGN KEY (`plagiarism_status_plagiarism_status_id`) REFERENCES `plagiarism_status` (`plagiarism_status_id`),
  CONSTRAINT `fk_assignments_subjects1` FOREIGN KEY (`subjects_subject_id`) REFERENCES `subjects` (`subject_id`),
  CONSTRAINT `fk_assignments_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignments: ~4 rows (approximately)
INSERT INTO `assignments` (`assignment_id`, `assignment_code`, `assignment`, `attachment`, `start_date`, `end_date`, `added_date`, `users_user_id`, `subjects_subject_id`, `assignments_order`, `assignment_status_assignment_status_id`, `late_submission_end_date`, `recorrection_apply_end_date`, `plagiarism_status_plagiarism_status_id`) VALUES
	(1, 'ASSI/001', 'Assignment I', '690d9b0ca263a.pdf', '2025-06-26 08:00:00', '2025-11-14 08:00:00', '2025-06-26 01:53:44', 3, 1, 0, 2, NULL, NULL, 2),
	(2, 'ASSI/002', 'Assignment II', '685c5b2acd7da.pdf', '2025-07-05 08:00:00', '2025-07-12 08:00:00', '2025-06-26 01:55:14', 3, 1, 0, 2, NULL, NULL, 2),
	(3, 'ASSI/003', 'Assignment I', '685c66c0e4654.pdf', '2025-07-06 08:00:00', '2025-07-12 11:00:00', '2025-06-26 02:44:40', 3, 3, 0, 2, NULL, NULL, 2),
	(4, 'ASSI/003', 'Assignment III', '68a8bc5c84338.pdf', '2025-08-23 00:25:00', '2025-08-30 00:25:00', '2025-08-23 00:22:12', 2, 1, 0, 2, NULL, NULL, 2);

-- Dumping structure for table lms_enviiz_latest_no_data.assignments_logs
CREATE TABLE IF NOT EXISTS `assignments_logs` (
  `assignments_logs_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `assignments_assignment_id` int NOT NULL,
  `log` varchar(45) NOT NULL,
  `date_time` datetime NOT NULL,
  PRIMARY KEY (`assignments_logs_id`),
  KEY `fk_users_has_assignments_assignments1_idx` (`assignments_assignment_id`),
  KEY `fk_users_has_assignments_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_users_has_assignments_assignments1` FOREIGN KEY (`assignments_assignment_id`) REFERENCES `assignments` (`assignment_id`),
  CONSTRAINT `fk_users_has_assignments_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignments_logs: ~4 rows (approximately)
INSERT INTO `assignments_logs` (`assignments_logs_id`, `users_user_id`, `assignments_assignment_id`, `log`, `date_time`) VALUES
	(1, 3, 1, 'Add Assignmnet', '2025-06-26 01:53:44'),
	(2, 3, 2, 'Add Assignmnet', '2025-06-26 01:55:14'),
	(3, 3, 3, 'Add Assignmnet', '2025-06-26 02:44:40'),
	(4, 2, 4, 'Add Assignmnet', '2025-08-23 00:22:12'),
	(5, 3, 1, 'Update Assignmnet', '2025-11-07 12:39:00');

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_marking_log
CREATE TABLE IF NOT EXISTS `assignment_marking_log` (
  `assignment_marking_log_id` int NOT NULL AUTO_INCREMENT,
  `marking_log` varchar(45) NOT NULL,
  `date_added` datetime NOT NULL,
  `lecturers_has_students_has_assignments_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`assignment_marking_log_id`),
  KEY `fk_assignment_marking_log_lecturers_has_students_has_assign_idx` (`lecturers_has_students_has_assignments_id`),
  KEY `fk_assignment_marking_log_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_assignment_marking_log_lecturers_has_students_has_assignme1` FOREIGN KEY (`lecturers_has_students_has_assignments_id`) REFERENCES `lecturers_has_students_has_assignments` (`lecturers_has_students_has_assignments_id`),
  CONSTRAINT `fk_assignment_marking_log_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_marking_log: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_marks_status
CREATE TABLE IF NOT EXISTS `assignment_marks_status` (
  `assignment_marks_status_id` int NOT NULL AUTO_INCREMENT,
  `assignment_marks_status` varchar(45) NOT NULL,
  PRIMARY KEY (`assignment_marks_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_marks_status: ~5 rows (approximately)
INSERT INTO `assignment_marks_status` (`assignment_marks_status_id`, `assignment_marks_status`) VALUES
	(1, 'Pending'),
	(2, 'Marking Assigned'),
	(3, 'Pending Approval'),
	(4, 'Mark Released'),
	(5, 'Recorrection Applied');

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_payments
CREATE TABLE IF NOT EXISTS `assignment_payments` (
  `assignment_payments_id` int NOT NULL AUTO_INCREMENT,
  `lecturers_lecturers_id` int NOT NULL,
  `assignments_assignment_id` int NOT NULL,
  `amount` double NOT NULL,
  `earning_status_earning_status_id` int NOT NULL,
  `assignment_payment_type_id` int NOT NULL,
  PRIMARY KEY (`assignment_payments_id`),
  KEY `fk_lecturers_has_assignments_assignments1_idx` (`assignments_assignment_id`),
  KEY `fk_lecturers_has_assignments_lecturers1_idx` (`lecturers_lecturers_id`),
  KEY `fk_assignment_payments_earning_status1_idx` (`earning_status_earning_status_id`),
  KEY `FK_assignment_payments_assignment_payment_type` (`assignment_payment_type_id`),
  CONSTRAINT `FK_assignment_payments_assignment_payment_type` FOREIGN KEY (`assignment_payment_type_id`) REFERENCES `assignment_payment_type` (`assignment_payment_type_id`),
  CONSTRAINT `fk_assignment_payments_earning_status1` FOREIGN KEY (`earning_status_earning_status_id`) REFERENCES `earning_status` (`earning_status_id`),
  CONSTRAINT `fk_lecturers_has_assignments_assignments1` FOREIGN KEY (`assignments_assignment_id`) REFERENCES `assignments` (`assignment_id`),
  CONSTRAINT `fk_lecturers_has_assignments_lecturers1` FOREIGN KEY (`lecturers_lecturers_id`) REFERENCES `lecturers` (`lecturers_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_payments: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_payments_logs
CREATE TABLE IF NOT EXISTS `assignment_payments_logs` (
  `assignment_payments_logs_id` int NOT NULL AUTO_INCREMENT,
  `note` varchar(45) NOT NULL,
  `date_updated` datetime NOT NULL,
  `assignment_payments_assignment_payments_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`assignment_payments_logs_id`),
  KEY `fk_assignment_payments_logs_users1_idx` (`users_user_id`),
  KEY `fk_assignment_payments_logs_lecturer_payments1_idx` (`assignment_payments_assignment_payments_id`) USING BTREE,
  CONSTRAINT `FK_assignment_payments_logs_assignment_payments` FOREIGN KEY (`assignment_payments_assignment_payments_id`) REFERENCES `assignment_payments` (`assignment_payments_id`),
  CONSTRAINT `fk_assignment_payments_logs_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_payments_logs: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_payment_type
CREATE TABLE IF NOT EXISTS `assignment_payment_type` (
  `assignment_payment_type_id` int NOT NULL AUTO_INCREMENT,
  `assignment_payment_type` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`assignment_payment_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_payment_type: ~2 rows (approximately)
INSERT INTO `assignment_payment_type` (`assignment_payment_type_id`, `assignment_payment_type`) VALUES
	(1, 'Direct'),
	(2, 'Re-Correction');

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_recorrection
CREATE TABLE IF NOT EXISTS `assignment_recorrection` (
  `recorrection_id` int NOT NULL AUTO_INCREMENT,
  `students_has_assignments_students_has_assignments_id` int NOT NULL,
  `marks` double DEFAULT NULL,
  `lecturers_lecturers_id` int NOT NULL,
  `submission_sts_submission_sts_id` int NOT NULL,
  PRIMARY KEY (`recorrection_id`),
  KEY `fk_students_has_assignments_has_recorrection_fee_students_h_idx` (`students_has_assignments_students_has_assignments_id`),
  KEY `fk_recorrection_lecturers1_idx` (`lecturers_lecturers_id`),
  KEY `fk_recorrection_submission_sts1_idx` (`submission_sts_submission_sts_id`),
  CONSTRAINT `fk_recorrection_lecturers1` FOREIGN KEY (`lecturers_lecturers_id`) REFERENCES `lecturers` (`lecturers_id`),
  CONSTRAINT `fk_recorrection_submission_sts1` FOREIGN KEY (`submission_sts_submission_sts_id`) REFERENCES `submission_sts` (`submission_sts_id`),
  CONSTRAINT `fk_students_has_assignments_has_recorrection_fee_students_has1` FOREIGN KEY (`students_has_assignments_students_has_assignments_id`) REFERENCES `students_has_assignments` (`students_has_assignments_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_recorrection: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_recorrection_fee
CREATE TABLE IF NOT EXISTS `assignment_recorrection_fee` (
  `recorrection_fee_id` int NOT NULL AUTO_INCREMENT,
  `recorrection_fee` double NOT NULL,
  `assignments_assignment_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`recorrection_fee_id`),
  KEY `fk_recorrection_fee_assignments1_idx` (`assignments_assignment_id`),
  KEY `fk_recorrection_fee_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_recorrection_fee_assignments1` FOREIGN KEY (`assignments_assignment_id`) REFERENCES `assignments` (`assignment_id`),
  CONSTRAINT `fk_recorrection_fee_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_recorrection_fee: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_recorrection_log
CREATE TABLE IF NOT EXISTS `assignment_recorrection_log` (
  `recorrection_log_id` int NOT NULL AUTO_INCREMENT,
  `recorrection_log` varchar(45) NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  `recorrection_recorrection_id` int NOT NULL,
  PRIMARY KEY (`recorrection_log_id`),
  KEY `fk_recorrection_log_users1_idx` (`users_user_id`),
  KEY `fk_recorrection_log_recorrection1_idx` (`recorrection_recorrection_id`),
  CONSTRAINT `fk_recorrection_log_recorrection1` FOREIGN KEY (`recorrection_recorrection_id`) REFERENCES `assignment_recorrection` (`recorrection_id`),
  CONSTRAINT `fk_recorrection_log_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_recorrection_log: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_recorrection_payment
CREATE TABLE IF NOT EXISTS `assignment_recorrection_payment` (
  `assignment_recorrection_payment_id` int NOT NULL AUTO_INCREMENT,
  `date_paid` datetime NOT NULL,
  `refference` varchar(45) NOT NULL,
  `payment_receipt` varchar(45) NOT NULL,
  `amount` double NOT NULL,
  `students_has_assignments_students_has_assignments_id` int NOT NULL,
  `payment_method_payment_method_id` int NOT NULL,
  `payment_status_payment_status_id` int NOT NULL,
  `bank_bank_id` int NOT NULL,
  PRIMARY KEY (`assignment_recorrection_payment_id`),
  KEY `fk_assignment_recorrection_payment_students_has_assignments_idx` (`students_has_assignments_students_has_assignments_id`),
  KEY `fk_assignment_recorrection_payment_payment_method1_idx` (`payment_method_payment_method_id`),
  KEY `fk_assignment_recorrection_payment_payment_status1_idx` (`payment_status_payment_status_id`),
  KEY `fk_assignment_recorrection_payment_bank1_idx` (`bank_bank_id`),
  CONSTRAINT `fk_assignment_recorrection_payment_bank1` FOREIGN KEY (`bank_bank_id`) REFERENCES `bank` (`bank_id`),
  CONSTRAINT `fk_assignment_recorrection_payment_payment_method1` FOREIGN KEY (`payment_method_payment_method_id`) REFERENCES `payment_method` (`payment_method_id`),
  CONSTRAINT `fk_assignment_recorrection_payment_payment_status1` FOREIGN KEY (`payment_status_payment_status_id`) REFERENCES `payment_status` (`payment_status_id`),
  CONSTRAINT `fk_assignment_recorrection_payment_students_has_assignments1` FOREIGN KEY (`students_has_assignments_students_has_assignments_id`) REFERENCES `students_has_assignments` (`students_has_assignments_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_recorrection_payment: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_recorrection_payment_log
CREATE TABLE IF NOT EXISTS `assignment_recorrection_payment_log` (
  `assignment_recorrection_payment_log_id` int NOT NULL AUTO_INCREMENT,
  `date_updated` datetime NOT NULL,
  `note` text NOT NULL,
  `assignment_recorrection_payment_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`assignment_recorrection_payment_log_id`),
  KEY `fk_assignment_recorrection_payment_log_assignment_recorrect_idx` (`assignment_recorrection_payment_id`),
  KEY `fk_assignment_recorrection_payment_log_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_assignment_recorrection_payment_log_assignment_recorrectio1` FOREIGN KEY (`assignment_recorrection_payment_id`) REFERENCES `assignment_recorrection_payment` (`assignment_recorrection_payment_id`),
  CONSTRAINT `fk_assignment_recorrection_payment_log_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_recorrection_payment_log: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_status
CREATE TABLE IF NOT EXISTS `assignment_status` (
  `assignment_status_id` int NOT NULL AUTO_INCREMENT,
  `assignment_status` varchar(45) NOT NULL,
  PRIMARY KEY (`assignment_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_status: ~5 rows (approximately)
INSERT INTO `assignment_status` (`assignment_status_id`, `assignment_status`) VALUES
	(1, 'Not Schedule'),
	(2, 'Schedule'),
	(3, 'Cancelled'),
	(4, 'Recorrection Enabled'),
	(5, 'Late Submission Enabled');

-- Dumping structure for table lms_enviiz_latest_no_data.assignment_submission
CREATE TABLE IF NOT EXISTS `assignment_submission` (
  `submission_id` int NOT NULL AUTO_INCREMENT,
  `submission` varchar(45) NOT NULL,
  PRIMARY KEY (`submission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.assignment_submission: ~2 rows (approximately)
INSERT INTO `assignment_submission` (`submission_id`, `submission`) VALUES
	(1, 'On-Time'),
	(2, 'Late'),
	(3, 'Repeat');

-- Dumping structure for table lms_enviiz_latest_no_data.attachments
CREATE TABLE IF NOT EXISTS `attachments` (
  `attachment_id` int NOT NULL AUTO_INCREMENT,
  `attachment_name` varchar(45) NOT NULL,
  `attachment` varchar(45) NOT NULL,
  `time_table_time_table_id` int NOT NULL,
  `attachments_order` int NOT NULL,
  `users_user_id` int NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`attachment_id`),
  KEY `fk_attachments_time_table1_idx` (`time_table_time_table_id`),
  KEY `fk_attachments_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_attachments_time_table1` FOREIGN KEY (`time_table_time_table_id`) REFERENCES `time_table` (`time_table_id`),
  CONSTRAINT `fk_attachments_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.attachments: ~8 rows (approximately)
INSERT INTO `attachments` (`attachment_id`, `attachment_name`, `attachment`, `time_table_time_table_id`, `attachments_order`, `users_user_id`, `date_added`) VALUES
	(14, 'ENVIIZ-NEW.pdf', '68f3d77648891.pdf', 11, 0, 3, '2025-10-18 23:37:50'),
	(16, 'Doc1.pdf', '68f3de1a10183.pdf', 22, 0, 3, '2025-10-19 00:06:10'),
	(17, 'ITSE-Form-18.pdf', '68f3de1a10188.pdf', 22, 0, 3, '2025-10-19 00:06:10'),
	(18, 'Export-251016-192929.pdf', '68f5d768307e3.pdf', 23, 0, 3, '2025-10-20 12:02:08'),
	(19, 'ENVIIZ-NEW.pdf', '68f5d768307ea.pdf', 23, 0, 3, '2025-10-20 12:02:08'),
	(20, 'Application_Filled (1).pdf', '68f5d768307ec.pdf', 23, 0, 3, '2025-10-20 12:02:08'),
	(21, 'Export-251016-192929.pdf', '68f6470f8d0bf.pdf', 24, 0, 3, '2025-10-20 19:58:31'),
	(22, 'Application_Filled.pdf', '68f9d6450bb34.pdf', 27, 0, 3, '2025-10-23 12:46:21');

-- Dumping structure for table lms_enviiz_latest_no_data.attendance
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `time_table_time_table_id` int NOT NULL,
  `attendance_status_attendance_status_id` int NOT NULL,
  `updated_at` datetime NOT NULL,
  `users_user_id` int DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  KEY `fk_students_has_time_table_time_table1_idx` (`time_table_time_table_id`),
  KEY `fk_students_has_time_table_students1_idx` (`students_student_id`),
  KEY `fk_attendance_attendance_status1_idx` (`attendance_status_attendance_status_id`),
  KEY `fk_attendance_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_attendance_attendance_status1` FOREIGN KEY (`attendance_status_attendance_status_id`) REFERENCES `attendance_status` (`attendance_status_id`),
  CONSTRAINT `fk_attendance_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_students_has_time_table_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`),
  CONSTRAINT `fk_students_has_time_table_time_table1` FOREIGN KEY (`time_table_time_table_id`) REFERENCES `time_table` (`time_table_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.attendance: ~4 rows (approximately)
INSERT INTO `attendance` (`attendance_id`, `students_student_id`, `time_table_time_table_id`, `attendance_status_attendance_status_id`, `updated_at`, `users_user_id`) VALUES
	(10, 8, 33, 2, '2025-10-29 11:47:55', 3),
	(11, 7, 33, 2, '2025-10-29 11:54:50', 3),
	(12, 6, 4, 4, '2025-10-30 18:53:03', NULL),
	(13, 6, 5, 4, '2025-10-30 18:53:11', NULL);

-- Dumping structure for table lms_enviiz_latest_no_data.attendance_status
CREATE TABLE IF NOT EXISTS `attendance_status` (
  `attendance_status_id` int NOT NULL AUTO_INCREMENT,
  `attendance_status` varchar(45) NOT NULL,
  PRIMARY KEY (`attendance_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.attendance_status: ~4 rows (approximately)
INSERT INTO `attendance_status` (`attendance_status_id`, `attendance_status`) VALUES
	(1, 'Half Verify'),
	(2, 'Present'),
	(3, 'Absent'),
	(4, 'Watched');

-- Dumping structure for table lms_enviiz_latest_no_data.bank
CREATE TABLE IF NOT EXISTS `bank` (
  `bank_id` int NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(45) NOT NULL,
  `acc_no` varchar(45) NOT NULL,
  `acc_name` varchar(45) NOT NULL,
  `branch` varchar(45) NOT NULL,
  `logo` varchar(45) NOT NULL,
  `users_user_id` int NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`bank_id`),
  KEY `fk_bank_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_bank_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.bank: ~0 rows (approximately)
INSERT INTO `bank` (`bank_id`, `bank_name`, `acc_no`, `acc_name`, `branch`, `logo`, `users_user_id`, `date_added`) VALUES
	(1, 'Commercial Bank', '12345678910', 'ENVIIZ', 'Colombo', '68d63fa92e011.png', 2, '2025-09-26 12:54:25');

-- Dumping structure for table lms_enviiz_latest_no_data.bank_account
CREATE TABLE IF NOT EXISTS `bank_account` (
  `bank_account_id` int NOT NULL AUTO_INCREMENT,
  `account_name` varchar(45) NOT NULL,
  `account_number` varchar(45) NOT NULL,
  `branch` varchar(45) NOT NULL,
  `bank` varchar(45) NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`bank_account_id`),
  KEY `fk_bank_account_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_bank_account_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.bank_account: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.city
CREATE TABLE IF NOT EXISTS `city` (
  `city_id` int NOT NULL AUTO_INCREMENT,
  `city` varchar(45) NOT NULL,
  `district_district_id` int NOT NULL,
  PRIMARY KEY (`city_id`),
  KEY `fk_city_district1_idx` (`district_district_id`),
  CONSTRAINT `fk_city_district1` FOREIGN KEY (`district_district_id`) REFERENCES `district` (`district_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1991 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.city: ~1,990 rows (approximately)
INSERT INTO `city` (`city_id`, `city`, `district_district_id`) VALUES
	(1, 'Addalaichenai', 1),
	(2, 'Akkaraipattu', 1),
	(3, 'Ampara', 1),
	(4, 'Bakmitiyawa', 1),
	(5, 'Central Camp', 1),
	(6, 'Dadayamtalawa', 1),
	(7, 'Damana', 1),
	(8, 'Damanewela', 1),
	(9, 'Deegawapiya', 1),
	(10, 'Dehiattakandiya', 1),
	(11, 'Devalahinda', 1),
	(12, 'Digamadulla Weeragoda', 1),
	(13, 'Dorakumbura', 1),
	(14, 'Galapitagala', 1),
	(15, 'Gonagolla', 1),
	(16, 'Hingurana', 1),
	(17, 'Hulannuge', 1),
	(18, 'Kalmunai', 1),
	(19, 'Kannakipuram', 1),
	(20, 'Karativu', 1),
	(21, 'Kekirihena', 1),
	(22, 'Koknahara', 1),
	(23, 'Kolamanthalawa', 1),
	(24, 'Komari', 1),
	(25, 'Lahugala', 1),
	(26, 'lmkkamam', 1),
	(27, 'Madawalalanda', 1),
	(28, 'Mahanagapura', 1),
	(29, 'Mahaoya', 1),
	(30, 'Malwatta', 1),
	(31, 'Mangalagama', 1),
	(32, 'Marathamune', 1),
	(33, 'Mawanagama', 1),
	(34, 'Moragahapallama', 1),
	(35, 'Namaloya', 1),
	(36, 'Navithanveli', 1),
	(37, 'Nawamedagama', 1),
	(38, 'Nintavur', 1),
	(39, 'Oluvil', 1),
	(40, 'Padiyatalawa', 1),
	(41, 'Pahalalanda', 1),
	(42, 'Palamunai', 1),
	(43, 'Panama', 1),
	(44, 'Pannalagama', 1),
	(45, 'Paragahakele', 1),
	(46, 'Periyaneelavanai', 1),
	(47, 'Polwaga Janapadaya', 1),
	(48, 'Pottuvil', 1),
	(49, 'Rajagalatenna', 1),
	(50, 'Sainthamaruthu', 1),
	(51, 'Samanthurai', 1),
	(52, 'Serankada', 1),
	(53, 'Siripura', 1),
	(54, 'Siyambalawewa', 1),
	(55, 'Tempitiya', 1),
	(56, 'Thambiluvil', 1),
	(57, 'Tirukovil', 1),
	(58, 'Uhana', 1),
	(59, 'Wadinagala', 1),
	(60, 'Wanagamuwa', 1),
	(61, 'Werunketagoda', 1),
	(62, 'Andiyagala', 2),
	(63, 'Angamuwa', 2),
	(64, 'Anuradhapura', 2),
	(65, 'Awukana', 2),
	(66, 'Bogahawewa', 2),
	(67, 'Dematawewa', 2),
	(68, 'Dunumadalawa', 2),
	(69, 'Dutuwewa', 2),
	(70, 'Elayapattuwa', 2),
	(71, 'Eppawala', 2),
	(72, 'Etawatunuwewa', 2),
	(73, 'Etaweeragollewa', 2),
	(74, 'Galadivulwewa', 2),
	(75, 'Galenbindunuwewa', 2),
	(76, 'Galkadawala', 2),
	(77, 'Galkiriyagama', 2),
	(78, 'Galkulama', 2),
	(79, 'Galnewa', 2),
	(80, 'Gambirigaswewa', 2),
	(81, 'Ganewalpola', 2),
	(82, 'Gemunupura', 2),
	(83, 'Getalawa', 2),
	(84, 'Gnanikulama', 2),
	(85, 'Gonahaddenawa', 2),
	(86, 'Habarana', 2),
	(87, 'Halmillawa Dambulla', 2),
	(88, 'Halmillawetiya', 2),
	(89, 'Hidogama', 2),
	(90, 'Horawpatana', 2),
	(91, 'Horiwila', 2),
	(92, 'Hurigaswewa', 2),
	(93, 'Hurulunikawewa', 2),
	(94, 'Kagama', 2),
	(95, 'Kahatagasdigiliya', 2),
	(96, 'Kahatagollewa', 2),
	(97, 'Kalakarambewa', 2),
	(98, 'Kalankuttiya', 2),
	(99, 'Kalaoya', 2),
	(100, 'Kalawedi Ulpotha', 2),
	(101, 'Kallanchiya', 2),
	(102, 'Kapugallawa', 2),
	(103, 'Karagahawewa', 2),
	(104, 'Katiyawa', 2),
	(105, 'Kebithigollewa', 2),
	(106, 'Kekirawa', 2),
	(107, 'Kendewa', 2),
	(108, 'Kiralogama', 2),
	(109, 'Kirigalwewa', 2),
	(110, 'Kitulhitiyawa', 2),
	(111, 'Kurundankulama', 2),
	(112, 'Labunoruwa', 2),
	(113, 'lhala Halmillewa', 2),
	(114, 'lhalagama', 2),
	(115, 'lpologama', 2),
	(116, 'Madatugama', 2),
	(117, 'Maha Elagamuwa', 2),
	(118, 'Mahabulankulama', 2),
	(119, 'Mahailluppallama', 2),
	(120, 'Mahakanadarawa', 2),
	(121, 'Mahapothana', 2),
	(122, 'Mahasenpura', 2),
	(123, 'Mahawilachchiya', 2),
	(124, 'Mailagaswewa', 2),
	(125, 'Malwanagama', 2),
	(126, 'Maneruwa', 2),
	(127, 'Maradankadawala', 2),
	(128, 'Maradankalla', 2),
	(129, 'Medawachchiya', 2),
	(130, 'Megodawewa', 2),
	(131, 'Mihintale', 2),
	(132, 'Morakewa', 2),
	(133, 'Mulkiriyawa', 2),
	(134, 'Muriyakadawala', 2),
	(135, 'Nachchaduwa', 2),
	(136, 'Namalpura', 2),
	(137, 'Negampaha', 2),
	(138, 'Nochchiyagama', 2),
	(139, 'Padavi Maithripura', 2),
	(140, 'Padavi Parakramapura', 2),
	(141, 'Padavi Sripura', 2),
	(142, 'Padavi Sritissapura', 2),
	(143, 'Padaviya', 2),
	(144, 'Padikaramaduwa', 2),
	(145, 'Pahala Halmillewa', 2),
	(146, 'Pahala Maragahawe', 2),
	(147, 'Pahalagama', 2),
	(148, 'Palagala', 2),
	(149, 'Palugaswewa', 2),
	(150, 'Pandukabayapura', 2),
	(151, 'Pandulagama', 2),
	(152, 'Parakumpura', 2),
	(153, 'Parangiyawadiya', 2),
	(154, 'Parasangahawewa', 2),
	(155, 'Pemaduwa', 2),
	(156, 'Perimiyankulama', 2),
	(157, 'Pihimbiyagolewa', 2),
	(158, 'Pubbogama', 2),
	(159, 'Pulmoddai', 2),
	(160, 'Punewa', 2),
	(161, 'Rajanganaya', 2),
	(162, 'Rambewa', 2),
	(163, 'Rampathwila', 2),
	(164, 'Ranorawa', 2),
	(165, 'Rathmalgahawewa', 2),
	(166, 'Saliyapura', 2),
	(167, 'Seeppukulama', 2),
	(168, 'Senapura', 2),
	(169, 'Sivalakulama', 2),
	(170, 'Siyambalewa', 2),
	(171, 'Sravasthipura', 2),
	(172, 'Talawa', 2),
	(173, 'Tambuttegama', 2),
	(174, 'Tammennawa', 2),
	(175, 'Tantirimale', 2),
	(176, 'Telhiriyawa', 2),
	(177, 'Tennamarawadiya', 2),
	(178, 'Tirappane', 2),
	(179, 'Tittagonewa', 2),
	(180, 'Udunuwara Colony', 2),
	(181, 'Upuldeniya', 2),
	(182, 'Uttimaduwa', 2),
	(183, 'Viharapalugama', 2),
	(184, 'Vijithapura', 2),
	(185, 'Wahalkada', 2),
	(186, 'Wahamalgollewa', 2),
	(187, 'Walagambahuwa', 2),
	(188, 'Walahaviddawewa', 2),
	(189, 'Welimuwapotana', 2),
	(190, 'Welioya Project', 2),
	(191, 'Akkarasiyaya', 3),
	(192, 'Aluketiyawa', 3),
	(193, 'Aluttaramma', 3),
	(194, 'Ambadandegama', 3),
	(195, 'Ambagahawatta', 3),
	(196, 'Ambagasdowa', 3),
	(197, 'Amunumulla', 3),
	(198, 'Arawa', 3),
	(199, 'Arawakumbura', 3),
	(200, 'Arawatta', 3),
	(201, 'Atakiriya', 3),
	(202, 'Badulla', 3),
	(203, 'Baduluoya', 3),
	(204, 'Ballaketuwa', 3),
	(205, 'Bambarapana', 3),
	(206, 'Bandarawela', 3),
	(207, 'Beramada', 3),
	(208, 'Bibilegama', 3),
	(209, 'Bogahakumbura', 3),
	(210, 'Boragas', 3),
	(211, 'Boralanda', 3),
	(212, 'Bowela', 3),
	(213, 'Dambana', 3),
	(214, 'Demodara', 3),
	(215, 'Diganatenna', 3),
	(216, 'Dikkapitiya', 3),
	(217, 'Dimbulana', 3),
	(218, 'Divulapelessa', 3),
	(219, 'Diyatalawa', 3),
	(220, 'Dulgolla', 3),
	(221, 'Egodawela', 3),
	(222, 'Ella', 3),
	(223, 'Ettampitiya', 3),
	(224, 'Galauda', 3),
	(225, 'Galedanda', 3),
	(226, 'Galporuyaya', 3),
	(227, 'Gamewela', 3),
	(228, 'Gawarawela', 3),
	(229, 'Girandurukotte', 3),
	(230, 'Godunna', 3),
	(231, 'Gurutalawa', 3),
	(232, 'Haldummulla', 3),
	(233, 'Hali Ela', 3),
	(234, 'Hangunnawa', 3),
	(235, 'Haputale', 3),
	(236, 'Hebarawa', 3),
	(237, 'Heeloya', 3),
	(238, 'Helahalpe', 3),
	(239, 'Helapupula', 3),
	(240, 'Hewanakumbura', 3),
	(241, 'Hingurukaduwa', 3),
	(242, 'Hopton', 3),
	(243, 'Idalgashinna', 3),
	(244, 'Jangulla', 3),
	(245, 'Kahataruppa', 3),
	(246, 'Kalubululanda', 3),
	(247, 'Kalugahakandura', 3),
	(248, 'Kalupahana', 3),
	(249, 'Kandaketya', 3),
	(250, 'Kandegedara', 3),
	(251, 'Kandepuhulpola', 3),
	(252, 'Kebillawela', 3),
	(253, 'Kendagolla', 3),
	(254, 'Keppetipola', 3),
	(255, 'Keselpotha', 3),
	(256, 'Ketawatta', 3),
	(257, 'Kiriwanagama', 3),
	(258, 'Koslanda', 3),
	(259, 'Kotamuduna', 3),
	(260, 'Kuruwitenna', 3),
	(261, 'Kuttiyagolla', 3),
	(262, 'Landewela', 3),
	(263, 'Liyangahawela', 3),
	(264, 'Lunugala', 3),
	(265, 'Lunuwatta', 3),
	(266, 'Madulsima', 3),
	(267, 'Mahiyanganaya', 3),
	(268, 'Makulella', 3),
	(269, 'Malgoda', 3),
	(270, 'Maliyadda', 3),
	(271, 'Mapakadawewa', 3),
	(272, 'Maspanna', 3),
	(273, 'Maussagolla', 3),
	(274, 'Medawela Udukinda', 3),
	(275, 'Medawelagama', 3),
	(276, 'Meegahakiula', 3),
	(277, 'Metigahatenna', 3),
	(278, 'Mirahawatta', 3),
	(279, 'Miriyabedda', 3),
	(280, 'Miyanakandura', 3),
	(281, 'Namunukula', 3),
	(282, 'Narangala', 3),
	(283, 'Nelumgama', 3),
	(284, 'Nikapotha', 3),
	(285, 'Nugatalawa', 3),
	(286, 'Ohiya', 3),
	(287, 'Pahalarathkinda', 3),
	(288, 'Pallekiruwa', 3),
	(289, 'Passara', 3),
	(290, 'Pathanewatta', 3),
	(291, 'Pattiyagedara', 3),
	(292, 'Pelagahatenna', 3),
	(293, 'Perawella', 3),
	(294, 'Pitamaruwa', 3),
	(295, 'Pitapola', 3),
	(296, 'Puhulpola', 3),
	(297, 'Ratkarawwa', 3),
	(298, 'Ridimaliyadda', 3),
	(299, 'Rilpola', 3),
	(300, 'Silmiyapura', 3),
	(301, 'Sirimalgoda', 3),
	(302, 'Sorabora Colony', 3),
	(303, 'Soragune', 3),
	(304, 'Soranatota', 3),
	(305, 'Spring Valley', 3),
	(306, 'Taldena', 3),
	(307, 'Tennepanguwa', 3),
	(308, 'Timbirigaspitiya', 3),
	(309, 'Uduhawara', 3),
	(310, 'Uraniya', 3),
	(311, 'Uva Deegalla', 3),
	(312, 'Uva Karandagolla', 3),
	(313, 'Uva Mawelagama', 3),
	(314, 'Uva Tenna', 3),
	(315, 'Uva Tissapura', 3),
	(316, 'Uva Uduwara', 3),
	(317, 'Uvaparanagama', 3),
	(318, 'Welimada', 3),
	(319, 'Wewatta', 3),
	(320, 'Wineethagama', 3),
	(321, 'Yalagamuwa', 3),
	(322, 'Yalwela', 3),
	(323, 'Ampilanthurai', 4),
	(324, 'Araipattai', 4),
	(325, 'Ayithiyamalai', 4),
	(326, 'Bakiella', 4),
	(327, 'Batticaloa', 4),
	(328, 'Cheddipalayam', 4),
	(329, 'Chenkaladi', 4),
	(330, 'Eravur', 4),
	(331, 'Kalkudah', 4),
	(332, 'Kallar', 4),
	(333, 'Kaluwanchikudi', 4),
	(334, 'Kaluwankemy', 4),
	(335, 'Kannankudah', 4),
	(336, 'Karadiyanaru', 4),
	(337, 'Kathiraveli', 4),
	(338, 'Kattankudi', 4),
	(339, 'Kiran', 4),
	(340, 'Kirankulam', 4),
	(341, 'Koddaikallar', 4),
	(342, 'Kokkaddichcholai', 4),
	(343, 'Kurukkalmadam', 4),
	(344, 'Mandur', 4),
	(345, 'Mankemi', 4),
	(346, 'Miravodai', 4),
	(347, 'Murakottanchanai', 4),
	(348, 'Navagirinagar', 4),
	(349, 'Navatkadu', 4),
	(350, 'Oddamavadi', 4),
	(351, 'Panichankemi', 4),
	(352, 'Pankudavely', 4),
	(353, 'Periyaporativu', 4),
	(354, 'Periyapullumalai', 4),
	(355, 'Pillaiyaradi', 4),
	(356, 'Punanai', 4),
	(357, 'Puthukudiyiruppu', 4),
	(358, 'Thannamunai', 4),
	(359, 'Thettativu', 4),
	(360, 'Thikkodai', 4),
	(361, 'Thirupalugamam', 4),
	(362, 'Thuraineelavanai', 4),
	(363, 'Unnichchai', 4),
	(364, 'Vakaneri', 4),
	(365, 'Vakarai', 4),
	(366, 'Valaichenai', 4),
	(367, 'Vantharumoolai', 4),
	(368, 'Vellavely', 4),
	(369, 'Colombo 01', 5),
	(370, 'Colombo 02', 5),
	(371, 'Colombo 03', 5),
	(372, 'Colombo 04', 5),
	(373, 'Colombo 05', 5),
	(374, 'Colombo 06', 5),
	(375, 'Colombo 07', 5),
	(376, 'Colombo 08', 5),
	(377, 'Colombo 09', 5),
	(378, 'Colombo 10', 5),
	(379, 'Colombo 11', 5),
	(380, 'Colombo 12', 5),
	(381, 'Colombo 13', 5),
	(382, 'Colombo 14', 5),
	(383, 'Colombo 15', 5),
	(384, 'Akarawita', 5),
	(385, 'Angoda', 5),
	(386, 'Arangala', 5),
	(387, 'Athurugiriya', 5),
	(388, 'Avissawella', 5),
	(389, 'Bambalapitiya', 5),
	(390, 'Batawala', 5),
	(391, 'Battaramulla', 5),
	(392, 'Batugampola', 5),
	(393, 'Bope', 5),
	(394, 'Boralesgamuwa', 5),
	(395, 'Borella', 5),
	(396, 'Dedigamuwa', 5),
	(397, 'Dehiwala', 5),
	(398, 'Deltara', 5),
	(399, 'Embuldeniya', 5),
	(400, 'Gongodawila', 5),
	(401, 'Habarakada', 5),
	(402, 'Handapangoda', 5),
	(403, 'Hanwella', 5),
	(404, 'Hewainna', 5),
	(405, 'Hiripitya', 5),
	(406, 'Hokandara', 5),
	(407, 'Homagama', 5),
	(408, 'Horagala', 5),
	(409, 'Kaduwela', 5),
	(410, 'Kahawala', 5),
	(411, 'Kalatuwawa', 5),
	(412, 'Kalubowila', 5),
	(413, 'Kiriwattuduwa', 5),
	(414, 'Kohuwala', 5),
	(415, 'Kolonnawa', 5),
	(416, 'Kosgama', 5),
	(417, 'Kotahena', 5),
	(418, 'Kotikawatta', 5),
	(419, 'Kottawa', 5),
	(420, 'Madapatha', 5),
	(421, 'Maharagama', 5),
	(422, 'Malabe', 5),
	(423, 'Meegoda', 5),
	(424, 'Moratuwa', 5),
	(425, 'Mount Lavinia', 5),
	(426, 'Mullegama', 5),
	(427, 'Mulleriyawa', 5),
	(428, 'Mutwal', 5),
	(429, 'Napawela', 5),
	(430, 'Narahenpita', 5),
	(431, 'Nugegoda', 5),
	(432, 'Padukka', 5),
	(433, 'Pannipitiya', 5),
	(434, 'Piliyandala', 5),
	(435, 'Pita Kotte', 5),
	(436, 'Pitipana Homagama', 5),
	(437, 'Polgasowita', 5),
	(438, 'Puwakpitiya', 5),
	(439, 'Rajagiriya', 5),
	(440, 'Ranala', 5),
	(441, 'Ratmalana', 5),
	(442, 'Siddamulla', 5),
	(443, 'Sri Jayewardenepura', 5),
	(444, 'Talawatugoda', 5),
	(445, 'Tummodara', 5),
	(446, 'Waga', 5),
	(447, 'Watareka', 5),
	(448, 'Wijerama', 5),
	(449, 'Agaliya', 6),
	(450, 'Ahangama', 6),
	(451, 'Ahungalla', 6),
	(452, 'Akmeemana', 6),
	(453, 'Aluthwala', 6),
	(454, 'Ambalangoda', 6),
	(455, 'Ampegama', 6),
	(456, 'Amugoda', 6),
	(457, 'Anangoda', 6),
	(458, 'Angulugaha', 6),
	(459, 'Ankokkawala', 6),
	(460, 'Baddegama', 6),
	(461, 'Balapitiya', 6),
	(462, 'Banagala', 6),
	(463, 'Batapola', 6),
	(464, 'Bentota', 6),
	(465, 'Boossa', 6),
	(466, 'Dikkumbura', 6),
	(467, 'Dodanduwa', 6),
	(468, 'Ella', 6),
	(469, 'Tanabaddegama', 6),
	(470, 'Elpitiya', 6),
	(471, 'Ethkandura', 6),
	(472, 'Galle', 6),
	(473, 'Ganegoda', 6),
	(474, 'Ginimellagaha', 6),
	(475, 'Gintota', 6),
	(476, 'Godahena', 6),
	(477, 'Gonagalpura', 6),
	(478, 'Gonamulla Junction', 6),
	(479, 'Gonapinuwala', 6),
	(480, 'Habaraduwa', 6),
	(481, 'Haburugala', 6),
	(482, 'Halvitigala Colony', 6),
	(483, 'Hawpe', 6),
	(484, 'Hikkaduwa', 6),
	(485, 'Hiniduma', 6),
	(486, 'Hiyare', 6),
	(487, 'Ihala', 6),
	(488, 'Walpola', 6),
	(489, 'Kahaduwa', 6),
	(490, 'Kahawa', 6),
	(491, 'Kananke Bazaar', 6),
	(492, 'Karagoda', 6),
	(493, 'Karandeniya', 6),
	(494, 'Kosgoda', 6),
	(495, 'Kottawagama', 6),
	(496, 'Kuleegoda', 6),
	(497, 'lhalahewessa', 6),
	(498, 'lmaduwa', 6),
	(499, 'lnduruwa', 6),
	(500, 'Magedara', 6),
	(501, 'Malgalla Talangalla', 6),
	(502, 'Mapalagama', 6),
	(503, 'Mapalagama Central', 6),
	(504, 'Mattaka', 6),
	(505, 'Meda-Keembiya', 6),
	(506, 'Meetiyagoda', 6),
	(507, 'Miriswatta', 6),
	(508, 'Nagoda', 6),
	(509, 'Nakiyadeniya', 6),
	(510, 'Nawadagala', 6),
	(511, 'Neluwa', 6),
	(512, 'Nindana', 6),
	(513, 'Opatha', 6),
	(514, 'Panangala', 6),
	(515, 'Pannimulla Panagoda', 6),
	(516, 'Parana ThanaYamgoda', 6),
	(517, 'Pitigala', 6),
	(518, 'Poddala', 6),
	(519, 'Porawagama', 6),
	(520, 'Rantotuwila', 6),
	(521, 'Ratgama', 6),
	(522, 'Talagampola', 6),
	(523, 'Talgaspe', 6),
	(524, 'Talgaswela', 6),
	(525, 'Talpe', 6),
	(526, 'Tawalama', 6),
	(527, 'Tiranagama', 6),
	(528, 'Udalamatta', 6),
	(529, 'Udugama', 6),
	(530, 'Uluvitike', 6),
	(531, 'Unawatuna', 6),
	(532, 'Unenwitiya', 6),
	(533, 'Uragaha', 6),
	(534, 'Uragasmanhandiya', 6),
	(535, 'Wakwella', 6),
	(536, 'Walahanduwa', 6),
	(537, 'Wanchawela', 6),
	(538, 'Wanduramba', 6),
	(539, 'Warukandeniya', 6),
	(540, 'Watugedara', 6),
	(541, 'Weihena', 6),
	(542, 'Yakkalamulla', 6),
	(543, 'Yatalamatta', 6),
	(544, 'Akaragama', 7),
	(545, 'Alawala', 7),
	(546, 'Ambagaspitiya', 7),
	(547, 'Ambepussa', 7),
	(548, 'Andiambalama', 7),
	(549, 'Attanagalla', 7),
	(550, 'Badalgama', 7),
	(551, 'Banduragoda', 7),
	(552, 'Batuwatta', 7),
	(553, 'Bemmulla', 7),
	(554, 'Biyagama', 7),
	(555, 'Biyagama IPZ', 7),
	(556, 'Bokalagama', 7),
	(557, 'Bopagama', 7),
	(558, 'Buthpitiya', 7),
	(559, 'Dagonna', 7),
	(560, 'Danowita', 7),
	(561, 'Debahera', 7),
	(562, 'Dekatana', 7),
	(563, 'Delgoda', 7),
	(564, 'Delwagura', 7),
	(565, 'Demalagama', 7),
	(566, 'Demanhandiya', 7),
	(567, 'Dewalapola', 7),
	(568, 'Divulapitiya', 7),
	(569, 'Divuldeniya', 7),
	(570, 'Dompe', 7),
	(571, 'Dunagaha', 7),
	(572, 'Ekala', 7),
	(573, 'Ellakkala', 7),
	(574, 'Essella', 7),
	(575, 'Gampaha', 7),
	(576, 'Ganemulla', 7),
	(577, 'GonawalaWP', 7),
	(578, 'Heiyanthuduwa', 7),
	(579, 'Hendala', 7),
	(580, 'Henegama', 7),
	(581, 'Hinatiyana Madawala', 7),
	(582, 'Hiswella', 7),
	(583, 'Horampella', 7),
	(584, 'Hunumulla', 7),
	(585, 'Ihala Madampella', 7),
	(586, 'Imbulgoda', 7),
	(587, 'Ja-Ela', 7),
	(588, 'Kadawatha', 7),
	(589, 'Kahatowita', 7),
	(590, 'Kalagedihena', 7),
	(591, 'Kaleliya', 7),
	(592, 'Kaluaggala', 7),
	(593, 'Kandana', 7),
	(594, 'Kapugoda', 7),
	(595, 'Kapuwatta', 7),
	(596, 'Katana', 7),
	(597, 'Katunayake', 7),
	(598, 'Katunayake Air Force Camp', 7),
	(599, 'Katuwellegama', 7),
	(600, 'Kelaniya', 7),
	(601, 'Kimbulapitiya', 7),
	(602, 'Kiribathgoda', 7),
	(603, 'Kirindiwela', 7),
	(604, 'Kitalawalana', 7),
	(605, 'Kitulwala', 7),
	(606, 'Kochchikade', 7),
	(607, 'Kotadeniyawa', 7),
	(608, 'Kotugoda', 7),
	(609, 'Kumbaloluwa', 7),
	(610, 'Loluwagoda', 7),
	(611, 'Lunugama', 7),
	(612, 'Mabodale', 7),
	(613, 'Madelgamuwa', 7),
	(614, 'Makewita', 7),
	(615, 'Makola', 7),
	(616, 'Malwana', 7),
	(617, 'Mandawala', 7),
	(618, 'Marandagahamula', 7),
	(619, 'Mellawagedara', 7),
	(620, 'Minuwangoda', 7),
	(621, 'Mirigama', 7),
	(622, 'Mithirigala', 7),
	(623, 'Muddaragama', 7),
	(624, 'Mudungoda', 7),
	(625, 'Naranwala', 7),
	(626, 'Nawana', 7),
	(627, 'Nedungamuwa', 7),
	(628, 'Negombo', 7),
	(629, 'Nikahetikanda', 7),
	(630, 'Nittambuwa', 7),
	(631, 'Niwandama', 7),
	(632, 'Pallewela', 7),
	(633, 'Pamunugama', 7),
	(634, 'Pamunuwatta', 7),
	(635, 'Pasyala', 7),
	(636, 'Peliyagoda', 7),
	(637, 'Pepiliyawala', 7),
	(638, 'Pethiyagoda', 7),
	(639, 'Polpithimukulana', 7),
	(640, 'Pugoda', 7),
	(641, 'Radawadunna', 7),
	(642, 'Radawana', 7),
	(643, 'Raddolugama', 7),
	(644, 'Ragama', 7),
	(645, 'Ruggahawila', 7),
	(646, 'Rukmale', 7),
	(647, 'Seeduwa', 7),
	(648, 'Siyambalape', 7),
	(649, 'Talahena', 7),
	(650, 'Thimbirigaskatuwa', 7),
	(651, 'Tittapattara', 7),
	(652, 'Udathuthiripitiya', 7),
	(653, 'Udugampola', 7),
	(654, 'Uggalboda', 7),
	(655, 'Urapola', 7),
	(656, 'Uswetakeiyawa', 7),
	(657, 'Veyangoda', 7),
	(658, 'Walgammulla', 7),
	(659, 'Walpita', 7),
	(660, 'Wanaluwewa', 7),
	(661, 'Wathurugama', 7),
	(662, 'Watinapaha', 7),
	(663, 'Wattala', 7),
	(664, 'Weboda', 7),
	(665, 'Wegowwa', 7),
	(666, 'Weliveriya', 7),
	(667, 'Weweldeniya', 7),
	(668, 'Yakkala', 7),
	(669, 'Ambalantota', 8),
	(670, 'Angunakolapelessa', 8),
	(671, 'Bandagiriya Colony', 8),
	(672, 'Barawakumbuka', 8),
	(673, 'Beliatta', 8),
	(674, 'Beragama', 8),
	(675, 'Beralihela', 8),
	(676, 'Bowalagama', 8),
	(677, 'Bundala', 8),
	(678, 'Ellagala', 8),
	(679, 'Gangulandeniya', 8),
	(680, 'Getamanna', 8),
	(681, 'Goda Koggalla', 8),
	(682, 'Gonagamuwa Uduwila', 8),
	(683, 'Gonnoruwa', 8),
	(684, 'Hakuruwela', 8),
	(685, 'Hambantota', 8),
	(686, 'Horewelagoda', 8),
	(687, 'Hungama', 8),
	(688, 'Ihala Beligalla', 8),
	(689, 'Ittademaliya', 8),
	(690, 'Julampitiya', 8),
	(691, 'Kahandamodara', 8),
	(692, 'Kariyamaditta', 8),
	(693, 'Katuwana', 8),
	(694, 'Kawantissapura', 8),
	(695, 'Kirama', 8),
	(696, 'Kirinda', 8),
	(697, 'Lunama', 8),
	(698, 'Lunugamwehera', 8),
	(699, 'Magama', 8),
	(700, 'Mahagalwewa', 8),
	(701, 'Mamadala', 8),
	(702, 'Medamulana', 8),
	(703, 'Middeniya', 8),
	(704, 'Migahajandur', 8),
	(705, 'Modarawana', 8),
	(706, 'Mulkirigala', 8),
	(707, 'Nakulugamuwa', 8),
	(708, 'Netolpitiya', 8),
	(709, 'Nihiluwa', 8),
	(710, 'Padawkema', 8),
	(711, 'Pahala Andarawewa', 8),
	(712, 'Pallekanda', 8),
	(713, 'Rammalawarapitiya', 8),
	(714, 'Ranakeliya', 8),
	(715, 'Ranmuduwewa', 8),
	(716, 'Ranna', 8),
	(717, 'Ratmalwala', 8),
	(718, 'RU/Ridiyagama', 8),
	(719, 'Sooriyawewa Town', 8),
	(720, 'Tangalla', 8),
	(721, 'Tissamaharama', 8),
	(722, 'Uda Gomadiya', 8),
	(723, 'Udamattala', 8),
	(724, 'Uswewa', 8),
	(725, 'Vitharandeniya', 8),
	(726, 'Walasmulla', 8),
	(727, 'Weeraketiya', 8),
	(728, 'Weerawila', 8),
	(729, 'Weerawila NewTown', 8),
	(730, 'Wekandawela', 8),
	(731, 'Weligatta', 8),
	(732, 'Yatigala', 8),
	(733, 'Allaipiddi', 9),
	(734, 'Allaveddi', 9),
	(735, 'Alvai', 9),
	(736, 'Anaicoddai', 9),
	(737, 'Analaitivu', 9),
	(738, 'Atchuveli', 9),
	(739, 'Chankanai', 9),
	(740, 'Chavakachcheri', 9),
	(741, 'Chullipuram', 9),
	(742, 'Chundikuli', 9),
	(743, 'Chunnakam', 9),
	(744, 'Delft', 9),
	(745, 'DelftWest', 9),
	(746, 'Eluvaitivu', 9),
	(747, 'Erlalai', 9),
	(748, 'Jaffna', 9),
	(749, 'Kaitadi', 9),
	(750, 'Kankesanthurai', 9),
	(751, 'Karainagar', 9),
	(752, 'Karaveddi', 9),
	(753, 'Kayts', 9),
	(754, 'Kodikamam', 9),
	(755, 'Kokuvil', 9),
	(756, 'Kondavil', 9),
	(757, 'Kopay', 9),
	(758, 'Kudatanai', 9),
	(759, 'llavalai', 9),
	(760, 'Mallakam', 9),
	(761, 'Manipay', 9),
	(762, 'Mathagal', 9),
	(763, 'Meesalai', 9),
	(764, 'Mirusuvil', 9),
	(765, 'Nagar Kovil', 9),
	(766, 'Nainathivu', 9),
	(767, 'Neervely', 9),
	(768, 'Pandaterippu', 9),
	(769, 'Point Pedro', 9),
	(770, 'Pungudutivu', 9),
	(771, 'Putur', 9),
	(772, 'Sandilipay', 9),
	(773, 'Sithankemy', 9),
	(774, 'Tellippallai', 9),
	(775, 'Thondamanaru', 9),
	(776, 'Urumpirai', 9),
	(777, 'Vaddukoddai', 9),
	(778, 'Valvettithurai', 9),
	(779, 'Vannarponnai', 9),
	(780, 'Varany', 9),
	(781, 'Vasavilan', 9),
	(782, 'Velanai', 9),
	(783, 'Agalawatta', 10),
	(784, 'Alubomulla', 10),
	(785, 'Alutgama', 10),
	(786, 'Anguruwatota', 10),
	(787, 'Baduraliya', 10),
	(788, 'Bandaragama', 10),
	(789, 'Bellana', 10),
	(790, 'Beruwala', 10),
	(791, 'Bolossagama', 10),
	(792, 'Bombuwala', 10),
	(793, 'Boralugoda', 10),
	(794, 'Bulathsinhala', 10),
	(795, 'Danawala Thiniyawala', 10),
	(796, 'Delmella', 10),
	(797, 'Dharga Town', 10),
	(798, 'Diwalakada', 10),
	(799, 'Dodangoda', 10),
	(800, 'Dombagoda', 10),
	(801, 'Galpatha', 10),
	(802, 'Gamagoda', 10),
	(803, 'Gonapola Junction', 10),
	(804, 'Govinna', 10),
	(805, 'Gurulubadda', 10),
	(806, 'Halkandawila', 10),
	(807, 'Haltota', 10),
	(808, 'Halwala', 10),
	(809, 'Halwatura', 10),
	(810, 'Hedigalla Colony', 10),
	(811, 'Horana', 10),
	(812, 'Ittapana', 10),
	(813, 'Kalawila Kiranthidiya', 10),
	(814, 'Kalutara', 10),
	(815, 'Kananwila', 10),
	(816, 'Kandanagama', 10),
	(817, 'Kehelwatta', 10),
	(818, 'Kelinkanda', 10),
	(819, 'Kitulgoda', 10),
	(820, 'Koholana', 10),
	(821, 'Kuda Uduwa', 10),
	(822, 'lngiriya', 10),
	(823, 'Maggona', 10),
	(824, 'Mahagama', 10),
	(825, 'Mahakalupahana', 10),
	(826, 'Matugama', 10),
	(827, 'Meegahatenna', 10),
	(828, 'Meegama', 10),
	(829, 'Millaniya', 10),
	(830, 'Millewa', 10),
	(831, 'Miwanapalana', 10),
	(832, 'Molkawa', 10),
	(833, 'Morapitiya', 10),
	(834, 'Morontuduwa', 10),
	(835, 'Nawattuduwa', 10),
	(836, 'Neboda', 10),
	(837, 'Padagoda', 10),
	(838, 'Pahalahewessa', 10),
	(839, 'Paiyagala', 10),
	(840, 'Panadura', 10),
	(841, 'Pannila', 10),
	(842, 'Paragastota', 10),
	(843, 'Paragoda', 10),
	(844, 'Paraigama', 10),
	(845, 'Pelanda', 10),
	(846, 'Pelawatta', 10),
	(847, 'Pokunuwita', 10),
	(848, 'Polgampola', 10),
	(849, 'Poruwedanda', 10),
	(850, 'Remunagoda', 10),
	(851, 'Tebuwana', 10),
	(852, 'Uduwara', 10),
	(853, 'Utumgama', 10),
	(854, 'Veyangalla', 10),
	(855, 'Wadduwa', 10),
	(856, 'Walagedara', 10),
	(857, 'Walallawita', 10),
	(858, 'Waskaduwa', 10),
	(859, 'Welipenna', 10),
	(860, 'Welmilla Junction', 10),
	(861, 'Yagirala', 10),
	(862, 'Yatadolawatta', 10),
	(863, 'Yatawara Junction', 10),
	(864, 'Akurana', 11),
	(865, 'Alawatugoda', 11),
	(866, 'Aludeniya', 11),
	(867, 'Ambagahapelessa', 11),
	(868, 'Ambatenna', 11),
	(869, 'Ampitiya', 11),
	(870, 'Ankumbura', 11),
	(871, 'Atabage', 11),
	(872, 'Balana', 11),
	(873, 'Bambaragahaela', 11),
	(874, 'Barawardhana Oya', 11),
	(875, 'Batagolladeniya', 11),
	(876, 'Batugoda', 11),
	(877, 'Batumulla', 11),
	(878, 'Bawlana', 11),
	(879, 'Bopana', 11),
	(880, 'Danture', 11),
	(881, 'Dedunupitiya', 11),
	(882, 'Dekinda', 11),
	(883, 'Deltota', 11),
	(884, 'Dolapihilla', 11),
	(885, 'Dolosbage', 11),
	(886, 'Doluwa', 11),
	(887, 'Doragamuwa', 11),
	(888, 'Dunuwila', 11),
	(889, 'Ekiriya', 11),
	(890, 'Elamulla', 11),
	(891, 'Etulgama', 11),
	(892, 'Galaboda', 11),
	(893, 'Galagedara', 11),
	(894, 'Galaha', 11),
	(895, 'Galhinna', 11),
	(896, 'Gallellagama', 11),
	(897, 'Gampola', 11),
	(898, 'Gelioya', 11),
	(899, 'Godamunna', 11),
	(900, 'Gomagoda', 11),
	(901, 'Gonagantenna', 11),
	(902, 'Gonawalapatana', 11),
	(903, 'Gunnepana', 11),
	(904, 'Gurudeniya', 11),
	(905, 'Halloluwa', 11),
	(906, 'Handaganawa', 11),
	(907, 'Handawalapitiya', 11),
	(908, 'Handessa', 11),
	(909, 'Hanguranketha', 11),
	(910, 'Harankahawa', 11),
	(911, 'Hasalaka', 11),
	(912, 'Hataraliyadda', 11),
	(913, 'Hewaheta', 11),
	(914, 'Hindagala', 11),
	(915, 'Hondiyadeniya', 11),
	(916, 'Hunnasgiriya', 11),
	(917, 'Jambugahapitiya', 11),
	(918, 'Kadugannawa', 11),
	(919, 'Kahataliyadda', 11),
	(920, 'Kalugala', 11),
	(921, 'Kandy', 11),
	(922, 'Kapuliyadde', 11),
	(923, 'Karandagolla', 11),
	(924, 'Katugastota', 11),
	(925, 'Kengalla', 11),
	(926, 'Ketakumbura', 11),
	(927, 'Ketawala Leula', 11),
	(928, 'Kiribathkumbura', 11),
	(929, 'Kobonila', 11),
	(930, 'Kolabissa', 11),
	(931, 'Kolongoda', 11),
	(932, 'Kulugammana', 11),
	(933, 'Kumbukkandura', 11),
	(934, 'Kumburegama', 11),
	(935, 'Kundasale', 11),
	(936, 'Leemagahakotuwa', 11),
	(937, 'lhala Kobbekaduwa', 11),
	(938, 'lllagolla', 11),
	(939, 'Lunuketiya Maditta', 11),
	(940, 'Madawala Bazaar', 11),
	(941, 'Madugalla', 11),
	(942, 'Madulkele', 11),
	(943, 'Mahadoraliyadda', 11),
	(944, 'Mahamedagama', 11),
	(945, 'Mailapitiya', 11),
	(946, 'Makkanigama', 11),
	(947, 'Makuldeniya', 11),
	(948, 'Mandaram Nuwara', 11),
	(949, 'Mapakanda', 11),
	(950, 'Marassana', 11),
	(951, 'Marymount Colony', 11),
	(952, 'Maturata', 11),
	(953, 'Mawatura', 11),
	(954, 'Medamahanuwara', 11),
	(955, 'Medawala Harispattuwa', 11),
	(956, 'Meetalawa', 11),
	(957, 'Megoda Kalugamuwa', 11),
	(958, 'Menikdiwela', 11),
	(959, 'Menikhinna', 11),
	(960, 'Mimure', 11),
	(961, 'Minigamuwa', 11),
	(962, 'Minipe', 11),
	(963, 'Murutalawa', 11),
	(964, 'Muruthagahamulla', 11),
	(965, 'Naranpanawa', 11),
	(966, 'Nattarampotha', 11),
	(967, 'Nawalapitiya', 11),
	(968, 'Nillambe', 11),
	(969, 'Nugaliyadda', 11),
	(970, 'Nugawela', 11),
	(971, 'Pallebowala', 11),
	(972, 'Pallekotuwa', 11),
	(973, 'Panvila', 11),
	(974, 'Panwilatenna', 11),
	(975, 'Paradeka', 11),
	(976, 'Pasbage', 11),
	(977, 'Pattitalawa', 11),
	(978, 'Pattiya Watta', 11),
	(979, 'Penideniya', 11),
	(980, 'Peradeniya', 11),
	(981, 'Pilawala', 11),
	(982, 'Pilimatalawa', 11),
	(983, 'Poholiyadda', 11),
	(984, 'Polgolla', 11),
	(985, 'Pujapitiya', 11),
	(986, 'Pupuressa', 11),
	(987, 'Pussellawa', 11),
	(988, 'Putuhapuwa', 11),
	(989, 'Rajawella', 11),
	(990, 'Rambukpitiya', 11),
	(991, 'Rambukwella', 11),
	(992, 'Rangala', 11),
	(993, 'Rantembe', 11),
	(994, 'Rathukohodigala', 11),
	(995, 'Rikillagaskada', 11),
	(996, 'Sangarajapura', 11),
	(997, 'Senarathwela', 11),
	(998, 'Talatuoya', 11),
	(999, 'Tawalantenna', 11),
	(1000, 'Teldeniya', 11),
	(1001, 'Tennekumbura', 11),
	(1002, 'Uda Peradeniya', 11),
	(1003, 'Udahentenna', 11),
	(1004, 'Udahingulwala', 11),
	(1005, 'Udatalawinna', 11),
	(1006, 'Udawatta', 11),
	(1007, 'Udispattuwa', 11),
	(1008, 'Ududumbara', 11),
	(1009, 'Uduwa', 11),
	(1010, 'Uduwahinna', 11),
	(1011, 'Uduwela', 11),
	(1012, 'Ulapane', 11),
	(1013, 'Ulpothagama', 11),
	(1014, 'Unuwinna', 11),
	(1015, 'Velamboda', 11),
	(1016, 'Watagoda Harispattuwa', 11),
	(1017, 'Wattappola', 11),
	(1018, 'Wattegama', 11),
	(1019, 'Weligalla', 11),
	(1020, 'Weligampola', 11),
	(1021, 'Wendaruwa', 11),
	(1022, 'Weragantota', 11),
	(1023, 'Werapitya', 11),
	(1024, 'Werellagama', 11),
	(1025, 'Wettawa', 11),
	(1026, 'Wilanagama', 11),
	(1027, 'Yahalatenna', 11),
	(1028, 'Yatihalagala', 11),
	(1029, 'Alawatura', 12),
	(1030, 'Algama', 12),
	(1031, 'Alutnuwara', 12),
	(1032, 'Ambalakanda', 12),
	(1033, 'Ambulugala', 12),
	(1034, 'Amitirigala', 12),
	(1035, 'Ampagala', 12),
	(1036, 'Anhettigama', 12),
	(1037, 'Aranayaka', 12),
	(1038, 'Aruggammana', 12),
	(1039, 'Atale', 12),
	(1040, 'Batuwita', 12),
	(1041, 'Berannawa', 12),
	(1042, 'Boralankada', 12),
	(1043, 'Bossella', 12),
	(1044, 'Bulathkohupitiya', 12),
	(1045, 'Damunupola', 12),
	(1046, 'Debathgama', 12),
	(1047, 'Dedugala', 12),
	(1048, 'Deewala Pallegama', 12),
	(1049, 'Dehiowita', 12),
	(1050, 'Deldeniya', 12),
	(1051, 'Deloluwa', 12),
	(1052, 'Deraniyagala', 12),
	(1053, 'Dewalegama', 12),
	(1054, 'Dewanagala', 12),
	(1055, 'Dombemada', 12),
	(1056, 'Dorawaka', 12),
	(1057, 'Dunumala', 12),
	(1058, 'Galapitamada', 12),
	(1059, 'Galatara', 12),
	(1060, 'Galigamuwa Town', 12),
	(1061, 'Gantuna', 12),
	(1062, 'Gonagala', 12),
	(1063, 'Hakahinna', 12),
	(1064, 'Hakbellawaka', 12),
	(1065, 'Helamada', 12),
	(1066, 'Hemmatagama', 12),
	(1067, 'Hettimulla', 12),
	(1068, 'Hewadiwela', 12),
	(1069, 'Hingula', 12),
	(1070, 'Hinguralakanda', 12),
	(1071, 'Hiriwadunna', 12),
	(1072, 'Imbulana', 12),
	(1073, 'Imbulgasdeniya', 12),
	(1074, 'Kabagamuwa', 12),
	(1075, 'Kannattota', 12),
	(1076, 'Kegalle', 12),
	(1077, 'Kehelpannala', 12),
	(1078, 'Kitulgala', 12),
	(1079, 'Kondeniya', 12),
	(1080, 'Kotiyakumbura', 12),
	(1081, 'Lewangama', 12),
	(1082, 'Mahabage', 12),
	(1083, 'Mahapallegama', 12),
	(1084, 'Maharangalla', 12),
	(1085, 'Makehelwala', 12),
	(1086, 'Malalpola', 12),
	(1087, 'Maliboda', 12),
	(1088, 'Malmaduwa', 12),
	(1089, 'Mawanella', 12),
	(1090, 'Migastenna Sabara', 12),
	(1091, 'Miyanawita', 12),
	(1092, 'Molagoda', 12),
	(1093, 'Morontota', 12),
	(1094, 'Nelundeniya', 12),
	(1095, 'Niyadurupola', 12),
	(1096, 'Noori', 12),
	(1097, 'Parape', 12),
	(1098, 'Pattampitiya', 12),
	(1099, 'Pitagaldeniya', 12),
	(1100, 'Pothukoladeniya', 12),
	(1101, 'Rambukkana', 12),
	(1102, 'Ruwanwella', 12),
	(1103, 'Seaforth Colony', 12),
	(1104, 'Talgaspitiya', 12),
	(1105, 'Teligama', 12),
	(1106, 'Tholangamuwa', 12),
	(1107, 'Thotawella', 12),
	(1108, 'Tulhiriya', 12),
	(1109, 'Tuntota', 12),
	(1110, 'Udagaldeniya', 12),
	(1111, 'Udapotha', 12),
	(1112, 'Udumulla', 12),
	(1113, 'Undugoda', 12),
	(1114, 'Ussapitiya', 12),
	(1115, 'Wahakula', 12),
	(1116, 'Waharaka', 12),
	(1117, 'Warakapola', 12),
	(1118, 'Watura', 12),
	(1119, 'Weeoya', 12),
	(1120, 'Wegalla', 12),
	(1121, 'Welihelatenna', 12),
	(1122, 'Weragala', 12),
	(1123, 'Yatagama', 12),
	(1124, 'Yatapana', 12),
	(1125, 'Yatiyantota', 12),
	(1126, 'Yattogoda', 12),
	(1127, 'Kilinochchi', 13),
	(1128, 'Alahengama', 14),
	(1129, 'Alahitiyawa', 14),
	(1130, 'Alawatuwala', 14),
	(1131, 'Alawwa', 14),
	(1132, 'Ambakote', 14),
	(1133, 'Ambanpola', 14),
	(1134, 'Anhandiya', 14),
	(1135, 'Anukkane', 14),
	(1136, 'Aragoda', 14),
	(1137, 'Ataragalla', 14),
	(1138, 'Awulegama', 14),
	(1139, 'Balalla', 14),
	(1140, 'Bamunukotuwa', 14),
	(1141, 'Bandara Koswatta', 14),
	(1142, 'Bingiriya', 14),
	(1143, 'Bogamulla', 14),
	(1144, 'Bopitiya', 14),
	(1145, 'Boraluwewa', 14),
	(1146, 'Boyagane', 14),
	(1147, 'Bujjomuwa', 14),
	(1148, 'Buluwala', 14),
	(1149, 'Dambadeniya', 14),
	(1150, 'Daraluwa', 14),
	(1151, 'Deegalla', 14),
	(1152, 'Delwite', 14),
	(1153, 'Demataluwa', 14),
	(1154, 'Diddeniya', 14),
	(1155, 'Digannewa', 14),
	(1156, 'Divullegoda', 14),
	(1157, 'Dodangaslanda', 14),
	(1158, 'Doratiyawa', 14),
	(1159, 'Dummalasuriya', 14),
	(1160, 'Ehetuwewa', 14),
	(1161, 'Elibichchiya', 14),
	(1162, 'Embogama', 14),
	(1163, 'Etungahakotuwa', 14),
	(1164, 'Galgamuwa', 14),
	(1165, 'Gallewa', 14),
	(1166, 'Girathalana', 14),
	(1167, 'Giriulla', 14),
	(1168, 'Gokaralla', 14),
	(1169, 'Gonawila', 14),
	(1170, 'Halmillawewa', 14),
	(1171, 'Hengamuwa', 14),
	(1172, 'Hettipola', 14),
	(1173, 'Hilogama', 14),
	(1174, 'Hindagolla', 14),
	(1175, 'Hiriyala Lenawa', 14),
	(1176, 'Hiruwalpola', 14),
	(1177, 'Horambawa', 14),
	(1178, 'Hulogedara', 14),
	(1179, 'Hulugalla', 14),
	(1180, 'Hunupola', 14),
	(1181, 'Ihala Gomugomuwa', 14),
	(1182, 'Ihala Katugampala', 14),
	(1183, 'Indulgodakanda', 14),
	(1184, 'Inguruwatta', 14),
	(1185, 'Iriyagolla', 14),
	(1186, 'Ithanawatta', 14),
	(1187, 'Kadigawa', 14),
	(1188, 'Kahapathwala', 14),
	(1189, 'Kalugamuwa', 14),
	(1190, 'Kanadeniyawala', 14),
	(1191, 'Kanattewewa', 14),
	(1192, 'Karagahagedara', 14),
	(1193, 'Karambe', 14),
	(1194, 'Katupota', 14),
	(1195, 'Kekunagolla', 14),
	(1196, 'Keppitiwalana', 14),
	(1197, 'Kimbulwanaoya', 14),
	(1198, 'Kirimetiyawa', 14),
	(1199, 'Kirindawa', 14),
	(1200, 'Kirindigalla', 14),
	(1201, 'Kithalawa', 14),
	(1202, 'Kobeigane', 14),
	(1203, 'Kohilagedara', 14),
	(1204, 'Konwewa', 14),
	(1205, 'Kosdeniya', 14),
	(1206, 'Kosgolla', 14),
	(1207, 'Kotawehera', 14),
	(1208, 'Kudagalgamuwa', 14),
	(1209, 'Kudakatnoruwa', 14),
	(1210, 'Kuliyapitiya', 14),
	(1211, 'Kumbukgeta', 14),
	(1212, 'Kumbukwewa', 14),
	(1213, 'Kuratihena', 14),
	(1214, 'Kurunegala', 14),
	(1215, 'Labbala', 14),
	(1216, 'lbbagamuwa', 14),
	(1217, 'lhala Kadigamuwa', 14),
	(1218, 'llukhena', 14),
	(1219, 'Lonahettiya', 14),
	(1220, 'Madahapola', 14),
	(1221, 'Madakumburumulla', 14),
	(1222, 'Maduragoda', 14),
	(1223, 'Maeliya', 14),
	(1224, 'Magulagama', 14),
	(1225, 'Mahagalkadawala', 14),
	(1226, 'Mahagirilla', 14),
	(1227, 'Mahamukalanyaya', 14),
	(1228, 'Mahananneriya', 14),
	(1229, 'Maharachchimulla', 14),
	(1230, 'Maho', 14),
	(1231, 'Makulewa', 14),
	(1232, 'Makulpotha', 14),
	(1233, 'Makulwewa', 14),
	(1234, 'Malagane', 14),
	(1235, 'Mandapola', 14),
	(1236, 'Maspotha', 14),
	(1237, 'Mawathagama', 14),
	(1238, 'Medivawa', 14),
	(1239, 'Meegalawa', 14),
	(1240, 'Meetanwala', 14),
	(1241, 'Meewellawa', 14),
	(1242, 'Melsiripura', 14),
	(1243, 'Metikumbura', 14),
	(1244, 'Metiyagane', 14),
	(1245, 'Minhettiya', 14),
	(1246, 'Minuwangete', 14),
	(1247, 'Mirihanagama', 14),
	(1248, 'Monnekulama', 14),
	(1249, 'Moragane', 14),
	(1250, 'Moragollagama', 14),
	(1251, 'Morathiha', 14),
	(1252, 'Munamaldeniya', 14),
	(1253, 'Muruthenge', 14),
	(1254, 'Nabadewa', 14),
	(1255, 'Nagollagama', 14),
	(1256, 'Nagollagoda', 14),
	(1257, 'Nakkawatta', 14),
	(1258, 'Narammala', 14),
	(1259, 'Narangoda', 14),
	(1260, 'Nawatalwatta', 14),
	(1261, 'Nelliya', 14),
	(1262, 'Nikadalupotha', 14),
	(1263, 'Nikaweratiya', 14),
	(1264, 'Padeniya', 14),
	(1265, 'Padiwela', 14),
	(1266, 'Pahalagiribawa', 14),
	(1267, 'Pahamune', 14),
	(1268, 'Palukadawala', 14),
	(1269, 'Panadaragama', 14),
	(1270, 'Panagamuwa', 14),
	(1271, 'Panaliya', 14),
	(1272, 'Panliyadda', 14),
	(1273, 'Pannala', 14),
	(1274, 'Pansiyagama', 14),
	(1275, 'Periyakadneluwa', 14),
	(1276, 'Pihimbiya Ratmale', 14),
	(1277, 'Pihimbuwa', 14),
	(1278, 'Pilessa', 14),
	(1279, 'Polgahawela', 14),
	(1280, 'Polpitigama', 14),
	(1281, 'Pothuhera', 14),
	(1282, 'Puswelitenna', 14),
	(1283, 'Ridibendiella', 14),
	(1284, 'Ridigama', 14),
	(1285, 'Saliya Asokapura', 14),
	(1286, 'Sandalankawa', 14),
	(1287, 'Sirisetagama', 14),
	(1288, 'Siyambalangamuwa', 14),
	(1289, 'Solepura', 14),
	(1290, 'Solewewa', 14),
	(1291, 'Sunandapura', 14),
	(1292, 'Talawattegedara', 14),
	(1293, 'Tambutta', 14),
	(1294, 'Thalahitimulla', 14),
	(1295, 'Thalakolawewa', 14),
	(1296, 'Thalwita', 14),
	(1297, 'Thambagalla', 14),
	(1298, 'Tharana Udawela', 14),
	(1299, 'Thimbiriyawa', 14),
	(1300, 'Tisogama', 14),
	(1301, 'Torayaya', 14),
	(1302, 'Tuttiripitigama', 14),
	(1303, 'Udubaddawa', 14),
	(1304, 'Uhumiya', 14),
	(1305, 'Ulpotha Pallekele', 14),
	(1306, 'Usgala Siyabmalangamuwa', 14),
	(1307, 'Wadakada', 14),
	(1308, 'Wadumunnegedara', 14),
	(1309, 'Walakumburumulla', 14),
	(1310, 'Wannigama', 14),
	(1311, 'Wannikudawewa', 14),
	(1312, 'Wannilhalagama', 14),
	(1313, 'Wannirasnayakapura', 14),
	(1314, 'Warawewa', 14),
	(1315, 'Wariyapola', 14),
	(1316, 'Watuwatta', 14),
	(1317, 'Weerapokuna', 14),
	(1318, 'Welawa Juncton', 14),
	(1319, 'Welipennagahamulla', 14),
	(1320, 'Wellagala', 14),
	(1321, 'Wellarawa', 14),
	(1322, 'Wellawa', 14),
	(1323, 'Welpalla', 14),
	(1324, 'Wennoruwa', 14),
	(1325, 'Weuda', 14),
	(1326, 'Wewagama', 14),
	(1327, 'Yakwila', 14),
	(1328, 'Yatigaloluwa', 14),
	(1329, 'Adampan', 15),
	(1330, 'Arippu', 15),
	(1331, 'Athimottai', 15),
	(1332, 'Chilavathurai', 15),
	(1333, 'Erukkalampiddy', 15),
	(1334, 'llluppaikadavai', 15),
	(1335, 'Madhu Church', 15),
	(1336, 'Madhu Road', 15),
	(1337, 'Mannar', 15),
	(1338, 'Marichchi Kaddi', 15),
	(1339, 'Murunkan', 15),
	(1340, 'Nanattan', 15),
	(1341, 'P.P.Potkemy', 15),
	(1342, 'Palampiddy', 15),
	(1343, 'Periyakunchikulam', 15),
	(1344, 'Periyamadhu', 15),
	(1345, 'Pesalai', 15),
	(1346, 'Talaimannar', 15),
	(1347, 'Temple', 15),
	(1348, 'Tharapuram', 15),
	(1349, 'Thiruketheeswaram Temple', 15),
	(1350, 'Uyilankulam', 15),
	(1351, 'Vaddakandal', 15),
	(1352, 'Vankalai', 15),
	(1353, 'Vellan Kulam', 15),
	(1354, 'Vidataltivu', 15),
	(1355, 'Akuramboda', 16),
	(1356, 'Alwatta', 16),
	(1357, 'Ambana', 16),
	(1358, 'Ataragallewa', 16),
	(1359, 'Bambaragaswewa', 16),
	(1360, 'Beligamuwa', 16),
	(1361, 'Dambulla', 16),
	(1362, 'Dankanda', 16),
	(1363, 'Devagiriya', 16),
	(1364, 'Dewahuwa', 16),
	(1365, 'Dullewa', 16),
	(1366, 'Dunkolawatta', 16),
	(1367, 'Dunuwilapitiya', 16),
	(1368, 'Elkaduwa', 16),
	(1369, 'Erawula Junction', 16),
	(1370, 'Etanawala', 16),
	(1371, 'Galewela', 16),
	(1372, 'Gammaduwa', 16),
	(1373, 'Gangala Puwakpitiya', 16),
	(1374, 'Handungamuwa', 16),
	(1375, 'Hattota Amuna', 16),
	(1376, 'Imbulgolla', 16),
	(1377, 'Inamaluwa', 16),
	(1378, 'Kaikawala', 16),
	(1379, 'Kalundawa', 16),
	(1380, 'Kandalama', 16),
	(1381, 'Karagahinna', 16),
	(1382, 'Katudeniya', 16),
	(1383, 'Kavudupelella', 16),
	(1384, 'Kibissa', 16),
	(1385, 'Kiwula', 16),
	(1386, 'Kongahawela', 16),
	(1387, 'Laggala Pallegama', 16),
	(1388, 'Leliambe', 16),
	(1389, 'Lenadora', 16),
	(1390, 'lllukkumbura', 16),
	(1391, 'Madawala Ulpotha', 16),
	(1392, 'Madipola', 16),
	(1393, 'Mahawela', 16),
	(1394, 'Mananwatta', 16),
	(1395, 'Maraka', 16),
	(1396, 'Matale', 16),
	(1397, 'Melipitiya', 16),
	(1398, 'Metihakka', 16),
	(1399, 'Millawana', 16),
	(1400, 'Muwandeniya', 16),
	(1401, 'Nalanda', 16),
	(1402, 'Naula', 16),
	(1403, 'Nugagolla', 16),
	(1404, 'Opalgala', 16),
	(1405, 'Ovilikanda', 16),
	(1406, 'Palapathwela', 16),
	(1407, 'Pallepola', 16),
	(1408, 'Perakanatta', 16),
	(1409, 'Pubbiliya', 16),
	(1410, 'Ranamuregama', 16),
	(1411, 'Rattota', 16),
	(1412, 'Selagama', 16),
	(1413, 'Sigiriya', 16),
	(1414, 'Talagoda Junction', 16),
	(1415, 'Talakiriyagama', 16),
	(1416, 'Udasgiriya', 16),
	(1417, 'Udatenna', 16),
	(1418, 'Ukuwela', 16),
	(1419, 'Wahacotte', 16),
	(1420, 'Walawela', 16),
	(1421, 'Wehigala', 16),
	(1422, 'Welangahawatte', 16),
	(1423, 'Wewalawewa', 16),
	(1424, 'Wilgamuwa', 16),
	(1425, 'Yatawatta', 16),
	(1426, 'Akuressa', 17),
	(1427, 'Alapaladeniya', 17),
	(1428, 'Aparekka', 17),
	(1429, 'Athuraliya', 17),
	(1430, 'Bengamuwa', 17),
	(1431, 'Beralapanathara', 17),
	(1432, 'Bopagoda', 17),
	(1433, 'Dampahala', 17),
	(1434, 'Deegala Lenama', 17),
	(1435, 'Deiyandara', 17),
	(1436, 'Dellawa', 17),
	(1437, 'Denagama', 17),
	(1438, 'Denipitiya', 17),
	(1439, 'Deniyaya', 17),
	(1440, 'Derangala', 17),
	(1441, 'Dikwella', 17),
	(1442, 'Diyagaha', 17),
	(1443, 'Diyalape', 17),
	(1444, 'Gandara', 17),
	(1445, 'Godapitiya', 17),
	(1446, 'Gomilamawarala', 17),
	(1447, 'Hakmana', 17),
	(1448, 'Handugala', 17),
	(1449, 'Horapawita', 17),
	(1450, 'Kalubowitiyana', 17),
	(1451, 'Kamburugamuwa', 17),
	(1452, 'Kamburupitiya', 17),
	(1453, 'Karagoda Uyangoda', 17),
	(1454, 'Karaputugala', 17),
	(1455, 'Karatota', 17),
	(1456, 'Kekanadurra', 17),
	(1457, 'Kiriweldola', 17),
	(1458, 'Kiriwelkele', 17),
	(1459, 'Kolawenigama', 17),
	(1460, 'Kotapola', 17),
	(1461, 'Kottegoda', 17),
	(1462, 'Lankagama', 17),
	(1463, 'Makandura', 17),
	(1464, 'Maliduwa', 17),
	(1465, 'Maramba', 17),
	(1466, 'Matara', 17),
	(1467, 'Mediripitiya', 17),
	(1468, 'Miella', 17),
	(1469, 'Mirissa', 17),
	(1470, 'Moragala Kirillapone', 17),
	(1471, 'Morawaka', 17),
	(1472, 'Mulatiyana Junction', 17),
	(1473, 'Nadugala', 17),
	(1474, 'Naimana', 17),
	(1475, 'Narawelpita', 17),
	(1476, 'Pahala Millawa', 17),
	(1477, 'Palatuwa', 17),
	(1478, 'Paragala', 17),
	(1479, 'Parapamulla', 17),
	(1480, 'Pasgoda', 17),
	(1481, 'Penetiyana', 17),
	(1482, 'Pitabeddara', 17),
	(1483, 'Pothdeniya', 17),
	(1484, 'Puhulwella', 17),
	(1485, 'Radawela', 17),
	(1486, 'Ransegoda', 17),
	(1487, 'Ratmale', 17),
	(1488, 'Rotumba', 17),
	(1489, 'Siyambalagoda', 17),
	(1490, 'Sultanagoda', 17),
	(1491, 'Telijjawila', 17),
	(1492, 'Thihagoda', 17),
	(1493, 'Urubokka', 17),
	(1494, 'Urugamuwa', 17),
	(1495, 'Urumutta', 17),
	(1496, 'Viharahena', 17),
	(1497, 'Walakanda', 17),
	(1498, 'Walasgala', 17),
	(1499, 'Waralla', 17),
	(1500, 'Weligama', 17),
	(1501, 'Wilpita', 17),
	(1502, 'Yatiyana', 17),
	(1503, 'Angunakolawewa', 18),
	(1504, 'Ayiwela', 18),
	(1505, 'Badalkumbura', 18),
	(1506, 'Baduluwela', 18),
	(1507, 'Bakinigahawela', 18),
	(1508, 'Balaharuwa', 18),
	(1509, 'Bibile', 18),
	(1510, 'Buddama', 18),
	(1511, 'Buttala', 18),
	(1512, 'Dambagalla', 18),
	(1513, 'Diyakobala', 18),
	(1514, 'Dombagahawela', 18),
	(1515, 'Ekamutugama', 18),
	(1516, 'Ekiriyankumbura', 18),
	(1517, 'Ethimalewewa', 18),
	(1518, 'Ettiliwewa', 18),
	(1519, 'Galabedda', 18),
	(1520, 'Hambegamuwa', 18),
	(1521, 'Hulandawa', 18),
	(1522, 'Inginiyagala', 18),
	(1523, 'Kandaudapanguwa', 18),
	(1524, 'Kandawinna', 18),
	(1525, 'Kataragama', 18),
	(1526, 'Kiriibbanwewa', 18),
	(1527, 'Kotagama', 18),
	(1528, 'Kotawehera Mankada', 18),
	(1529, 'Kotiyagala', 18),
	(1530, 'Kumbukkana', 18),
	(1531, 'Mahagama Colony', 18),
	(1532, 'Marawa', 18),
	(1533, 'Mariarawa', 18),
	(1534, 'Medagana', 18),
	(1535, 'Monaragala', 18),
	(1536, 'Moretuwegama', 18),
	(1537, 'Nakkala', 18),
	(1538, 'Nannapurawa', 18),
	(1539, 'Nelliyadda', 18),
	(1540, 'Nilgala', 18),
	(1541, 'Obbegoda', 18),
	(1542, 'Okkampitiya', 18),
	(1543, 'Pangura', 18),
	(1544, 'Pitakumbura', 18),
	(1545, 'Randeniya', 18),
	(1546, 'Ruwalwela', 18),
	(1547, 'Sella Kataragama', 18),
	(1548, 'Sewanagala', 18),
	(1549, 'Siyambalagune', 18),
	(1550, 'Siyambalanduwa', 18),
	(1551, 'Suriara', 18),
	(1552, 'Tanamalwila', 18),
	(1553, 'Uva Gangodagama', 18),
	(1554, 'Uva Kudaoya', 18),
	(1555, 'Uva Pelwatta', 18),
	(1556, 'Warunagama', 18),
	(1557, 'Wedikumbura', 18),
	(1558, 'Weherayaya Handapanagala', 18),
	(1559, 'Wellawaya', 18),
	(1560, 'Wilaoya', 18),
	(1561, 'Mullaitivu', 19),
	(1562, 'Alampil', 19),
	(1563, 'Karuppaddamurippu', 19),
	(1564, 'Kokkilai', 19),
	(1565, 'Kokkuthuoduvai', 19),
	(1566, 'Mankulam', 19),
	(1567, 'Mullivaikkal', 19),
	(1568, 'Mulliyawalai', 19),
	(1569, 'Muthauyan Kaddakulam', 19),
	(1570, 'Naddan Kandal', 19),
	(1571, 'Odduchudan', 19),
	(1572, 'Puthuvedduvan', 19),
	(1573, 'Thunukkai', 19),
	(1574, 'Udayarkaddu', 19),
	(1575, 'Vavunakkulam', 19),
	(1576, 'Visvamadukulam', 19),
	(1577, 'Yogapuram', 19),
	(1578, 'Agarapathana', 20),
	(1579, 'Ambagamuwa Udabulathgama', 20),
	(1580, 'Ambatalawa', 20),
	(1581, 'Ambewela', 20),
	(1582, 'Bogawantalawa', 20),
	(1583, 'Bopattalawa', 20),
	(1584, 'Dagampitiya', 20),
	(1585, 'Dayagama Bazaar', 20),
	(1586, 'Dikoya', 20),
	(1587, 'Doragala', 20),
	(1588, 'Dunukedeniya', 20),
	(1589, 'Ginigathena', 20),
	(1590, 'Gonakele', 20),
	(1591, 'Haggala', 20),
	(1592, 'Halgranoya', 20),
	(1593, 'Hangarapitiya', 20),
	(1594, 'Hapugastalawa', 20),
	(1595, 'Harangalagama', 20),
	(1596, 'Harasbedda', 20),
	(1597, 'Hatton', 20),
	(1598, 'Hedunuwewa', 20),
	(1599, 'Hitigegama', 20),
	(1600, 'Kalaganwatta', 20),
	(1601, 'Kandapola', 20),
	(1602, 'Katukitula', 20),
	(1603, 'Keerthi Bandarapura', 20),
	(1604, 'Kelanigama', 20),
	(1605, 'Ketaboola', 20),
	(1606, 'Kotagala', 20),
	(1607, 'Kotmale', 20),
	(1608, 'Kottellena', 20),
	(1609, 'Kumbalgamuwa', 20),
	(1610, 'Kumbukwela', 20),
	(1611, 'Kurupanawela', 20),
	(1612, 'Labukele', 20),
	(1613, 'Laxapana', 20),
	(1614, 'Lindula', 20),
	(1615, 'Madulla', 20),
	(1616, 'Maldeniya', 20),
	(1617, 'Maskeliya', 20),
	(1618, 'Maswela', 20),
	(1619, 'Mipanawa', 20),
	(1620, 'Mipilimana', 20),
	(1621, 'Morahenagama', 20),
	(1622, 'Munwatta', 20),
	(1623, 'Nanuoya', 20),
	(1624, 'Nawathispane', 20),
	(1625, 'Nayapana Janapadaya', 20),
	(1626, 'Nildandahinna', 20),
	(1627, 'Nissanka Uyana', 20),
	(1628, 'Norwood', 20),
	(1629, 'Nuwara Eliya', 20),
	(1630, 'Padiyapelella', 20),
	(1631, 'Patana', 20),
	(1632, 'Pitawala', 20),
	(1633, 'Pundaluoya', 20),
	(1634, 'Ramboda', 20),
	(1635, 'Rozella', 20),
	(1636, 'Rupaha', 20),
	(1637, 'Ruwaneliya', 20),
	(1638, 'Santhipura', 20),
	(1639, 'Talawakele', 20),
	(1640, 'Teripeha', 20),
	(1641, 'Udamadura', 20),
	(1642, 'Udapussallawa', 20),
	(1643, 'Walapane', 20),
	(1644, 'Watagoda', 20),
	(1645, 'Watawala', 20),
	(1646, 'Widulipura', 20),
	(1647, 'Wijebahukanda', 20),
	(1648, 'Alutwewa', 21),
	(1649, 'Aralaganwila', 21),
	(1650, 'Aselapura', 21),
	(1651, 'Attanakadawala', 21),
	(1652, 'Bakamuna', 21),
	(1653, 'Dalukana', 21),
	(1654, 'Damminna', 21),
	(1655, 'Dewagala', 21),
	(1656, 'Dimbulagala', 21),
	(1657, 'Divulankadawala', 21),
	(1658, 'Divuldamana', 21),
	(1659, 'Diyabeduma', 21),
	(1660, 'Diyasenpura', 21),
	(1661, 'Elahera', 21),
	(1662, 'Ellewewa', 21),
	(1663, 'Galamuna', 21),
	(1664, 'Galoya Junction', 21),
	(1665, 'Giritale', 21),
	(1666, 'Hansayapalama', 21),
	(1667, 'Hingurakdamana', 21),
	(1668, 'Hingurakgoda', 21),
	(1669, 'Jayanthipura', 21),
	(1670, 'Jayasiripura', 21),
	(1671, 'Kalingaela', 21),
	(1672, 'Kalukele Badanagala', 21),
	(1673, 'Kashyapapura', 21),
	(1674, 'Kawudulla', 21),
	(1675, 'Kawuduluwewa Stagell', 21),
	(1676, 'Kottapitiya', 21),
	(1677, 'Kumaragama', 21),
	(1678, 'Lakshauyana', 21),
	(1679, 'Maduruoya', 21),
	(1680, 'Maha Ambagaswewa', 21),
	(1681, 'Mahatalakolawewa', 21),
	(1682, 'Mahawela Sinhapura', 21),
	(1683, 'Mampitiya', 21),
	(1684, 'Medirigiriya', 21),
	(1685, 'Meegaswewa', 21),
	(1686, 'Minneriya', 21),
	(1687, 'Mutugala', 21),
	(1688, 'Nawasenapura', 21),
	(1689, 'Nelumwewa', 21),
	(1690, 'Nuwaragala', 21),
	(1691, 'Onegama', 21),
	(1692, 'OrubendiSiyambalawa', 21),
	(1693, 'Palugasdamana', 21),
	(1694, 'Parakramasamudraya', 21),
	(1695, 'Pelatiyawa', 21),
	(1696, 'Pimburattewa', 21),
	(1697, 'Polonnaruwa', 21),
	(1698, 'Pulastigama', 21),
	(1699, 'Sevanapitiya', 21),
	(1700, 'Sinhagama', 21),
	(1701, 'Sungavila', 21),
	(1702, 'Talpotha', 21),
	(1703, 'Tamankaduwa', 21),
	(1704, 'Tambala', 21),
	(1705, 'Unagalavehera', 21),
	(1706, 'Welikanda', 21),
	(1707, 'Wijayabapura', 21),
	(1708, 'Yodaela', 21),
	(1709, 'Yudaganawa', 21),
	(1710, 'Adippala', 22),
	(1711, 'Ambakandawila', 22),
	(1712, 'Anamaduwa', 22),
	(1713, 'Andigama', 22),
	(1714, 'Angunawila', 22),
	(1715, 'Attawilluwa', 22),
	(1716, 'Bangadeniya', 22),
	(1717, 'Baranankattuwa', 22),
	(1718, 'Battuluoya', 22),
	(1719, 'Bujjampola', 22),
	(1720, 'Chilaw', 22),
	(1721, 'Dankotuwa', 22),
	(1722, 'Dunkannawa', 22),
	(1723, 'Eluwankulama', 22),
	(1724, 'Ettale', 22),
	(1725, 'Galmuruwa', 22),
	(1726, 'Ihala Kottaramulla', 22),
	(1727, 'Ihala Puliyankulama', 22),
	(1728, 'Ilippadeniya', 22),
	(1729, 'Inginimitiya', 22),
	(1730, 'Ismailpuram', 22),
	(1731, 'Kakkapalliya', 22),
	(1732, 'Kalladiya', 22),
	(1733, 'Kalpitiya', 22),
	(1734, 'Kandakuliya', 22),
	(1735, 'Karativponparappi', 22),
	(1736, 'Karawitagara', 22),
	(1737, 'Karuwalagaswewa', 22),
	(1738, 'Katuneriya', 22),
	(1739, 'Kirimundalama', 22),
	(1740, 'Koswatta', 22),
	(1741, 'Kottantivu', 22),
	(1742, 'Kottukachchiya', 22),
	(1743, 'Kudawewa', 22),
	(1744, 'Kumarakattuwa', 22),
	(1745, 'Kurinjanpitiya', 22),
	(1746, 'Kuruketiyawa', 22),
	(1747, 'Lihiriyagama', 22),
	(1748, 'Lunuwila', 22),
	(1749, 'Madampe', 22),
	(1750, 'Madurankuliya', 22),
	(1751, 'Mahakumbukkadawala', 22),
	(1752, 'Mahauswewa', 22),
	(1753, 'Mahawewa', 22),
	(1754, 'Mampuri', 22),
	(1755, 'Mangalaeliya', 22),
	(1756, 'Marawila', 22),
	(1757, 'Mudalakkuliya', 22),
	(1758, 'Mugunuwatawana', 22),
	(1759, 'Mukkutoduwawa', 22),
	(1760, 'Mundel', 22),
	(1761, 'Muttibendiwila', 22),
	(1762, 'Nainamadama', 22),
	(1763, 'Nalladarankattuwa', 22),
	(1764, 'Nattandiya', 22),
	(1765, 'Nawagattegama', 22),
	(1766, 'Norachcholai', 22),
	(1767, 'Palaviya', 22),
	(1768, 'Pallama', 22),
	(1769, 'Palliwasalturai', 22),
	(1770, 'Panirendawa', 22),
	(1771, 'Pothuwatawana', 22),
	(1772, 'Puttalam', 22),
	(1773, 'Puttalam Cement Factory', 22),
	(1774, 'Rajakadaluwa', 22),
	(1775, 'Saliyawewa Junction', 22),
	(1776, 'Serukele', 22),
	(1777, 'Sirambiadiya', 22),
	(1778, 'Siyambalagashene', 22),
	(1779, 'Tabbowa', 22),
	(1780, 'Talawila Church', 22),
	(1781, 'Toduwawa', 22),
	(1782, 'Udappuwa', 22),
	(1783, 'Uridyawa', 22),
	(1784, 'Vanathawilluwa', 22),
	(1785, 'Waikkal', 22),
	(1786, 'Watugahamulla', 22),
	(1787, 'Wennappuwa', 22),
	(1788, 'Wijeyakatupotha', 22),
	(1789, 'Wilpotha', 22),
	(1790, 'Yogiyana', 22),
	(1791, 'Akarella', 23),
	(1792, 'Atakalanpanna', 23),
	(1793, 'Ayagama', 23),
	(1794, 'Balangoda', 23),
	(1795, 'Batatota', 23),
	(1796, 'Belihuloya', 23),
	(1797, 'Bolthumbe', 23),
	(1798, 'Bomluwageaina', 23),
	(1799, 'Bulutota', 23),
	(1800, 'Dambuluwana', 23),
	(1801, 'Daugala', 23),
	(1802, 'Dela', 23),
	(1803, 'Delwala', 23),
	(1804, 'Demuwatha', 23),
	(1805, 'Dodampe', 23),
	(1806, 'Doloswalakanda', 23),
	(1807, 'Dumbara Manana', 23),
	(1808, 'Eheliyagoda', 23),
	(1809, 'Elapatha', 23),
	(1810, 'Ellagawa', 23),
	(1811, 'Ellaulla', 23),
	(1812, 'Ellawala', 23),
	(1813, 'Embilipitiya', 23),
	(1814, 'Eratna', 23),
	(1815, 'Erepola', 23),
	(1816, 'Gabbela', 23),
	(1817, 'Gallella', 23),
	(1818, 'Gangeyaya', 23),
	(1819, 'Gawaragiriya', 23),
	(1820, 'Getahetta', 23),
	(1821, 'Gillimale', 23),
	(1822, 'Godagampola', 23),
	(1823, 'Godakawela', 23),
	(1824, 'Gurubewilagama', 23),
	(1825, 'Halpe', 23),
	(1826, 'Halwinna', 23),
	(1827, 'Handagiriya', 23),
	(1828, 'Hapugastenna', 23),
	(1829, 'Hatangala', 23),
	(1830, 'Hatarabage', 23),
	(1831, 'Hidellana', 23),
	(1832, 'Hiramadagama', 23),
	(1833, 'Ihalagama', 23),
	(1834, 'Ittakanda', 23),
	(1835, 'Kahangama', 23),
	(1836, 'Kahawatta', 23),
	(1837, 'Kalawana', 23),
	(1838, 'Kaltota', 23),
	(1839, 'Karandana', 23),
	(1840, 'Karangoda', 23),
	(1841, 'Kella Junction', 23),
	(1842, 'Kiriella', 23),
	(1843, 'Kolambageara', 23),
	(1844, 'Kolombugama', 23),
	(1845, 'Kolonna', 23),
	(1846, 'Kudawa', 23),
	(1847, 'Kuruwita', 23),
	(1848, 'Lellopitiya', 23),
	(1849, 'lmbulpe', 23),
	(1850, 'Madalagama', 23),
	(1851, 'Mahawalatenna', 23),
	(1852, 'Makandura Sabara', 23),
	(1853, 'Malwala Junction', 23),
	(1854, 'Marapana', 23),
	(1855, 'Matuwagalagama', 23),
	(1856, 'Medagalatur', 23),
	(1857, 'Meddekanda', 23),
	(1858, 'Minipura Dumbara', 23),
	(1859, 'Mitipola', 23),
	(1860, 'Morahela', 23),
	(1861, 'Mulendiyawala', 23),
	(1862, 'Mulgama', 23),
	(1863, 'Nawalakanda', 23),
	(1864, 'NawinnaPinnakanda', 23),
	(1865, 'Niralagama', 23),
	(1866, 'Nivitigala', 23),
	(1867, 'Omalpe', 23),
	(1868, 'Opanayaka', 23),
	(1869, 'Padalangala', 23),
	(1870, 'Pallebedda', 23),
	(1871, 'Pambagolla', 23),
	(1872, 'Panamura', 23),
	(1873, 'Panapitiya', 23),
	(1874, 'Panapola', 23),
	(1875, 'Panawala', 23),
	(1876, 'Parakaduwa', 23),
	(1877, 'Pebotuwa', 23),
	(1878, 'Pelmadulla', 23),
	(1879, 'Pimbura', 23),
	(1880, 'Pinnawala', 23),
	(1881, 'Pothupitiya', 23),
	(1882, 'Rajawaka', 23),
	(1883, 'Rakwana', 23),
	(1884, 'Ranwala', 23),
	(1885, 'Rassagala', 23),
	(1886, 'Ratna Hangamuwa', 23),
	(1887, 'Ratnapura', 23),
	(1888, 'Samanalawewa', 23),
	(1889, 'Sri Palabaddala', 23),
	(1890, 'Sudagala', 23),
	(1891, 'Talakolahinna', 23),
	(1892, 'Tanjantenna', 23),
	(1893, 'Teppanawa', 23),
	(1894, 'Tunkama', 23),
	(1895, 'Udaha Hawupe', 23),
	(1896, 'Udakarawita', 23),
	(1897, 'Udaniriella', 23),
	(1898, 'Udawalawe', 23),
	(1899, 'Ullinduwawa', 23),
	(1900, 'Veddagala', 23),
	(1901, 'Vijeriya', 23),
	(1902, 'Waleboda', 23),
	(1903, 'Watapotha', 23),
	(1904, 'Waturawa', 23),
	(1905, 'Weligepola', 23),
	(1906, 'Welipathayaya', 23),
	(1907, 'Wewelwatta', 23),
	(1908, 'Wikiliya', 23),
	(1909, 'Agbopura', 24),
	(1910, 'Buckmigama', 24),
	(1911, 'Chinabay', 24),
	(1912, 'Dehiwatte', 24),
	(1913, 'Echchilampattai', 24),
	(1914, 'Galmetiyawa', 24),
	(1915, 'Gomarankadawala', 24),
	(1916, 'Kaddaiparichchan', 24),
	(1917, 'Kanniya', 24),
	(1918, 'Kantalai', 24),
	(1919, 'Kantalai Sugar Factory', 24),
	(1920, 'Kiliveddy', 24),
	(1921, 'Kinniya', 24),
	(1922, 'Kuchchaveli', 24),
	(1923, 'Kumburupiddy', 24),
	(1924, 'Kurinchakemy', 24),
	(1925, 'Lankapatuna', 24),
	(1926, 'Mahadivulwewa', 24),
	(1927, 'Maharugiramam', 24),
	(1928, 'Mallikativu', 24),
	(1929, 'Mawadichenai', 24),
	(1930, 'Mullipothana', 24),
	(1931, 'Mutur', 24),
	(1932, 'Neelapola', 24),
	(1933, 'Nilaveli', 24),
	(1934, 'Pankulam', 24),
	(1935, 'Rottawewa', 24),
	(1936, 'Sampaltivu', 24),
	(1937, 'Sampur', 24),
	(1938, 'Serunuwara', 24),
	(1939, 'Seruwila', 24),
	(1940, 'Sirajnagar', 24),
	(1941, 'Somapura', 24),
	(1942, 'Tampalakamam', 24),
	(1943, 'Tiriyayi', 24),
	(1944, 'Toppur', 24),
	(1945, 'Trincomalee', 24),
	(1946, 'Vellamanal', 24),
	(1947, 'Wanela', 24),
	(1948, 'Vavuniya', 25),
	(1949, 'Akkarayankulam', 25),
	(1950, 'Cheddikulam', 25),
	(1951, 'Chemamadukkulam', 25),
	(1952, 'Elephant Pass', 25),
	(1953, 'Eluthumadduval', 25),
	(1954, 'Iranai lluppaikulam', 25),
	(1955, 'Iranaitiv', 25),
	(1956, 'Kanagarayankulam', 25),
	(1957, 'Kanavil', 25),
	(1958, 'Kandavalai', 25),
	(1959, 'Kavutharimunai', 25),
	(1960, 'Kilinochchi', 25),
	(1961, 'lyakachchi', 25),
	(1962, 'Mahakachchakodiya', 25),
	(1963, 'Mamaduwa', 25),
	(1964, 'Maraiyadithakulam', 25),
	(1965, 'Mulliyan', 25),
	(1966, 'Murasumoddai', 25),
	(1967, 'Nedunkemy', 25),
	(1968, 'Neriyakulam', 25),
	(1969, 'Omanthai', 25),
	(1970, 'Palamoddai', 25),
	(1971, 'Pallai', 25),
	(1972, 'Pallavarayankaddu', 25),
	(1973, 'Pampaimadu', 25),
	(1974, 'Paranthan', 25),
	(1975, 'Pavaikulam', 25),
	(1976, 'Periyathambanai', 25),
	(1977, 'Periyaulukkulam', 25),
	(1978, 'Purakari Nallur', 25),
	(1979, 'Ramanathapuram', 25),
	(1980, 'Sasthrikulankulam', 25),
	(1981, 'Sivapuram', 25),
	(1982, 'Skanthapuram', 25),
	(1983, 'Thalayadi', 25),
	(1984, 'Tharmapuram', 25),
	(1985, 'Uruthirapuram', 25),
	(1986, 'Vaddakachchi', 25),
	(1987, 'Vannerikkulam', 25),
	(1988, 'Varikkuttiyoor', 25),
	(1989, 'Veravil', 25),
	(1990, 'Vinayagapuram', 25);

-- Dumping structure for table lms_enviiz_latest_no_data.comments
CREATE TABLE IF NOT EXISTS `comments` (
  `comments_id` int NOT NULL AUTO_INCREMENT,
  `news_news_id` int NOT NULL,
  `students_student_id` int NOT NULL,
  `comment` text NOT NULL,
  `added_at` datetime NOT NULL,
  PRIMARY KEY (`comments_id`),
  KEY `fk_news_has_students_students2_idx` (`students_student_id`),
  KEY `fk_news_has_students_news2_idx` (`news_news_id`),
  CONSTRAINT `fk_news_has_students_news2` FOREIGN KEY (`news_news_id`) REFERENCES `news` (`news_id`),
  CONSTRAINT `fk_news_has_students_students2` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.comments: ~0 rows (approximately)
INSERT INTO `comments` (`comments_id`, `news_news_id`, `students_student_id`, `comment`, `added_at`) VALUES
	(2, 5, 6, 'hi', '2025-10-02 01:41:38');

-- Dumping structure for table lms_enviiz_latest_no_data.course_payments
CREATE TABLE IF NOT EXISTS `course_payments` (
  `course_payments_id` int NOT NULL AUTO_INCREMENT,
  `payment_status_payment_status_id` int NOT NULL,
  `payment_type_payment_type_id` int NOT NULL,
  `payment_method_payment_method_id` int NOT NULL,
  `date_paid` datetime DEFAULT NULL,
  `refference` varchar(45) NOT NULL,
  `payment_receipt` varchar(45) DEFAULT NULL,
  `discount` double NOT NULL,
  `amount` double NOT NULL,
  `discount_type_discount_type_id` int NOT NULL,
  `discount_note` varchar(45) DEFAULT NULL,
  `payment_plan_has_students_has_intakes_id` int NOT NULL,
  `bank_bank_id` int DEFAULT NULL,
  PRIMARY KEY (`course_payments_id`),
  KEY `fk_course_payments_payment_status1_idx` (`payment_status_payment_status_id`),
  KEY `fk_course_payments_payment_type1_idx` (`payment_type_payment_type_id`),
  KEY `fk_course_payments_payment_method1_idx` (`payment_method_payment_method_id`),
  KEY `fk_course_payments_discount_type1_idx` (`discount_type_discount_type_id`),
  KEY `fk_course_payments_payment_plan_has_students_has_intakes1_idx` (`payment_plan_has_students_has_intakes_id`),
  KEY `fk_course_payments_bank1_idx` (`bank_bank_id`),
  CONSTRAINT `fk_course_payments_bank1` FOREIGN KEY (`bank_bank_id`) REFERENCES `bank` (`bank_id`),
  CONSTRAINT `fk_course_payments_discount_type1` FOREIGN KEY (`discount_type_discount_type_id`) REFERENCES `discount_type` (`discount_type_id`),
  CONSTRAINT `fk_course_payments_payment_method1` FOREIGN KEY (`payment_method_payment_method_id`) REFERENCES `payment_method` (`payment_method_id`),
  CONSTRAINT `fk_course_payments_payment_plan_has_students_has_intakes1` FOREIGN KEY (`payment_plan_has_students_has_intakes_id`) REFERENCES `payment_plan_has_students_has_intakes` (`payment_plan_has_students_has_intakes_id`),
  CONSTRAINT `fk_course_payments_payment_status1` FOREIGN KEY (`payment_status_payment_status_id`) REFERENCES `payment_status` (`payment_status_id`),
  CONSTRAINT `fk_course_payments_payment_type1` FOREIGN KEY (`payment_type_payment_type_id`) REFERENCES `payment_type` (`payment_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.course_payments: ~16 rows (approximately)
INSERT INTO `course_payments` (`course_payments_id`, `payment_status_payment_status_id`, `payment_type_payment_type_id`, `payment_method_payment_method_id`, `date_paid`, `refference`, `payment_receipt`, `discount`, `amount`, `discount_type_discount_type_id`, `discount_note`, `payment_plan_has_students_has_intakes_id`, `bank_bank_id`) VALUES
	(1, 1, 1, 1, NULL, 'Y1K0O5O2', NULL, 0, 25000, 1, '', 1, NULL),
	(2, 2, 1, 1, '2025-11-20 20:59:23', 'N2CLPMCJ', NULL, 0, 25000, 1, '', 2, 1),
	(3, 1, 3, 1, NULL, 'HMCQR85G', NULL, 0, 50000, 1, '', 3, NULL),
	(4, 4, 3, 1, '2025-09-26 13:07:21', '80DSUBBM', NULL, 1000, 5000, 2, '1000 OFF', 4, 1),
	(5, 1, 3, 1, NULL, 'EWJIP1LL', NULL, 0, 2000, 1, 'No Discounts', 5, NULL),
	(6, 2, 3, 1, '2025-11-20 23:56:52', '9D5DXDWF', NULL, 0, 5000, 1, '', 2, 1),
	(7, 2, 3, 1, '2025-09-26 12:56:10', 'NOHBAPMI', NULL, 0, 5000, 1, '', 6, 1),
	(8, 2, 3, 1, '2025-09-26 12:55:40', 'WTUI805E', NULL, 0, 5000, 1, '', 6, 1),
	(9, 1, 3, 1, NULL, '85Y7GCIY', NULL, 1000, 10000, 3, '10% OFF', 7, NULL),
	(10, 8, 3, 1, '2025-09-26 12:54:49', '0DMGR6Z2', NULL, 0, 10000, 1, 'No', 8, 1),
	(11, 1, 1, 1, NULL, 'HPQC3HRF', NULL, 0, 25000, 1, '', 1, NULL),
	(12, 1, 1, 1, NULL, 'HZ38HK2Y', NULL, 0, 25000, 1, '', 6, NULL),
	(13, 2, 3, 1, '2025-11-20 17:05:07', 'SA5A2LIJ', NULL, 0, 10000, 1, '', 2, 1),
	(14, 1, 3, 1, NULL, '5JO16ZHN', NULL, 0, 10000, 1, '', 8, NULL),
	(15, 1, 3, 1, NULL, 'MVHIW7NG', NULL, 0, 10000, 1, '', 1, NULL),
	(16, 1, 3, 1, '2025-11-16 11:24:48', '23', NULL, 0, 10000, 1, '', 2, NULL),
	(17, 1, 3, 1, NULL, 'QV9LNBN2', NULL, 10, 5000, 3, '', 2, NULL);

-- Dumping structure for table lms_enviiz_latest_no_data.course_payment_slips
CREATE TABLE IF NOT EXISTS `course_payment_slips` (
  `course_payment_slip_id` int NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(45) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `slip_hash` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  `slip_name` varchar(45) NOT NULL,
  `course_payments_course_payments_id` int NOT NULL,
  PRIMARY KEY (`course_payment_slip_id`) USING BTREE,
  KEY `fk_course_payment_slips_course_payments1_idx` (`course_payments_course_payments_id`),
  CONSTRAINT `fk_course_payment_slips_course_payments1` FOREIGN KEY (`course_payments_course_payments_id`) REFERENCES `course_payments` (`course_payments_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.course_payment_slips: ~6 rows (approximately)
INSERT INTO `course_payment_slips` (`course_payment_slip_id`, `reference_number`, `slip_hash`, `slip_name`, `course_payments_course_payments_id`) VALUES
	(1, '', '', '68d63fc11ec69.pdf', 10),
	(2, '', '', '68d63ff441da3.pdf', 8),
	(3, '', '', '68d6401268d93.pdf', 7),
	(4, '', '', '68d642b1d19ff.pdf', 4),
	(8, NULL, NULL, '691efcebd7bd0.pdf', 13),
	(9, NULL, NULL, '691f33d3437fd.pdf', 2),
	(10, NULL, NULL, '691f5d6c84f88.jpg', 6);

-- Dumping structure for table lms_enviiz_latest_no_data.discount_type
CREATE TABLE IF NOT EXISTS `discount_type` (
  `discount_type_id` int NOT NULL AUTO_INCREMENT,
  `discount_type` varchar(45) NOT NULL,
  PRIMARY KEY (`discount_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.discount_type: ~3 rows (approximately)
INSERT INTO `discount_type` (`discount_type_id`, `discount_type`) VALUES
	(1, 'No Discount'),
	(2, 'Fixed Amount Discount'),
	(3, 'Percentage Based Discount');

-- Dumping structure for table lms_enviiz_latest_no_data.district
CREATE TABLE IF NOT EXISTS `district` (
  `district_id` int NOT NULL AUTO_INCREMENT,
  `district` varchar(45) NOT NULL,
  `province_province_id` int NOT NULL,
  PRIMARY KEY (`district_id`),
  KEY `fk_district_province1_idx` (`province_province_id`),
  CONSTRAINT `fk_district_province1` FOREIGN KEY (`province_province_id`) REFERENCES `province` (`province_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.district: ~25 rows (approximately)
INSERT INTO `district` (`district_id`, `district`, `province_province_id`) VALUES
	(1, 'Ampara', 2),
	(2, 'Anuradhapura', 3),
	(3, 'Badulla', 8),
	(4, 'Batticaloa', 2),
	(5, 'Colombo', 9),
	(6, 'Galle', 7),
	(7, 'Gampaha', 9),
	(8, 'Hambantota', 7),
	(9, 'Jaffna', 4),
	(10, 'Kalutara', 9),
	(11, 'Kandy', 1),
	(12, 'Kegalle', 6),
	(13, 'Kilinochchi', 4),
	(14, 'Kurunegala', 5),
	(15, 'Mannar', 4),
	(16, 'Matale', 1),
	(17, 'Matara', 7),
	(18, 'Monaragala', 8),
	(19, 'Mullaitivu', 4),
	(20, 'Nuwara Eliya', 1),
	(21, 'Polonnaruwa', 3),
	(22, 'Puttalam', 5),
	(23, 'Ratnapura', 6),
	(24, 'Trincomalee', 2),
	(25, 'Vavuniya', 4);

-- Dumping structure for table lms_enviiz_latest_no_data.earning_status
CREATE TABLE IF NOT EXISTS `earning_status` (
  `earning_status_id` int NOT NULL AUTO_INCREMENT,
  `earning_status` varchar(45) NOT NULL,
  PRIMARY KEY (`earning_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.earning_status: ~3 rows (approximately)
INSERT INTO `earning_status` (`earning_status_id`, `earning_status`) VALUES
	(1, 'Pending'),
	(2, 'Approval'),
	(3, 'Reject');

-- Dumping structure for table lms_enviiz_latest_no_data.essay_questions
CREATE TABLE IF NOT EXISTS `essay_questions` (
  `essay_question_id` int NOT NULL AUTO_INCREMENT,
  `essay_question` text NOT NULL,
  `online_exam_online_exam_id` int NOT NULL,
  PRIMARY KEY (`essay_question_id`),
  KEY `fk_essay_questions_online_exam1_idx` (`online_exam_online_exam_id`),
  CONSTRAINT `fk_essay_questions_online_exam1` FOREIGN KEY (`online_exam_online_exam_id`) REFERENCES `online_exam` (`online_exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.essay_questions: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.exams
CREATE TABLE IF NOT EXISTS `exams` (
  `exam_id` int NOT NULL AUTO_INCREMENT,
  `exam_name` varchar(45) NOT NULL,
  `exam_types_exam_type_id` int NOT NULL,
  `exam_date` date NOT NULL,
  `subjects_subject_id` int NOT NULL,
  `exams_order` int NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`exam_id`),
  KEY `fk_exams_exam_types1_idx` (`exam_types_exam_type_id`),
  KEY `fk_exams_subjects1_idx` (`subjects_subject_id`),
  KEY `fk_exams_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_exams_exam_types1` FOREIGN KEY (`exam_types_exam_type_id`) REFERENCES `exam_types` (`exam_type_id`),
  CONSTRAINT `fk_exams_subjects1` FOREIGN KEY (`subjects_subject_id`) REFERENCES `subjects` (`subject_id`),
  CONSTRAINT `fk_exams_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.exams: ~6 rows (approximately)
INSERT INTO `exams` (`exam_id`, `exam_name`, `exam_types_exam_type_id`, `exam_date`, `subjects_subject_id`, `exams_order`, `date_added`, `users_user_id`) VALUES
	(1, 'Sample', 3, '2025-07-02', 1, 0, '2025-07-02 13:38:06', 3),
	(2, 'Sample Quiz', 3, '2025-07-02', 1, 0, '2025-07-02 13:41:05', 3),
	(3, 'Sample Quiz', 3, '2025-07-02', 1, 0, '2025-07-02 13:42:25', 3),
	(4, 'Sample Quiz', 3, '2025-07-02', 2, 0, '2025-07-02 13:45:52', 3),
	(5, 'Sample Quiz', 3, '2025-07-02', 1, 0, '2025-07-02 20:14:20', 3),
	(6, 'Test Quiz', 3, '2025-08-23', 1, 0, '2025-08-23 00:39:00', 2),
	(9, 'Test', 1, '2025-10-31', 1, 0, '2025-10-31 01:58:31', 3);

-- Dumping structure for table lms_enviiz_latest_no_data.exam_payments
CREATE TABLE IF NOT EXISTS `exam_payments` (
  `exam_payments_id` int NOT NULL AUTO_INCREMENT,
  `lecturers_lecturers_id` int NOT NULL,
  `exams_exam_id` int NOT NULL,
  `amount` double NOT NULL,
  `earning_status_earning_status_id` int NOT NULL,
  PRIMARY KEY (`exam_payments_id`),
  KEY `fk_lecturers_has_exams_exams1_idx` (`exams_exam_id`),
  KEY `fk_lecturers_has_exams_lecturers1_idx` (`lecturers_lecturers_id`),
  KEY `fk_exam_payments_earning_status1_idx` (`earning_status_earning_status_id`),
  CONSTRAINT `fk_exam_payments_earning_status1` FOREIGN KEY (`earning_status_earning_status_id`) REFERENCES `earning_status` (`earning_status_id`),
  CONSTRAINT `fk_lecturers_has_exams_exams1` FOREIGN KEY (`exams_exam_id`) REFERENCES `exams` (`exam_id`),
  CONSTRAINT `fk_lecturers_has_exams_lecturers1` FOREIGN KEY (`lecturers_lecturers_id`) REFERENCES `lecturers` (`lecturers_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.exam_payments: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.exam_payments_logs
CREATE TABLE IF NOT EXISTS `exam_payments_logs` (
  `exam_payments_logs_id` int NOT NULL AUTO_INCREMENT,
  `note` varchar(45) NOT NULL,
  `date_updated` datetime NOT NULL,
  `exam_payments_exam_payments_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`exam_payments_logs_id`),
  KEY `fk_exam_payments_logs_exam_payments1_idx` (`exam_payments_exam_payments_id`),
  KEY `fk_exam_payments_logs_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_exam_payments_logs_exam_payments1` FOREIGN KEY (`exam_payments_exam_payments_id`) REFERENCES `exam_payments` (`exam_payments_id`),
  CONSTRAINT `fk_exam_payments_logs_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.exam_payments_logs: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.exam_status
CREATE TABLE IF NOT EXISTS `exam_status` (
  `exam_status_id` int NOT NULL AUTO_INCREMENT,
  `exam_status` varchar(45) NOT NULL,
  PRIMARY KEY (`exam_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.exam_status: ~3 rows (approximately)
INSERT INTO `exam_status` (`exam_status_id`, `exam_status`) VALUES
	(1, 'Pending'),
	(2, 'Marking Completed'),
	(3, 'Marks Released');

-- Dumping structure for table lms_enviiz_latest_no_data.exam_types
CREATE TABLE IF NOT EXISTS `exam_types` (
  `exam_type_id` int NOT NULL AUTO_INCREMENT,
  `exam_type` varchar(45) NOT NULL,
  PRIMARY KEY (`exam_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.exam_types: ~3 rows (approximately)
INSERT INTO `exam_types` (`exam_type_id`, `exam_type`) VALUES
	(1, 'Physical Exam'),
	(2, 'Online Exam'),
	(3, 'Quiz Exam');

-- Dumping structure for table lms_enviiz_latest_no_data.faculties
CREATE TABLE IF NOT EXISTS `faculties` (
  `faculty_id` int NOT NULL AUTO_INCREMENT,
  `faculty` varchar(100) NOT NULL,
  `faculty_cover` varchar(45) NOT NULL,
  `date_added` datetime NOT NULL,
  `faculty_order` int NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`faculty_id`),
  KEY `fk_faculties_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_faculties_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.faculties: ~6 rows (approximately)
INSERT INTO `faculties` (`faculty_id`, `faculty`, `faculty_cover`, `date_added`, `faculty_order`, `users_user_id`) VALUES
	(1, 'Faculty of Technology', '68f29fe12eb12.png', '2025-06-11 20:31:57', 0, 1),
	(2, 'Faculty of Science', '68499a8004745.png', '2025-06-11 20:32:24', 0, 1),
	(3, 'Faculty of Medicine', '68499af5df4d6.png', '2025-06-11 20:34:21', 0, 1),
	(4, 'Faculty of Arts', '68499b6df02f0.png', '2025-06-11 20:36:21', 0, 1),
	(5, 'Faculty of Law', '68499baa8f592.png', '2025-06-11 20:37:22', 0, 1),
	(6, 'Faculty of Graduate Studies', '68499be82268a.png', '2025-06-11 20:38:24', 0, 1),
	(9, 'Faculty of Education23', '68f29ef32f121.png', '2025-10-18 01:24:27', 0, 3);

-- Dumping structure for table lms_enviiz_latest_no_data.faq
CREATE TABLE IF NOT EXISTS `faq` (
  `faq_id` int NOT NULL AUTO_INCREMENT,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  `faq_order` int NOT NULL,
  `video_link` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,
  PRIMARY KEY (`faq_id`),
  KEY `fk_faq_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_faq_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.faq: ~0 rows (approximately)
INSERT INTO `faq` (`faq_id`, `question`, `answer`, `date_added`, `users_user_id`, `faq_order`, `video_link`) VALUES
	(5, 'How do I log in to the LMS?', '<p>\n\nWhen you register with your institution, you will receive login credentials (username and password) by email or SMS. Use those details to log in through the LMS portal or mobile app. The first time you log in, you may be asked to reset your password for security. If you forget your password later, you can use the “Forgot Password” option or contact your course administrator.\n<br /></p>', '2025-10-02 02:48:00', 3, 0, '<iframe width="560" height="315" src="https://www.youtube.com/embed/8ONk3yjELBY?si=MVHffrsCiXTGkFKm" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>');

-- Dumping structure for table lms_enviiz_latest_no_data.gender
CREATE TABLE IF NOT EXISTS `gender` (
  `gender_id` int NOT NULL AUTO_INCREMENT,
  `gender` varchar(45) NOT NULL,
  PRIMARY KEY (`gender_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.gender: ~2 rows (approximately)
INSERT INTO `gender` (`gender_id`, `gender`) VALUES
	(1, 'Male'),
	(2, 'Female');

-- Dumping structure for table lms_enviiz_latest_no_data.guardian_details
CREATE TABLE IF NOT EXISTS `guardian_details` (
  `guardian_details_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(45) NOT NULL,
  `last_name` varchar(45) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile` varchar(12) NOT NULL,
  `address` text,
  `title_title_id` int NOT NULL,
  `students_student_id` int NOT NULL,
  `guardian_relation_guardian_relation_id` int NOT NULL,
  PRIMARY KEY (`guardian_details_id`),
  KEY `fk_guardian_details_title1_idx` (`title_title_id`),
  KEY `fk_guardian_details_students1_idx` (`students_student_id`),
  KEY `fk_guardian_details_guardian_relation1_idx` (`guardian_relation_guardian_relation_id`),
  CONSTRAINT `fk_guardian_details_guardian_relation1` FOREIGN KEY (`guardian_relation_guardian_relation_id`) REFERENCES `guardian_relation` (`guardian_relation_id`),
  CONSTRAINT `fk_guardian_details_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`),
  CONSTRAINT `fk_guardian_details_title1` FOREIGN KEY (`title_title_id`) REFERENCES `title` (`title_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.guardian_details: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.guardian_relation
CREATE TABLE IF NOT EXISTS `guardian_relation` (
  `guardian_relation_id` int NOT NULL AUTO_INCREMENT,
  `guardian_relation` varchar(45) NOT NULL,
  PRIMARY KEY (`guardian_relation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.guardian_relation: ~9 rows (approximately)
INSERT INTO `guardian_relation` (`guardian_relation_id`, `guardian_relation`) VALUES
	(1, 'Mother'),
	(2, 'Father'),
	(3, 'Husband'),
	(4, 'Wife'),
	(5, 'Brother'),
	(6, 'Sister'),
	(7, 'Grand Father'),
	(8, 'Grand Mother'),
	(9, 'Legal Guardian');

-- Dumping structure for table lms_enviiz_latest_no_data.help_desk
CREATE TABLE IF NOT EXISTS `help_desk` (
  `help_desk` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `help_desk_category_help_desk_category_id` int NOT NULL,
  `help_desk_order` int NOT NULL,
  PRIMARY KEY (`help_desk`),
  KEY `fk_help_desk_help_desk_category1_idx` (`help_desk_category_help_desk_category_id`),
  CONSTRAINT `fk_help_desk_help_desk_category1` FOREIGN KEY (`help_desk_category_help_desk_category_id`) REFERENCES `help_desk_category` (`help_desk_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.help_desk: ~21 rows (approximately)
INSERT INTO `help_desk` (`help_desk`, `title`, `help_desk_category_help_desk_category_id`, `help_desk_order`) VALUES
	(1, 'Course content issues', 1, 1),
	(2, 'Lecture recordings', 1, 2),
	(3, 'Assignment guidance', 1, 3),
	(5, 'Exam queries', 1, 4),
	(6, 'Quiz Submission', 1, 5),
	(7, 'Login/Password reset', 2, 1),
	(8, 'Website/App errors', 2, 2),
	(9, 'Video/audio not working', 2, 3),
	(10, 'System bugs', 2, 4),
	(11, 'Payment confirmation', 3, 1),
	(12, 'Refund request', 3, 2),
	(13, 'Scholarship/discount inquiries', 3, 3),
	(14, 'Invoice issues', 3, 4),
	(15, 'Course/program enrollment', 4, 1),
	(16, 'Intake details', 4, 2),
	(17, 'Timetable issues', 4, 3),
	(18, 'Subject selection', 4, 4),
	(19, 'Policies & regulations', 5, 1),
	(20, 'Event information', 5, 2),
	(21, 'Certificates/Transcripts requests', 5, 3),
	(22, 'Other administrative queries', 5, 4);

-- Dumping structure for table lms_enviiz_latest_no_data.help_desk_category
CREATE TABLE IF NOT EXISTS `help_desk_category` (
  `help_desk_category_id` int NOT NULL AUTO_INCREMENT,
  `help_desk_category` varchar(45) NOT NULL,
  PRIMARY KEY (`help_desk_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.help_desk_category: ~5 rows (approximately)
INSERT INTO `help_desk_category` (`help_desk_category_id`, `help_desk_category`) VALUES
	(1, 'Academic Support'),
	(2, 'Technical Support'),
	(3, 'Payments & Finance'),
	(4, 'Enrollment & Registration'),
	(5, 'General Inquiries');

-- Dumping structure for table lms_enviiz_latest_no_data.help_desk_logs
CREATE TABLE IF NOT EXISTS `help_desk_logs` (
  `help_desk_logs_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `students_has_help_desk_students_has_help_desk_id` int NOT NULL,
  `log` varchar(100) NOT NULL,
  `added_at` datetime NOT NULL,
  PRIMARY KEY (`help_desk_logs_id`),
  KEY `fk_users_has_students_has_help_desk_students_has_help_desk1_idx` (`students_has_help_desk_students_has_help_desk_id`),
  KEY `fk_users_has_students_has_help_desk_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_users_has_students_has_help_desk_students_has_help_desk1` FOREIGN KEY (`students_has_help_desk_students_has_help_desk_id`) REFERENCES `students_has_help_desk` (`students_has_help_desk_id`),
  CONSTRAINT `fk_users_has_students_has_help_desk_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.help_desk_logs: ~2 rows (approximately)
INSERT INTO `help_desk_logs` (`help_desk_logs_id`, `users_user_id`, `students_has_help_desk_students_has_help_desk_id`, `log`, `added_at`) VALUES
	(8, 3, 9, 'resolved', '2025-10-17 10:59:02'),
	(9, 3, 10, 'Assign user : Pawani Perera(Academic)', '2025-10-17 11:25:30'),
	(10, 3, 10, 'escalted', '2025-10-17 11:25:56');

-- Dumping structure for table lms_enviiz_latest_no_data.help_desk_status
CREATE TABLE IF NOT EXISTS `help_desk_status` (
  `help_desk_status_id` int NOT NULL AUTO_INCREMENT,
  `help_desk_status` varchar(45) NOT NULL,
  PRIMARY KEY (`help_desk_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.help_desk_status: ~4 rows (approximately)
INSERT INTO `help_desk_status` (`help_desk_status_id`, `help_desk_status`) VALUES
	(1, 'In Progress'),
	(2, 'Escalated'),
	(3, 'Resolved'),
	(4, 'Closed');

-- Dumping structure for table lms_enviiz_latest_no_data.intakes
CREATE TABLE IF NOT EXISTS `intakes` (
  `intake_id` int NOT NULL AUTO_INCREMENT,
  `intake` varchar(45) NOT NULL,
  `intake_cover` varchar(45) NOT NULL,
  `programmes_programme_id` int NOT NULL,
  `date_added` datetime NOT NULL,
  `intakes_order` int NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`intake_id`),
  KEY `fk_intakes_programmes1_idx` (`programmes_programme_id`),
  KEY `fk_intakes_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_intakes_programmes1` FOREIGN KEY (`programmes_programme_id`) REFERENCES `programmes` (`programme_id`),
  CONSTRAINT `fk_intakes_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.intakes: ~7 rows (approximately)
INSERT INTO `intakes` (`intake_id`, `intake`, `intake_cover`, `programmes_programme_id`, `date_added`, `intakes_order`, `users_user_id`) VALUES
	(1, '2024/2025 Intake', '6849c1eadf665.png', 1, '2025-06-11 23:20:34', 0, 1),
	(2, '2025/2026 Intake', '6849c20976505.png', 1, '2025-06-11 23:21:05', 0, 1),
	(3, '2024/2025 Intake', '6849c22135596.png', 2, '2025-06-11 23:21:29', 0, 1),
	(4, '2025/2026 Intake', '6849c59269745.png', 2, '2025-06-11 23:36:10', 0, 1),
	(5, '2024/2025 Intake', '6849c81642b09.png', 3, '2025-06-11 23:46:54', 0, 1),
	(6, '2025/2026 Intake', '6849c8290f49b.png', 3, '2025-06-11 23:47:13', 0, 1),
	(7, '2026/2027 Batch', '6849c914cfd5c.png', 1, '2025-06-11 23:51:08', 0, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.lecturers
CREATE TABLE IF NOT EXISTS `lecturers` (
  `lecturers_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `subjects_subject_id` int NOT NULL,
  `added_by` int NOT NULL,
  `added_at` datetime NOT NULL,
  `status_status_id` int NOT NULL,
  `per_assignment_fee` double NOT NULL,
  `per_examination_fee` double NOT NULL,
  `hourly_rate` double NOT NULL,
  PRIMARY KEY (`lecturers_id`),
  KEY `fk_users_has_subjects_subjects1_idx` (`subjects_subject_id`),
  KEY `fk_users_has_subjects_users1_idx` (`users_user_id`),
  KEY `fk_lecturers_users1_idx` (`added_by`),
  KEY `fk_lecturers_status1_idx` (`status_status_id`),
  CONSTRAINT `fk_lecturers_status1` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_lecturers_users1` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_users_has_subjects_subjects1` FOREIGN KEY (`subjects_subject_id`) REFERENCES `subjects` (`subject_id`),
  CONSTRAINT `fk_users_has_subjects_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.lecturers: ~4 rows (approximately)
INSERT INTO `lecturers` (`lecturers_id`, `users_user_id`, `subjects_subject_id`, `added_by`, `added_at`, `status_status_id`, `per_assignment_fee`, `per_examination_fee`, `hourly_rate`) VALUES
	(1, 5, 1, 3, '2025-06-26 01:36:58', 1, 1000, 2000, 5000),
	(2, 5, 3, 3, '2025-06-26 02:05:48', 1, 1000, 1000, 5000),
	(3, 8, 1, 3, '2025-08-13 22:59:51', 1, 2000, 5000, 1500),
	(4, 5, 2, 1, '2025-08-14 00:03:54', 1, 500, 500, 1000);

-- Dumping structure for table lms_enviiz_latest_no_data.lecturers_has_students_has_assignments
CREATE TABLE IF NOT EXISTS `lecturers_has_students_has_assignments` (
  `lecturers_has_students_has_assignments_id` int NOT NULL AUTO_INCREMENT,
  `lecturers_lecturers_id` int NOT NULL,
  `students_has_assignments_students_has_assignments_id` int NOT NULL,
  `marks` double DEFAULT NULL,
  `submission_sts_submission_sts_id` int NOT NULL,
  PRIMARY KEY (`lecturers_has_students_has_assignments_id`),
  KEY `fk_lecturers_has_students_has_assignments_students_has_assi_idx` (`students_has_assignments_students_has_assignments_id`),
  KEY `fk_lecturers_has_students_has_assignments_lecturers1_idx` (`lecturers_lecturers_id`),
  KEY `fk_lecturers_has_students_has_assignments_submission_sts1_idx` (`submission_sts_submission_sts_id`),
  CONSTRAINT `fk_lecturers_has_students_has_assignments_lecturers1` FOREIGN KEY (`lecturers_lecturers_id`) REFERENCES `lecturers` (`lecturers_id`),
  CONSTRAINT `fk_lecturers_has_students_has_assignments_students_has_assign1` FOREIGN KEY (`students_has_assignments_students_has_assignments_id`) REFERENCES `students_has_assignments` (`students_has_assignments_id`),
  CONSTRAINT `fk_lecturers_has_students_has_assignments_submission_sts1` FOREIGN KEY (`submission_sts_submission_sts_id`) REFERENCES `submission_sts` (`submission_sts_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.lecturers_has_students_has_assignments: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.lecturer_payments
CREATE TABLE IF NOT EXISTS `lecturer_payments` (
  `lecturer_payments_id` int NOT NULL AUTO_INCREMENT,
  `lecturers_lecturers_id` int NOT NULL,
  `time_table_time_table_id` int NOT NULL,
  `amount` double NOT NULL,
  `earning_status_earning_status_id` int NOT NULL,
  PRIMARY KEY (`lecturer_payments_id`),
  KEY `fk_lecturers_has_time_table_time_table1_idx` (`time_table_time_table_id`),
  KEY `fk_lecturers_has_time_table_lecturers1_idx` (`lecturers_lecturers_id`),
  KEY `fk_lecturer_payments_earning_status1_idx` (`earning_status_earning_status_id`),
  CONSTRAINT `fk_lecturer_payments_earning_status1` FOREIGN KEY (`earning_status_earning_status_id`) REFERENCES `earning_status` (`earning_status_id`),
  CONSTRAINT `fk_lecturers_has_time_table_lecturers1` FOREIGN KEY (`lecturers_lecturers_id`) REFERENCES `lecturers` (`lecturers_id`),
  CONSTRAINT `fk_lecturers_has_time_table_time_table1` FOREIGN KEY (`time_table_time_table_id`) REFERENCES `time_table` (`time_table_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.lecturer_payments: ~0 rows (approximately)
INSERT INTO `lecturer_payments` (`lecturer_payments_id`, `lecturers_lecturers_id`, `time_table_time_table_id`, `amount`, `earning_status_earning_status_id`) VALUES
	(2, 3, 22, 825, 1),
	(3, 1, 23, 14900, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.lecturer_payments_logs
CREATE TABLE IF NOT EXISTS `lecturer_payments_logs` (
  `lecturer_payments_logs_id` int NOT NULL AUTO_INCREMENT,
  `note` varchar(45) NOT NULL,
  `date_updated` datetime NOT NULL,
  `lecturer_payments_lecturer_payments_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`lecturer_payments_logs_id`),
  KEY `fk_lecturer_payments_logs_lecturer_payments1_idx` (`lecturer_payments_lecturer_payments_id`),
  KEY `fk_lecturer_payments_logs_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_lecturer_payments_logs_lecturer_payments1` FOREIGN KEY (`lecturer_payments_lecturer_payments_id`) REFERENCES `lecturer_payments` (`lecturer_payments_id`),
  CONSTRAINT `fk_lecturer_payments_logs_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.lecturer_payments_logs: ~0 rows (approximately)
INSERT INTO `lecturer_payments_logs` (`lecturer_payments_logs_id`, `note`, `date_updated`, `lecturer_payments_lecturer_payments_id`, `users_user_id`) VALUES
	(3, 'Add Lecture Payment', '2025-10-19 11:06:23', 2, 3),
	(4, 'Add Lecture Payment', '2025-10-20 23:07:55', 3, 3);

-- Dumping structure for table lms_enviiz_latest_no_data.lecturer_payouts
CREATE TABLE IF NOT EXISTS `lecturer_payouts` (
  `lecturer_payouts_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `amount` double NOT NULL,
  `date` datetime NOT NULL,
  `payout_status_payout_status_id` int NOT NULL,
  PRIMARY KEY (`lecturer_payouts_id`),
  KEY `fk_lecturer_payouts_lecturers1_idx` (`users_user_id`) USING BTREE,
  KEY `FK_lecturer_payouts_payout_status` (`payout_status_payout_status_id`),
  CONSTRAINT `FK_lecturer_payouts_payout_status` FOREIGN KEY (`payout_status_payout_status_id`) REFERENCES `payout_status` (`payout_status_id`),
  CONSTRAINT `FK_lecturer_payouts_users` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.lecturer_payouts: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.lecture_attendnace_status
CREATE TABLE IF NOT EXISTS `lecture_attendnace_status` (
  `lecture_attendnace_status_id` int NOT NULL AUTO_INCREMENT,
  `lecture_attendnace_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`lecture_attendnace_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table lms_enviiz_latest_no_data.lecture_attendnace_status: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.lecture_log
CREATE TABLE IF NOT EXISTS `lecture_log` (
  `lecture_log_id` int NOT NULL AUTO_INCREMENT,
  `time_table_time_table_id` int NOT NULL,
  `lecture_progress_lecture_progress_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  `date_updated` datetime NOT NULL,
  PRIMARY KEY (`lecture_log_id`),
  KEY `fk_table1_time_table1_idx` (`time_table_time_table_id`),
  KEY `fk_table1_lecture_progress1_idx` (`lecture_progress_lecture_progress_id`),
  KEY `fk_lecture_log_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_lecture_log_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_table1_lecture_progress1` FOREIGN KEY (`lecture_progress_lecture_progress_id`) REFERENCES `lecture_progress` (`lecture_progress_id`),
  CONSTRAINT `fk_table1_time_table1` FOREIGN KEY (`time_table_time_table_id`) REFERENCES `time_table` (`time_table_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.lecture_log: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.lecture_progress
CREATE TABLE IF NOT EXISTS `lecture_progress` (
  `lecture_progress_id` int NOT NULL AUTO_INCREMENT,
  `lecture_progress` varchar(45) NOT NULL,
  PRIMARY KEY (`lecture_progress_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.lecture_progress: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.lecture_recording
CREATE TABLE IF NOT EXISTS `lecture_recording` (
  `lecture_recording_id` int NOT NULL AUTO_INCREMENT,
  `lecture_recording` text NOT NULL,
  `time_table_time_table_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  `added_at` datetime NOT NULL,
  `recording_thumbnail` varchar(45) NOT NULL,
  `expire_date` date NOT NULL,
  PRIMARY KEY (`lecture_recording_id`),
  KEY `fk_lecture_recording_time_table1_idx` (`time_table_time_table_id`),
  KEY `fk_lecture_recording_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_lecture_recording_time_table1` FOREIGN KEY (`time_table_time_table_id`) REFERENCES `time_table` (`time_table_id`),
  CONSTRAINT `fk_lecture_recording_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.lecture_recording: ~0 rows (approximately)
INSERT INTO `lecture_recording` (`lecture_recording_id`, `lecture_recording`, `time_table_time_table_id`, `users_user_id`, `added_at`, `recording_thumbnail`, `expire_date`) VALUES
	(2, '<iframe width="560" height="315" src="https://www.youtube.com/embed/EngW7tLk6R8?si=aeYJLrvYTTO5W4i5" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>', 22, 3, '2025-10-19 13:02:51', '68f5d53830cc1.png', '2025-10-30'),
	(3, '<iframe width="560" height="315" src="https://www.youtube.com/embed/K-vzevjxhKs?si=c0Gj0U1KdH-qvU4C" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>', 23, 3, '2025-10-20 23:13:21', '68f674b921093.png', '2025-10-23'),
	(4, '<iframe width="560" height="315" src="https://www.youtube.com/embed/I6H8umS2J8E?si=IlfPV8bhzvcLISA4" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>', 34, 3, '2025-11-08 14:40:46', '690f0916e346b.png', '2025-11-21');

-- Dumping structure for table lms_enviiz_latest_no_data.lecture_type
CREATE TABLE IF NOT EXISTS `lecture_type` (
  `lecture_type_id` int NOT NULL AUTO_INCREMENT,
  `lecture_type` varchar(45) NOT NULL,
  PRIMARY KEY (`lecture_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.lecture_type: ~3 rows (approximately)
INSERT INTO `lecture_type` (`lecture_type_id`, `lecture_type`) VALUES
	(1, 'Physical'),
	(2, 'Online'),
	(3, 'Recording');

-- Dumping structure for table lms_enviiz_latest_no_data.levels
CREATE TABLE IF NOT EXISTS `levels` (
  `level_id` int NOT NULL AUTO_INCREMENT,
  `level` varchar(100) NOT NULL,
  `level_cover` varchar(45) DEFAULT NULL,
  `intakes_intake_id` int NOT NULL,
  `levels_order` int NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`level_id`),
  KEY `fk_levels_intakes1_idx` (`intakes_intake_id`),
  KEY `fk_levels_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_levels_intakes1` FOREIGN KEY (`intakes_intake_id`) REFERENCES `intakes` (`intake_id`),
  CONSTRAINT `fk_levels_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.levels: ~12 rows (approximately)
INSERT INTO `levels` (`level_id`, `level`, `level_cover`, `intakes_intake_id`, `levels_order`, `date_added`, `users_user_id`) VALUES
	(1, 'Level 01', '6849c8856147a.png', 1, 0, '2025-06-11 23:48:45', 1),
	(2, 'Level 02', '6849c8a1c703b.png', 1, 0, '2025-06-11 23:49:13', 1),
	(3, 'Level 03', '6849c8ae4bd3e.png', 1, 0, '2025-06-11 23:49:26', 1),
	(4, 'Year 01', '6849c8d615f15.png', 2, 0, '2025-06-11 23:50:06', 1),
	(5, 'Year 02', '6849c8e4369e2.png', 2, 0, '2025-06-11 23:50:20', 1),
	(6, 'Year 03', '6849c8ef85ff8.png', 2, 0, '2025-06-11 23:50:31', 1),
	(7, 'Semester 01', '6849c92f3785a.png', 7, 0, '2025-06-11 23:51:35', 1),
	(8, 'Semester 02', '6849c93f156cd.png', 7, 0, '2025-06-11 23:51:51', 1),
	(9, 'Semester 03', '6849c94b52320.png', 7, 0, '2025-06-11 23:52:03', 1),
	(10, 'Level 1 ', '685c5629872ac.png', 3, 0, '2025-06-26 01:33:53', 3),
	(11, 'Level 2', '685c563fdbb90.png', 3, 0, '2025-06-26 01:34:15', 3),
	(12, 'Level 3', '685c56569feb6.png', 3, 0, '2025-06-26 01:34:38', 3);

-- Dumping structure for table lms_enviiz_latest_no_data.likes
CREATE TABLE IF NOT EXISTS `likes` (
  `likes_id` int NOT NULL AUTO_INCREMENT,
  `news_news_id` int NOT NULL,
  `students_student_id` int NOT NULL,
  PRIMARY KEY (`likes_id`),
  KEY `fk_news_has_students_students1_idx` (`students_student_id`),
  KEY `fk_news_has_students_news1_idx` (`news_news_id`),
  CONSTRAINT `fk_news_has_students_news1` FOREIGN KEY (`news_news_id`) REFERENCES `news` (`news_id`),
  CONSTRAINT `fk_news_has_students_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.likes: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.login_details
CREATE TABLE IF NOT EXISTS `login_details` (
  `login_details_id` int NOT NULL AUTO_INCREMENT,
  `first_name` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `whatsapp` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_registered` datetime NOT NULL,
  `verification` text COLLATE utf8mb4_unicode_ci,
  `reset_time` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_status_id` int NOT NULL,
  PRIMARY KEY (`login_details_id`),
  KEY `FK_login_details_status` (`status_status_id`),
  CONSTRAINT `FK_login_details_status` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table lms_enviiz_latest_no_data.login_details: ~19 rows (approximately)
INSERT INTO `login_details` (`login_details_id`, `first_name`, `last_name`, `email`, `whatsapp`, `password`, `date_registered`, `verification`, `reset_time`, `status_status_id`) VALUES
	(1, 'Sahan', 'Athuraliya', 'sahanathuraliya@enviiz.com', '0766859513', '$2y$10$b9d6FFr92QTgAV.AeYni2em1oqr.DqV4eLTcxCqieiqOs4PLMHtAG', '2025-06-12 00:27:06', '2049', NULL, 1),
	(2, 'Nimal', 'Perera', 'nimal@gmail.com', '0712345678', '$2y$10$ZbfcgPmV1d7aqBD7EydZieNUJ4vVF/pjVkdkmb6WIOzFASybO.AMy', '2025-06-16 13:49:40', '7495', NULL, 1),
	(3, 'Sanduni', 'Fernando', 'sanduni@gmail.com', '0701122334', '$2y$10$P71mnF6xaYjNTiORk8Bj/OczUX0EYfxwULwKiKtvFBfuX23jYf72O', '2025-06-16 14:08:10', '2932', NULL, 1),
	(4, 'Tharindu', 'Jayasinghe', 'tharindu@gmail.com', '0711223344', '$2y$10$Xz1oEx5Yxqzc04xi15nUAeeE93YLFSeG68XaxrKlvk6BMwq1ZnZqa', '2025-06-16 14:09:18', '4252', NULL, 1),
	(5, 'Lakshan', 'Sasanka', 'lakshansasa44@gmail.com', '0750699388', '$2y$10$U5ri48DkmCZy7LpSm.4V6.33I0i3qiE0Y3ErMph847ijbGIaflmAO', '2025-06-25 23:22:53', '7136', NULL, 1),
	(6, 'Rashmi', 'Kaveesha', 'kaveesharashmi48@gmail.com', '', '$argon2i$v=19$m=65536,t=4,p=1$a3p0ampLSGpXd290U0YwZQ$TJN3r29RiWybynFZSjH9yLl4CD9hjlo8hxnxXV2MHSk', '2025-06-26 01:22:29', 'UHl4th9pLednLFfutipC7ilQHZgpOqLTam7Be52OthTfEjm1x2jnp85j5JGyY5S8udkUVU6ogML6Nj9h6GneuFGo06b4W5h6Zw', '2025-10-28 03:38:32', 1),
	(7, 'Sadev', 'Kaveesha', 'sadev1214@gmail.com', '0775500325', '$2y$10$KCbfbxZemrbfamYrQENxwu2giI44j4QhhU.gafTf6MYGmpTgytrpC', '2025-08-14 00:11:29', '9730', NULL, 1),
	(8, 'Harinda', 'Madushan', 'harindadssc@gmail.com', '0719329935', '$2y$10$PVOrD4juD0fM416GF0OocOusuZ4QhLpOHu9PwnIZSr/pv8GtQHWBG', '2025-08-24 20:51:05', '4YSPEntuU2q0HNVCsSXoIFlbUmY0e6OWsDlv6fMPD0jAg', '2025-08-24 22:14:37', 1),
	(9, 'Sahan', 'Lakshitha', 'sahan.s.lakshitha1@gmail.com', '0767309224', '$2y$10$83BzT38/BjG8xzetUsffAOzeSbuSfbZUD71tTY4G8l98WTG/sPueq', '2025-08-24 22:55:32', 'LV8OGb54DX0jd4qGAopdRtSBSG5GocwW1nnEka9xqNn9d', NULL, 1),
	(15, 'Nevil ', 'Pathirana ', 'nevilveyangoda@gmail.com', '0718610382', '$2y$10$QDCzdOKNbEGKAeiTp7ROpOdQ1DbgX/vB2TMWZi1OhXGko/Tnvid9m', '2025-11-21 20:51:14', NULL, NULL, 1),
	(16, 'Hansani', 'Malkanthi', 'mhansi1997@gmail.com', '0760137763', '$2y$10$O9s4AhDIR0HHawYlrhOL1O6fYwWGsxVNxmpWqQBmjaUYP0/1JlCj2', '2025-11-21 20:53:37', NULL, NULL, 1),
	(17, 'Geethani', 'Samarakoon', 'geethanisamarakoon7@gmail.com', '0786300433', '$2y$10$TLzAaaSYtv1QjdLOZrDH6e82v16PzCd06qwne0pnHavnBW7xUyn8i', '2025-11-21 20:53:37', NULL, NULL, 1),
	(18, 'Chula ', 'Jayasinghe ', 'chulajayasinghe153@gmail.com', '0773850518', '$2y$10$Ma8QAX/Kc1st8/SLH.AFc.WoVl7fgfRySV.5exfbqnlZSmCwYIDUC', '2025-11-21 20:53:37', NULL, NULL, 1),
	(19, 'Erandi', 'Anuruddhika ', 'erandianuruddika029@gmail.com', '0767502881', '$2y$10$Jt7lfhb3UhUE/ZqOlz4s0uUd6p2/lrExZhuO4Eca0tszsGyepUgu6', '2025-11-21 20:53:37', NULL, NULL, 1),
	(20, 'Chamara', 'Basnayake', 'madusankabasnayake@gmail.com', '0716509053', '$2y$10$1i8dBqywaP9Dn1Tw1F669.jUNNxOHvtCbgn93qwCdN1mdr/zvjTs.', '2025-11-21 20:53:37', NULL, NULL, 1),
	(21, 'Kanapaddala Gamage ', 'Chamila Udayani ', 'rageeththasmira@gmail.com', '0778322820', '$2y$10$TokDlvdXyXz8Dp26X9TVTedi2ZyzgBGFCq0vPkoV5RM1POerRiHRW', '2025-11-21 20:53:37', NULL, NULL, 1),
	(22, 'Thisum', 'Gajamange', 'thisumi038@gmail.com', '0710193655', '$2y$10$bdpc01NGRchYuIfHBwP2mOmaNxVW7cSX8zzntphitMg4YlCtfXpra', '2025-11-21 20:53:37', NULL, NULL, 1),
	(23, 'Harsha', 'gajanayaka', '117harsha@gmail.com', '0766391459', '$2y$10$vuv03oizl5049q0CJ/pB9ugtUkoRtMSMsDl/ZucgLZ81hJj.4ymI2', '2025-11-21 20:53:37', NULL, NULL, 1),
	(24, 'Sulochana', 'Sandamali', 'sulochanasandamali400@gmail.com', '0760800314', '$2y$10$/YMYdyMFNGMlr6VYBdtfkujRbYgYIteCkTi7ObBlwpB.tk5gb3SGy', '2025-11-21 20:53:37', NULL, NULL, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.marital_status
CREATE TABLE IF NOT EXISTS `marital_status` (
  `marital_status_id` int NOT NULL AUTO_INCREMENT,
  `marital_status` varchar(45) NOT NULL,
  PRIMARY KEY (`marital_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.marital_status: ~3 rows (approximately)
INSERT INTO `marital_status` (`marital_status_id`, `marital_status`) VALUES
	(1, 'Single'),
	(2, 'Married'),
	(3, 'Divorced');

-- Dumping structure for table lms_enviiz_latest_no_data.news
CREATE TABLE IF NOT EXISTS `news` (
  `news_id` int NOT NULL AUTO_INCREMENT,
  `desc` text NOT NULL,
  `status_status_id` int NOT NULL,
  `likes` int NOT NULL,
  `added_at` datetime NOT NULL,
  `added_by` int NOT NULL,
  PRIMARY KEY (`news_id`),
  KEY `fk_news_status1_idx` (`status_status_id`),
  KEY `fk_news_users1_idx` (`added_by`),
  CONSTRAINT `fk_news_status1` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_news_users1` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.news: ~2 rows (approximately)
INSERT INTO `news` (`news_id`, `desc`, `status_status_id`, `likes`, `added_at`, `added_by`) VALUES
	(5, '<p>\r\n\r\n<div data-component="text-block" class="sc-3b6b161a-0 jdlrvG" style="margin: 0px auto 16px; font-variant-numeric: inherit; font-variant-east-asian: inherit; font-variant-alternates: inherit; font-variant-position: inherit; font-variant-emoji: inherit; font-stretch: inherit; line-height: inherit; font-family: &quot;Times New Roman&quot;; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; vertical-align: baseline; position: relative; display: grid; gap: 16px; width: 722px"><p class="sc-9a00e533-0 bJoRPJ" style=" font-style: inherit; font-variant: inherit; font-stretch: inherit; font-size: 18px; line-height: 26px; font-family: &quot;BBC Reith Serif&quot;, BBCReithSerif-fallback, serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; vertical-align: baseline; color: rgb(32, 34, 36); text-decoration: none; letter-spacing: -0.36px">Welcome to the shutdown, 2025 edition. On Tuesday evening, the US Senate was unable to pass a spending bill that would have kept the US government funded, and for the first time in nearly seven years, federal operations have been drastically curtailed.</p><p class="sc-9a00e533-0 bJoRPJ" style=" font-style: inherit; font-variant: inherit; font-stretch: inherit; font-size: 18px; line-height: 26px; font-family: &quot;BBC Reith Serif&quot;, BBCReithSerif-fallback, serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; vertical-align: baseline; color: rgb(32, 34, 36); text-decoration: none; letter-spacing: -0.36px">At some point, this shutdown – like all the ones before it - will end. It may take days; it may take weeks, but eventually, as public pressure and political pain grows, one side or the other will yield.</p><p class="sc-9a00e533-0 bJoRPJ" style=" font-style: inherit; font-variant: inherit; font-stretch: inherit; font-size: 18px; line-height: 26px; font-family: &quot;BBC Reith Serif&quot;, BBCReithSerif-fallback, serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; vertical-align: baseline; color: rgb(32, 34, 36); text-decoration: none; letter-spacing: -0.36px">Here are four scenarios for how that might play out.</p></div><div data-component="subheadline-block" class="sc-3b6b161a-0 bPbmDW" style="margin: 24px auto 16px; font-variant-numeric: inherit; font-variant-east-asian: inherit; font-variant-alternates: inherit; font-variant-position: inherit; font-variant-emoji: inherit; font-stretch: inherit; line-height: inherit; font-family: &quot;Times New Roman&quot;; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; vertical-align: baseline; position: relative; display: grid; gap: 16px; width: 722px"><h2 class="sc-f98b1ad2-0 fsBZby" style=" font-style: inherit; font-variant: inherit; font-weight: 500; font-stretch: inherit; font-size: 32px; line-height: 36px; font-family: &quot;BBC Reith Serif&quot;, BBCReithSerif-fallback, serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; vertical-align: baseline; color: rgb(32, 34, 36); text-decoration: none; letter-spacing: -0.32px">Democrats quickly break ranks</h2></div><div data-component="text-block" class="sc-3b6b161a-0 jdlrvG" style="margin: 0px auto 16px; font-variant-numeric: inherit; font-variant-east-asian: inherit; font-variant-alternates: inherit; font-variant-position: inherit; font-variant-emoji: inherit; font-stretch: inherit; line-height: inherit; font-family: &quot;Times New Roman&quot;; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; vertical-align: baseline; position: relative; display: grid; gap: 16px; width: 722px"><p class="sc-9a00e533-0 bJoRPJ" style=" font-style: inherit; font-variant: inherit; font-stretch: inherit; font-size: 18px; line-height: 26px; font-family: &quot;BBC Reith Serif&quot;, BBCReithSerif-fallback, serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; vertical-align: baseline; color: rgb(32, 34, 36); text-decoration: none; letter-spacing: -0.36px">Senate Democrats shot down a Republican spending bill that would have kept the government operating until November, but that vote may have contained the seeds of their defeat.</p><p class="sc-9a00e533-0 bJoRPJ" style=" font-style: inherit; font-variant: inherit; font-stretch: inherit; font-size: 18px; line-height: 26px; font-family: &quot;BBC Reith Serif&quot;, BBCReithSerif-fallback, serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; vertical-align: baseline; color: rgb(32, 34, 36); text-decoration: none; letter-spacing: -0.36px">While forty four Democrats (and Republican iconoclast Rand Paul) voted no, two Democrats and one Democrat-allied independent sided with the Republican majority.</p><p class="sc-9a00e533-0 bJoRPJ" style=" font-style: inherit; font-variant: inherit; font-stretch: inherit; font-size: 18px; line-height: 26px; font-family: &quot;BBC Reith Serif&quot;, BBCReithSerif-fallback, serif; font-optical-sizing: inherit; font-size-adjust: inherit; font-kerning: inherit; font-feature-settings: inherit; font-variation-settings: inherit; vertical-align: baseline; color: rgb(32, 34, 36); text-decoration: none; letter-spacing: -0.36px">Independent Angus King of Maine is always a bit of a wildcard. John Fetterman of Pennsylvania has been charting his own path for nearly a year. But Catherine Cortez Masto of Nevada, while not a liberal firebrand, is not your typical political maverick.</p></div>\r\n<br /></p>', 1, 0, '2025-10-02 01:11:22', 3);

-- Dumping structure for table lms_enviiz_latest_no_data.news_imgs
CREATE TABLE IF NOT EXISTS `news_imgs` (
  `news_imgs_id` int NOT NULL AUTO_INCREMENT,
  `news_img` varchar(45) NOT NULL,
  `news_news_id` int NOT NULL,
  PRIMARY KEY (`news_imgs_id`),
  KEY `fk_news_imgs_news1_idx` (`news_news_id`),
  CONSTRAINT `fk_news_imgs_news1` FOREIGN KEY (`news_news_id`) REFERENCES `news` (`news_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.news_imgs: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.online_exam
CREATE TABLE IF NOT EXISTS `online_exam` (
  `online_exam_id` int NOT NULL AUTO_INCREMENT,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration` varchar(5) NOT NULL,
  `exams_exam_id` int NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`online_exam_id`),
  KEY `fk_online_exam_exams1_idx` (`exams_exam_id`),
  KEY `fk_online_exam_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_online_exam_exams1` FOREIGN KEY (`exams_exam_id`) REFERENCES `exams` (`exam_id`),
  CONSTRAINT `fk_online_exam_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.online_exam: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.optional_details
CREATE TABLE IF NOT EXISTS `optional_details` (
  `optional_details_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `cv` varchar(45) DEFAULT NULL,
  `bio` varchar(120) DEFAULT NULL,
  `about` text,
  `achivements` text,
  `experience` text,
  `hourly_rate` double NOT NULL,
  PRIMARY KEY (`optional_details_id`),
  KEY `fk_advanced_details_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_advanced_details_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.optional_details: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.panelty_payments
CREATE TABLE IF NOT EXISTS `panelty_payments` (
  `panelty_payment_id` int NOT NULL AUTO_INCREMENT,
  `course_payments_course_payments_id` int NOT NULL,
  `amount` double NOT NULL,
  `date_added` datetime DEFAULT NULL,
  PRIMARY KEY (`panelty_payment_id`),
  KEY `FK_panelty_payments_course_payments` (`course_payments_course_payments_id`),
  CONSTRAINT `FK_panelty_payments_course_payments` FOREIGN KEY (`course_payments_course_payments_id`) REFERENCES `course_payments` (`course_payments_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table lms_enviiz_latest_no_data.panelty_payments: ~10 rows (approximately)
INSERT INTO `panelty_payments` (`panelty_payment_id`, `course_payments_course_payments_id`, `amount`, `date_added`) VALUES
	(2, 1, 5000, '2025-11-20 21:03:51'),
	(3, 14, 1000, '2025-11-20 21:03:52'),
	(4, 13, 1000, '2025-11-20 17:05:07'),
	(5, 12, 1000, '2025-11-20 21:03:53'),
	(6, 11, 1000, '2025-11-20 21:03:53'),
	(7, 9, 1000, '2025-11-20 21:03:54'),
	(8, 6, 1000, '2025-11-20 21:03:55'),
	(9, 5, 1000, '2025-11-20 21:03:55'),
	(10, 3, 1000, '2025-11-20 21:03:56'),
	(11, 2, 1000, '2025-11-20 21:03:57');

-- Dumping structure for table lms_enviiz_latest_no_data.panelty_payments_type
CREATE TABLE IF NOT EXISTS `panelty_payments_type` (
  `panelty_payments_type_id` int NOT NULL AUTO_INCREMENT,
  `panelty_payments_type` varchar(50) NOT NULL,
  PRIMARY KEY (`panelty_payments_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table lms_enviiz_latest_no_data.panelty_payments_type: ~2 rows (approximately)
INSERT INTO `panelty_payments_type` (`panelty_payments_type_id`, `panelty_payments_type`) VALUES
	(1, 'Fixeced Amount Discount'),
	(2, 'Prcentage Based Discount');

-- Dumping structure for table lms_enviiz_latest_no_data.panelty_payment_category
CREATE TABLE IF NOT EXISTS `panelty_payment_category` (
  `panelty_payment_category_id` int NOT NULL AUTO_INCREMENT,
  `panelty_payments_type_panelty_payments_type_id` int NOT NULL,
  `panelty_amount` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`panelty_payment_category_id`),
  KEY `FK_panelty_payment_category_panelty_payments_type` (`panelty_payments_type_panelty_payments_type_id`),
  CONSTRAINT `FK_panelty_payment_category_panelty_payments_type` FOREIGN KEY (`panelty_payments_type_panelty_payments_type_id`) REFERENCES `panelty_payments_type` (`panelty_payments_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table lms_enviiz_latest_no_data.panelty_payment_category: ~1 rows (approximately)
INSERT INTO `panelty_payment_category` (`panelty_payment_category_id`, `panelty_payments_type_panelty_payments_type_id`, `panelty_amount`) VALUES
	(1, 1, 1000);

-- Dumping structure for table lms_enviiz_latest_no_data.payment_category
CREATE TABLE IF NOT EXISTS `payment_category` (
  `payment_category_id` int NOT NULL AUTO_INCREMENT,
  `payment_category` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.payment_category: ~2 rows (approximately)
INSERT INTO `payment_category` (`payment_category_id`, `payment_category`) VALUES
	(1, 'Registration Fee'),
	(2, 'University Payment');

-- Dumping structure for table lms_enviiz_latest_no_data.payment_logs
CREATE TABLE IF NOT EXISTS `payment_logs` (
  `payment_logs_id` int NOT NULL AUTO_INCREMENT,
  `course_payments_course_payments_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  `date_updated` datetime NOT NULL,
  `note` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_logs_id`),
  KEY `fk_course_payments_has_users_users1_idx` (`users_user_id`),
  KEY `fk_course_payments_has_users_course_payments1_idx` (`course_payments_course_payments_id`),
  CONSTRAINT `fk_course_payments_has_users_course_payments1` FOREIGN KEY (`course_payments_course_payments_id`) REFERENCES `course_payments` (`course_payments_id`),
  CONSTRAINT `fk_course_payments_has_users_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.payment_logs: ~17 rows (approximately)
INSERT INTO `payment_logs` (`payment_logs_id`, `course_payments_course_payments_id`, `users_user_id`, `date_updated`, `note`) VALUES
	(1, 1, 3, '2025-06-26 03:56:51', 'Registration Fee'),
	(2, 2, 3, '2025-06-26 03:58:15', 'Univercity Registration Paymnet'),
	(3, 3, 3, '2025-06-26 04:17:32', 'Installment Payment'),
	(4, 4, 1, '2025-07-28 11:13:41', '1000 OFF'),
	(5, 5, 1, '2025-07-30 20:34:34', 'No Discounts'),
	(6, 6, 3, '2025-08-14 21:01:03', 'Installment Fee'),
	(7, 7, 2, '2025-08-14 21:01:58', 'Installment 1'),
	(8, 8, 2, '2025-08-14 21:02:05', 'Installment 1'),
	(9, 9, 1, '2025-08-21 22:32:28', '10% OFF'),
	(10, 10, 1, '2025-09-19 10:06:02', 'No'),
	(11, 4, 3, '2025-09-26 13:12:12', 'Reject Payment'),
	(12, 10, 3, '2025-09-26 13:13:40', 'Approved Payment'),
	(13, 10, 3, '2025-09-26 13:14:56', 'Approved by Accountant'),
	(36, 11, 3, '2025-10-20 11:27:37', 'Add Payment'),
	(37, 12, 3, '2025-10-20 11:27:37', 'Add Payment'),
	(38, 13, 3, '2025-10-20 11:30:49', 'Add Payment'),
	(39, 14, 3, '2025-10-20 11:30:49', 'Add Payment'),
	(40, 15, 3, '2025-11-15 13:18:52', 'Installment'),
	(41, 16, 3, '2025-11-15 13:25:19', 'test'),
	(42, 17, 3, '2025-11-20 21:25:28', 'test');

-- Dumping structure for table lms_enviiz_latest_no_data.payment_method
CREATE TABLE IF NOT EXISTS `payment_method` (
  `payment_method_id` int NOT NULL AUTO_INCREMENT,
  `payment_method` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_method_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.payment_method: ~2 rows (approximately)
INSERT INTO `payment_method` (`payment_method_id`, `payment_method`) VALUES
	(1, 'Bank Transfer'),
	(2, 'Card Payment'),
	(3, 'Direct Payment');

-- Dumping structure for table lms_enviiz_latest_no_data.payment_plan
CREATE TABLE IF NOT EXISTS `payment_plan` (
  `payment_plan_id` int NOT NULL AUTO_INCREMENT,
  `payment_plan` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.payment_plan: ~5 rows (approximately)
INSERT INTO `payment_plan` (`payment_plan_id`, `payment_plan`) VALUES
	(1, 'Full Payment'),
	(2, 'Monthly Installments'),
	(3, 'Sem Installments'),
	(4, 'Yearly Installments'),
	(5, 'Installments');

-- Dumping structure for table lms_enviiz_latest_no_data.payment_plan_has_students_has_intakes
CREATE TABLE IF NOT EXISTS `payment_plan_has_students_has_intakes` (
  `payment_plan_has_students_has_intakes_id` int NOT NULL AUTO_INCREMENT,
  `payment_plan_payment_plan_id` int NOT NULL,
  `students_has_intakes_students_has_intakes_id` int NOT NULL,
  `payment_category_payment_category_id` int NOT NULL,
  `discount_type_discount_type_id` int NOT NULL,
  `amount` double NOT NULL,
  `discount` double NOT NULL,
  `discount_note` varchar(45) DEFAULT NULL,
  `payment_period` int DEFAULT NULL,
  PRIMARY KEY (`payment_plan_has_students_has_intakes_id`),
  KEY `fk_payment_plan_has_students_has_intakes_students_has_intak_idx` (`students_has_intakes_students_has_intakes_id`),
  KEY `fk_payment_plan_has_students_has_intakes_payment_plan1_idx` (`payment_plan_payment_plan_id`),
  KEY `fk_payment_plan_has_students_has_intakes_payment_category1_idx` (`payment_category_payment_category_id`),
  KEY `fk_payment_plan_has_students_has_intakes_discount_type1_idx` (`discount_type_discount_type_id`),
  CONSTRAINT `fk_payment_plan_has_students_has_intakes_discount_type1` FOREIGN KEY (`discount_type_discount_type_id`) REFERENCES `discount_type` (`discount_type_id`),
  CONSTRAINT `fk_payment_plan_has_students_has_intakes_payment_category1` FOREIGN KEY (`payment_category_payment_category_id`) REFERENCES `payment_category` (`payment_category_id`),
  CONSTRAINT `fk_payment_plan_has_students_has_intakes_payment_plan1` FOREIGN KEY (`payment_plan_payment_plan_id`) REFERENCES `payment_plan` (`payment_plan_id`),
  CONSTRAINT `fk_payment_plan_has_students_has_intakes_students_has_intakes1` FOREIGN KEY (`students_has_intakes_students_has_intakes_id`) REFERENCES `students_has_intakes` (`students_has_intakes_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.payment_plan_has_students_has_intakes: ~9 rows (approximately)
INSERT INTO `payment_plan_has_students_has_intakes` (`payment_plan_has_students_has_intakes_id`, `payment_plan_payment_plan_id`, `students_has_intakes_students_has_intakes_id`, `payment_category_payment_category_id`, `discount_type_discount_type_id`, `amount`, `discount`, `discount_note`, `payment_period`) VALUES
	(1, 1, 1, 1, 1, 25000, 0, '', NULL),
	(2, 1, 7, 2, 2, 500000, 50000, '', NULL),
	(3, 2, 2, 2, 3, 500000, 10, '', NULL),
	(4, 2, 8, 2, 2, 25000, 5000, '5000 OFF', NULL),
	(5, 5, 9, 2, 2, 25000, 3000, '3000 OFF', NULL),
	(6, 1, 10, 1, 1, 10000, 0, '', NULL),
	(7, 5, 11, 2, 2, 150000, 20000, 'Early Registration', NULL),
	(8, 1, 10, 2, 3, 450000, 20, 'Pay at once discount', NULL),
	(9, 2, 1, 2, 1, 500000, 0, '', 24);

-- Dumping structure for table lms_enviiz_latest_no_data.payment_status
CREATE TABLE IF NOT EXISTS `payment_status` (
  `payment_status_id` int NOT NULL AUTO_INCREMENT,
  `payment_status` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.payment_status: ~8 rows (approximately)
INSERT INTO `payment_status` (`payment_status_id`, `payment_status`) VALUES
	(1, 'Awaiting Payment'),
	(2, 'Awaiting Approval(Aca Officer)'),
	(3, 'Approved(Ac Officer)'),
	(4, 'Rejected(Ac Officer)'),
	(5, 'Awaiting Approval(Acc)'),
	(6, 'Approval(Accountant)'),
	(7, 'Rejected(Accountant)'),
	(8, 'Payment Accepted');

-- Dumping structure for table lms_enviiz_latest_no_data.payment_type
CREATE TABLE IF NOT EXISTS `payment_type` (
  `payment_type_id` int NOT NULL AUTO_INCREMENT,
  `payment_type` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.payment_type: ~2 rows (approximately)
INSERT INTO `payment_type` (`payment_type_id`, `payment_type`) VALUES
	(1, 'Registration Fee'),
	(3, 'Installment Fee');

-- Dumping structure for table lms_enviiz_latest_no_data.payout_data
CREATE TABLE IF NOT EXISTS `payout_data` (
  `payout_data_id` int NOT NULL AUTO_INCREMENT,
  `payout_data` varchar(45) NOT NULL,
  `payout_type_payout_type_id` int NOT NULL,
  `lecturer_payouts_lecturer_payouts_id` int NOT NULL,
  `amount` double NOT NULL,
  `lecturers_lecturers_id` int NOT NULL,
  PRIMARY KEY (`payout_data_id`),
  KEY `fk_payout_data_payout_type1_idx` (`payout_type_payout_type_id`),
  KEY `fk_payout_data_lecturer_payouts1_idx` (`lecturer_payouts_lecturer_payouts_id`),
  KEY `FK_payout_data_lecturers` (`lecturers_lecturers_id`),
  CONSTRAINT `fk_payout_data_lecturer_payouts1` FOREIGN KEY (`lecturer_payouts_lecturer_payouts_id`) REFERENCES `lecturer_payouts` (`lecturer_payouts_id`),
  CONSTRAINT `FK_payout_data_lecturers` FOREIGN KEY (`lecturers_lecturers_id`) REFERENCES `lecturers` (`lecturers_id`),
  CONSTRAINT `fk_payout_data_payout_type1` FOREIGN KEY (`payout_type_payout_type_id`) REFERENCES `payout_type` (`payout_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.payout_data: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.payout_logs
CREATE TABLE IF NOT EXISTS `payout_logs` (
  `payout_logs_id` int NOT NULL AUTO_INCREMENT,
  `note` varchar(45) NOT NULL,
  `date_updated` datetime NOT NULL,
  `lecturer_payouts_lecturer_payouts_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`payout_logs_id`),
  KEY `fk_payout_logs_lecturer_payouts1_idx` (`lecturer_payouts_lecturer_payouts_id`),
  KEY `fk_payout_logs_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_payout_logs_lecturer_payouts1` FOREIGN KEY (`lecturer_payouts_lecturer_payouts_id`) REFERENCES `lecturer_payouts` (`lecturer_payouts_id`),
  CONSTRAINT `fk_payout_logs_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.payout_logs: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.payout_status
CREATE TABLE IF NOT EXISTS `payout_status` (
  `payout_status_id` int NOT NULL AUTO_INCREMENT,
  `payout_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`payout_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table lms_enviiz_latest_no_data.payout_status: ~7 rows (approximately)
INSERT INTO `payout_status` (`payout_status_id`, `payout_status`) VALUES
	(1, 'Awaiting Approval Manager'),
	(2, 'Approved Manager'),
	(3, 'Rejected Manager'),
	(4, 'Awaiting Approval Accountant'),
	(5, 'Approved Accountant'),
	(6, 'Rejected Accountant'),
	(7, 'Paid');

-- Dumping structure for table lms_enviiz_latest_no_data.payout_type
CREATE TABLE IF NOT EXISTS `payout_type` (
  `payout_type_id` int NOT NULL AUTO_INCREMENT,
  `payout_type` varchar(45) NOT NULL,
  PRIMARY KEY (`payout_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.payout_type: ~3 rows (approximately)
INSERT INTO `payout_type` (`payout_type_id`, `payout_type`) VALUES
	(1, 'Lecture Payments'),
	(2, 'Assignment Payments'),
	(3, 'Exam Payments');

-- Dumping structure for table lms_enviiz_latest_no_data.permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `permission` varchar(45) NOT NULL,
  PRIMARY KEY (`permission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.permissions: ~28 rows (approximately)
INSERT INTO `permissions` (`permission_id`, `permission`) VALUES
	(1, 'View Only'),
	(2, 'Add New'),
	(3, 'Reset Password'),
	(4, 'Active/Inactive'),
	(5, 'Give Permission'),
	(6, 'Edit Permission'),
	(7, 'Assign Intake'),
	(8, 'Edit Intake'),
	(9, 'Remove Intake'),
	(10, 'Update'),
	(11, 'Delete'),
	(12, 'Add Payment Plan'),
	(13, 'Request Payment'),
	(14, 'Enable Card Payment'),
	(15, 'Approve / Reject Payment'),
	(16, 'Assign Lecturer'),
	(17, 'Release Mark'),
	(18, 'Enable Late Submission'),
	(19, 'Enable Re-Correction'),
	(20, 'Upload Marks (CSV)'),
	(21, 'Add Bank'),
	(22, 'Edit Bank'),
	(23, 'Approve Quiz Reset Requests'),
	(24, 'Confirm Lecturer Payments'),
	(25, 'Help Desk'),
	(26, 'Change Student Intake'),
	(27, 'Mark Attendance'),
	(28, 'Upload Lecture Recording');

-- Dumping structure for table lms_enviiz_latest_no_data.permission_to
CREATE TABLE IF NOT EXISTS `permission_to` (
  `permission_to_id` int NOT NULL AUTO_INCREMENT,
  `permissions_permission_id` int NOT NULL,
  `permission_type_permission_type_id` int NOT NULL,
  PRIMARY KEY (`permission_to_id`),
  KEY `fk_permission_to_permissions1_idx` (`permissions_permission_id`),
  KEY `fk_permission_to_permission_type1_idx` (`permission_type_permission_type_id`),
  CONSTRAINT `fk_permission_to_permission_type1` FOREIGN KEY (`permission_type_permission_type_id`) REFERENCES `permission_type` (`permission_type_id`),
  CONSTRAINT `fk_permission_to_permissions1` FOREIGN KEY (`permissions_permission_id`) REFERENCES `permissions` (`permission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.permission_to: ~59 rows (approximately)
INSERT INTO `permission_to` (`permission_to_id`, `permissions_permission_id`, `permission_type_permission_type_id`) VALUES
	(43, 1, 1),
	(44, 2, 1),
	(45, 3, 1),
	(46, 4, 1),
	(47, 5, 1),
	(48, 6, 1),
	(49, 10, 1),
	(50, 11, 1),
	(51, 1, 2),
	(52, 2, 2),
	(53, 3, 2),
	(54, 4, 2),
	(55, 7, 2),
	(56, 8, 2),
	(57, 10, 2),
	(58, 11, 2),
	(59, 1, 3),
	(60, 2, 3),
	(61, 4, 3),
	(62, 10, 3),
	(63, 11, 3),
	(64, 1, 4),
	(65, 2, 4),
	(66, 4, 4),
	(67, 10, 4),
	(68, 11, 4),
	(69, 1, 5),
	(70, 2, 5),
	(71, 4, 5),
	(72, 1, 8),
	(73, 2, 8),
	(74, 3, 8),
	(75, 4, 8),
	(76, 10, 8),
	(77, 9, 8),
	(78, 1, 9),
	(79, 2, 9),
	(80, 4, 9),
	(81, 1, 10),
	(82, 12, 10),
	(83, 13, 10),
	(84, 14, 10),
	(85, 15, 10),
	(86, 11, 10),
	(87, 1, 6),
	(88, 2, 6),
	(89, 10, 6),
	(91, 1, 7),
	(92, 2, 11),
	(93, 10, 14),
	(94, 2, 19),
	(95, 2, 13),
	(96, 2, 15),
	(97, 10, 15),
	(98, 2, 16),
	(99, 10, 16),
	(100, 2, 17),
	(101, 10, 17),
	(102, 11, 17);

-- Dumping structure for table lms_enviiz_latest_no_data.permission_type
CREATE TABLE IF NOT EXISTS `permission_type` (
  `permission_type_id` int NOT NULL AUTO_INCREMENT,
  `permission_type` varchar(45) NOT NULL,
  PRIMARY KEY (`permission_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.permission_type: ~19 rows (approximately)
INSERT INTO `permission_type` (`permission_type_id`, `permission_type`) VALUES
	(1, 'User'),
	(2, 'Student'),
	(3, 'Faculty'),
	(4, 'Programme'),
	(5, 'Intake'),
	(6, 'Level'),
	(7, 'Subject'),
	(8, 'Intake Students'),
	(9, 'Lecturers'),
	(10, 'Intake Payments'),
	(11, 'Lecture Schedule'),
	(12, 'Pre-Recorded Lectures'),
	(13, 'Lecture Recordings'),
	(14, 'Subject Students'),
	(15, 'Exam'),
	(16, 'Assignments'),
	(17, 'Quiz'),
	(18, 'Subject Overview'),
	(19, 'Payments');

-- Dumping structure for table lms_enviiz_latest_no_data.physical_exam
CREATE TABLE IF NOT EXISTS `physical_exam` (
  `physical_exam_id` int NOT NULL AUTO_INCREMENT,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration` varchar(5) NOT NULL,
  `location` varchar(45) NOT NULL,
  `exams_exam_id` int NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`physical_exam_id`),
  KEY `fk_physical_exam_exams1_idx` (`exams_exam_id`),
  KEY `fk_physical_exam_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_physical_exam_exams1` FOREIGN KEY (`exams_exam_id`) REFERENCES `exams` (`exam_id`),
  CONSTRAINT `fk_physical_exam_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.physical_exam: ~0 rows (approximately)
INSERT INTO `physical_exam` (`physical_exam_id`, `start_time`, `end_time`, `duration`, `location`, `exams_exam_id`, `date_added`, `users_user_id`) VALUES
	(3, '07:00:00', '10:00:00', '60', 'Colombo', 9, '2025-10-31 01:58:31', 3);

-- Dumping structure for table lms_enviiz_latest_no_data.plagiarism_status
CREATE TABLE IF NOT EXISTS `plagiarism_status` (
  `plagiarism_status_id` int NOT NULL AUTO_INCREMENT,
  `plagiarism_status` varchar(45) NOT NULL,
  PRIMARY KEY (`plagiarism_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.plagiarism_status: ~2 rows (approximately)
INSERT INTO `plagiarism_status` (`plagiarism_status_id`, `plagiarism_status`) VALUES
	(1, 'Required'),
	(2, 'Not Required');

-- Dumping structure for table lms_enviiz_latest_no_data.portal_payment
CREATE TABLE IF NOT EXISTS `portal_payment` (
  `portal_payment_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `amount` double NOT NULL,
  `discount` double NOT NULL,
  `discount_note` varchar(45) DEFAULT NULL,
  `from` datetime NOT NULL,
  `to` datetime NOT NULL,
  `date_paid` datetime DEFAULT NULL,
  `discount_type_discount_type_id` int NOT NULL,
  `refference` varchar(45) DEFAULT NULL,
  `receipt` varchar(45) DEFAULT NULL,
  `portal_payment_status_portal_payment_status_id` int NOT NULL,
  `portal_payment_plan_portal_payment_plan_id` int NOT NULL,
  `payment_method_payment_method_id` int NOT NULL,
  PRIMARY KEY (`portal_payment_id`),
  KEY `fk_students_has_payment_plan_students1_idx` (`students_student_id`),
  KEY `fk_portal_payment_discount_type1_idx` (`discount_type_discount_type_id`),
  KEY `fk_portal_payment_portal_payment_status1_idx` (`portal_payment_status_portal_payment_status_id`),
  KEY `fk_portal_payment_portal_payment_plan1_idx` (`portal_payment_plan_portal_payment_plan_id`),
  KEY `fk_portal_payment_payment_method1_idx` (`payment_method_payment_method_id`),
  CONSTRAINT `fk_portal_payment_discount_type1` FOREIGN KEY (`discount_type_discount_type_id`) REFERENCES `discount_type` (`discount_type_id`),
  CONSTRAINT `fk_portal_payment_payment_method1` FOREIGN KEY (`payment_method_payment_method_id`) REFERENCES `payment_method` (`payment_method_id`),
  CONSTRAINT `fk_portal_payment_portal_payment_plan1` FOREIGN KEY (`portal_payment_plan_portal_payment_plan_id`) REFERENCES `portal_payment_plan` (`portal_payment_plan_id`),
  CONSTRAINT `fk_portal_payment_portal_payment_status1` FOREIGN KEY (`portal_payment_status_portal_payment_status_id`) REFERENCES `portal_payment_status` (`portal_payment_status_id`),
  CONSTRAINT `fk_students_has_payment_plan_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.portal_payment: ~24 rows (approximately)
INSERT INTO `portal_payment` (`portal_payment_id`, `students_student_id`, `amount`, `discount`, `discount_note`, `from`, `to`, `date_paid`, `discount_type_discount_type_id`, `refference`, `receipt`, `portal_payment_status_portal_payment_status_id`, `portal_payment_plan_portal_payment_plan_id`, `payment_method_payment_method_id`) VALUES
	(1, 1, 200, 0, NULL, '2025-06-12 00:27:06', '2025-06-17 00:27:06', '2025-06-12 00:27:06', 1, '', '', 3, 1, 1),
	(2, 2, 200, 0, NULL, '2025-06-16 13:49:40', '2025-06-21 13:49:40', '2025-06-16 13:49:40', 1, '', '', 3, 1, 1),
	(3, 3, 200, 0, NULL, '2025-06-16 14:08:10', '2025-06-21 14:08:10', '2025-06-16 14:08:10', 1, '', '', 3, 1, 1),
	(4, 4, 200, 0, NULL, '2025-06-16 14:09:18', '2025-06-21 14:09:18', '2025-06-16 14:09:18', 1, '', '', 3, 1, 1),
	(5, 5, 200, 0, NULL, '2025-06-25 23:22:53', '2025-06-30 23:22:53', '2025-06-25 23:22:53', 1, '', '', 3, 2, 1),
	(6, 5, 200, 0, NULL, '2025-06-30 23:22:54', '2025-07-30 23:22:54', '2025-06-25 23:29:51', 1, '', '', 3, 2, 1),
	(7, 1, 200, 0, NULL, '2025-06-17 00:27:07', '2025-07-17 00:27:07', '2025-06-25 23:35:17', 1, '', '', 3, 2, 1),
	(8, 6, 200, 0, NULL, '2025-06-26 01:22:29', '2025-07-01 01:22:29', '2025-06-26 01:22:29', 1, '', '', 3, 2, 1),
	(9, 6, 200, 0, NULL, '2025-07-01 01:22:30', '2025-07-31 01:22:30', '2025-07-08 09:17:01', 1, '', '', 3, 2, 1),
	(10, 1, 200, 0, NULL, '2025-07-17 00:27:08', '2025-08-16 00:27:08', '2025-07-28 09:54:05', 1, '', '', 3, 2, 1),
	(11, 5, 200, 0, NULL, '2025-07-30 23:22:55', '2025-08-29 23:22:55', '2025-08-13 19:55:40', 1, '', '', 3, 2, 1),
	(12, 5, 200, 0, NULL, '2025-08-29 23:22:56', '2025-09-28 23:22:56', '2025-08-13 19:56:04', 1, '', '', 3, 2, 1),
	(13, 6, 200, 0, NULL, '2025-07-31 01:22:31', '2025-08-30 01:22:31', '2025-08-14 00:08:58', 1, '', '', 3, 2, 1),
	(14, 7, 200, 0, NULL, '2025-08-14 00:11:29', '2025-08-19 00:11:29', '2025-08-14 00:11:29', 1, '', '', 3, 2, 1),
	(15, 1, 200, 0, NULL, '2025-08-16 00:27:09', '2025-09-15 00:27:09', '2025-08-21 18:44:33', 1, '', '', 3, 2, 1),
	(16, 7, 200, 0, NULL, '2025-08-19 00:11:30', '2025-09-18 00:11:30', '2025-08-23 00:41:12', 1, '', '', 3, 2, 1),
	(17, 8, 200, 0, NULL, '2025-08-25 19:24:10', '2025-09-24 19:24:10', '2025-08-25 19:24:10', 1, '', '', 3, 2, 1),
	(18, 9, 200, 0, NULL, '2025-09-18 15:45:49', '2025-10-18 15:45:49', '2025-09-18 15:45:49', 1, '', '', 3, 2, 1),
	(19, 9, 200, 0, NULL, '2025-10-18 15:45:50', '2025-11-17 15:45:50', '2025-09-18 15:45:52', 1, '', '', 3, 2, 1),
	(20, 1, 200, 0, NULL, '2025-09-15 00:27:10', '2025-10-15 00:27:10', '2025-09-18 15:46:04', 1, '', '', 3, 2, 1),
	(21, 1, 200, 0, NULL, '2025-10-15 00:27:11', '2025-11-14 00:27:11', '2025-09-18 15:46:05', 1, '', '', 3, 2, 1),
	(22, 7, 200, 0, NULL, '2025-09-18 00:11:31', '2025-10-18 00:11:31', '2025-09-26 12:51:03', 1, '', '', 3, 2, 1),
	(23, 6, 200, 0, NULL, '2025-08-30 01:22:32', '2025-10-11 01:22:32', '2025-09-26 13:05:26', 1, '', '1234.png', 3, 2, 1),
	(36, 6, 400, 0, NULL, '2025-11-11 01:22:32', '2025-12-11 01:22:32', '2025-11-21 10:59:43', 1, 'PJ1WPTEU', '691ff8c71b49e.jpg', 3, 2, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.portal_payment_logs
CREATE TABLE IF NOT EXISTS `portal_payment_logs` (
  `portal_payment_logs_id` int NOT NULL AUTO_INCREMENT,
  `portal_payment_portal_payment_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  `date_updated` datetime NOT NULL,
  `note` varchar(45) NOT NULL,
  PRIMARY KEY (`portal_payment_logs_id`),
  KEY `fk_portal_payment_has_users_users1_idx` (`users_user_id`),
  KEY `fk_portal_payment_has_users_portal_payment1_idx` (`portal_payment_portal_payment_id`),
  CONSTRAINT `fk_portal_payment_has_users_portal_payment1` FOREIGN KEY (`portal_payment_portal_payment_id`) REFERENCES `portal_payment` (`portal_payment_id`),
  CONSTRAINT `fk_portal_payment_has_users_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.portal_payment_logs: ~21 rows (approximately)
INSERT INTO `portal_payment_logs` (`portal_payment_logs_id`, `portal_payment_portal_payment_id`, `users_user_id`, `date_updated`, `note`) VALUES
	(1, 1, 1, '2025-06-12 00:27:06', 'Add Trial Portal Payment'),
	(2, 2, 1, '2025-06-16 13:49:40', 'Add Trial Portal Payment'),
	(3, 3, 1, '2025-06-16 14:08:10', 'Add Trial Portal Payment'),
	(4, 4, 1, '2025-06-16 14:09:18', 'Add Trial Portal Payment'),
	(5, 5, 1, '2025-06-25 23:22:53', 'Add Trial Portal Payment'),
	(6, 6, 1, '2025-06-25 23:29:51', 'Add  Portal Payment'),
	(7, 7, 1, '2025-06-25 23:35:17', 'Add  Portal Payment'),
	(8, 8, 3, '2025-06-26 01:22:29', 'Add Trial Portal Payment'),
	(9, 9, 3, '2025-07-08 09:17:01', 'Add  Portal Payment'),
	(10, 10, 1, '2025-07-28 09:54:05', 'Add  Portal Payment'),
	(11, 11, 1, '2025-08-13 19:55:40', 'Add  Portal Payment'),
	(12, 12, 1, '2025-08-13 19:56:04', 'Add  Portal Payment'),
	(13, 13, 3, '2025-08-14 00:08:58', 'Add  Portal Payment'),
	(14, 14, 2, '2025-08-14 00:11:29', 'Add Trial Portal Payment'),
	(15, 15, 1, '2025-08-21 18:44:33', 'Add  Portal Payment'),
	(16, 16, 2, '2025-08-23 00:41:12', 'Add  Portal Payment'),
	(17, 19, 1, '2025-09-18 15:45:52', 'Add  Portal Payment'),
	(18, 20, 1, '2025-09-18 15:46:04', 'Add  Portal Payment'),
	(19, 21, 1, '2025-09-18 15:46:05', 'Add  Portal Payment'),
	(20, 22, 1, '2025-09-26 12:51:03', 'Add  Portal Payment'),
	(21, 23, 3, '2025-09-26 13:05:26', 'Add  Portal Payment'),
	(29, 36, 3, '2025-11-21 11:00:03', 'acc');

-- Dumping structure for table lms_enviiz_latest_no_data.portal_payment_plan
CREATE TABLE IF NOT EXISTS `portal_payment_plan` (
  `portal_payment_plan_id` int NOT NULL AUTO_INCREMENT,
  `portal_payment_plan` varchar(45) NOT NULL,
  `portal_pyament_monthly_amount` double NOT NULL DEFAULT '0',
  PRIMARY KEY (`portal_payment_plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.portal_payment_plan: ~5 rows (approximately)
INSERT INTO `portal_payment_plan` (`portal_payment_plan_id`, `portal_payment_plan`, `portal_pyament_monthly_amount`) VALUES
	(1, 'Trial', 0),
	(2, 'Monthly', 200),
	(3, 'Quarterly', 189),
	(4, 'Semi-Annual', 179),
	(5, 'Annual', 169);

-- Dumping structure for table lms_enviiz_latest_no_data.portal_payment_status
CREATE TABLE IF NOT EXISTS `portal_payment_status` (
  `portal_payment_status_id` int NOT NULL AUTO_INCREMENT,
  `portal_payment_status` varchar(45) NOT NULL,
  PRIMARY KEY (`portal_payment_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.portal_payment_status: ~4 rows (approximately)
INSERT INTO `portal_payment_status` (`portal_payment_status_id`, `portal_payment_status`) VALUES
	(1, 'Pending Payment'),
	(2, 'Awaiting Approval'),
	(3, 'Accepted'),
	(4, 'Rejected');

-- Dumping structure for table lms_enviiz_latest_no_data.portal_subscription_payments
CREATE TABLE IF NOT EXISTS `portal_subscription_payments` (
  `portal_subscription_payment_id` int NOT NULL AUTO_INCREMENT,
  `total_amount` double NOT NULL,
  `students_count` int NOT NULL,
  `date_paid` datetime NOT NULL,
  `approved_user` int NOT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`portal_subscription_payment_id`) USING BTREE,
  KEY `FK_portal_subscription_payments_users` (`approved_user`) USING BTREE,
  CONSTRAINT `FK_portal_subscription_payments_users` FOREIGN KEY (`approved_user`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table lms_enviiz_latest_no_data.portal_subscription_payments: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.programmes
CREATE TABLE IF NOT EXISTS `programmes` (
  `programme_id` int NOT NULL AUTO_INCREMENT,
  `programme` varchar(100) NOT NULL,
  `programme_cover` varchar(45) NOT NULL,
  `faculties_faculty_id` int NOT NULL,
  `date_added` datetime NOT NULL,
  `programmes_order` int NOT NULL,
  `users_user_id` int NOT NULL,
  `programme_duration_programme_duration_id` int NOT NULL,
  PRIMARY KEY (`programme_id`),
  KEY `fk_programmes_faculties1_idx` (`faculties_faculty_id`),
  KEY `fk_programmes_users1_idx` (`users_user_id`),
  KEY `fk_programmes_programme_duration1_idx` (`programme_duration_programme_duration_id`),
  CONSTRAINT `fk_programmes_faculties1` FOREIGN KEY (`faculties_faculty_id`) REFERENCES `faculties` (`faculty_id`),
  CONSTRAINT `fk_programmes_programme_duration1` FOREIGN KEY (`programme_duration_programme_duration_id`) REFERENCES `programme_duration` (`programme_duration_id`),
  CONSTRAINT `fk_programmes_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.programmes: ~3 rows (approximately)
INSERT INTO `programmes` (`programme_id`, `programme`, `programme_cover`, `faculties_faculty_id`, `date_added`, `programmes_order`, `users_user_id`, `programme_duration_programme_duration_id`) VALUES
	(1, 'Bachelor Of Biosystems Technology Honours', '68499f89250d9.png', 1, '2025-06-11 20:53:53', 0, 1, 1),
	(2, 'Bachelor of IT', '6849a06361ebb.png', 1, '2025-06-11 20:57:31', 0, 1, 1),
	(3, 'Bachelor of Engineering Technology Honours', '6849a0f23a7e4.png', 1, '2025-06-11 20:59:54', 0, 1, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.programme_duration
CREATE TABLE IF NOT EXISTS `programme_duration` (
  `programme_duration_id` int NOT NULL AUTO_INCREMENT,
  `programme_duration` varchar(45) NOT NULL,
  `programme_duration_hours` double NOT NULL,
  `order_by` int NOT NULL,
  PRIMARY KEY (`programme_duration_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.programme_duration: ~0 rows (approximately)
INSERT INTO `programme_duration` (`programme_duration_id`, `programme_duration`, `programme_duration_hours`, `order_by`) VALUES
	(1, '1 Year', 3200, 0);

-- Dumping structure for table lms_enviiz_latest_no_data.province
CREATE TABLE IF NOT EXISTS `province` (
  `province_id` int NOT NULL AUTO_INCREMENT,
  `province` varchar(45) NOT NULL,
  PRIMARY KEY (`province_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.province: ~9 rows (approximately)
INSERT INTO `province` (`province_id`, `province`) VALUES
	(1, 'Central'),
	(2, 'Eastern'),
	(3, 'North Central'),
	(4, 'Northern'),
	(5, 'North Western'),
	(6, 'Sabaragamuwa'),
	(7, 'Southern'),
	(8, 'Uva'),
	(9, 'Western');

-- Dumping structure for table lms_enviiz_latest_no_data.questions
CREATE TABLE IF NOT EXISTS `questions` (
  `question_id` int NOT NULL AUTO_INCREMENT,
  `question` text NOT NULL,
  `correct_answer` int NOT NULL,
  `quiz_quiz_id` int NOT NULL,
  `questions_order` int NOT NULL,
  PRIMARY KEY (`question_id`),
  KEY `fk_questions_quiz1_idx` (`quiz_quiz_id`),
  CONSTRAINT `fk_questions_quiz1` FOREIGN KEY (`quiz_quiz_id`) REFERENCES `quiz` (`quiz_id`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.questions: ~2 rows (approximately)
INSERT INTO `questions` (`question_id`, `question`, `correct_answer`, `quiz_quiz_id`, `questions_order`) VALUES
	(1, 'Sample Quiz 1', 2, 4, 0),
	(2, 'Test Question 1', 2, 5, 0);

-- Dumping structure for table lms_enviiz_latest_no_data.quiz
CREATE TABLE IF NOT EXISTS `quiz` (
  `quiz_id` int NOT NULL AUTO_INCREMENT,
  `start_time` time NOT NULL,
  `end_date` date NOT NULL,
  `end_time` time NOT NULL,
  `duration` varchar(5) NOT NULL,
  `exams_exam_id` int NOT NULL,
  `quiz_type_quiz_type_id` int NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  `quiz_order` int NOT NULL,
  `quiz_order_quiz_order_id` int NOT NULL,
  PRIMARY KEY (`quiz_id`),
  KEY `fk_quiz_exams1_idx` (`exams_exam_id`),
  KEY `fk_quiz_quiz_type1_idx` (`quiz_type_quiz_type_id`),
  KEY `fk_quiz_users1_idx` (`users_user_id`),
  KEY `FK_quiz_quiz_order` (`quiz_order_quiz_order_id`),
  CONSTRAINT `fk_quiz_exams1` FOREIGN KEY (`exams_exam_id`) REFERENCES `exams` (`exam_id`),
  CONSTRAINT `FK_quiz_quiz_order` FOREIGN KEY (`quiz_order_quiz_order_id`) REFERENCES `quiz_order` (`quiz_order_id`),
  CONSTRAINT `fk_quiz_quiz_type1` FOREIGN KEY (`quiz_type_quiz_type_id`) REFERENCES `quiz_type` (`quiz_type_id`),
  CONSTRAINT `fk_quiz_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.quiz: ~2 rows (approximately)
INSERT INTO `quiz` (`quiz_id`, `start_time`, `end_date`, `end_time`, `duration`, `exams_exam_id`, `quiz_type_quiz_type_id`, `date_added`, `users_user_id`, `quiz_order`, `quiz_order_quiz_order_id`) VALUES
	(4, '22:20:00', '2025-07-03', '22:20:00', '60', 5, 1, '2025-07-02 20:14:20', 3, 0, 1),
	(5, '00:40:00', '2025-08-30', '00:40:00', '60', 6, 2, '2025-08-23 00:39:00', 2, 0, 2);

-- Dumping structure for table lms_enviiz_latest_no_data.quiz_order
CREATE TABLE IF NOT EXISTS `quiz_order` (
  `quiz_order_id` int NOT NULL AUTO_INCREMENT,
  `quiz_order` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`quiz_order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table lms_enviiz_latest_no_data.quiz_order: ~2 rows (approximately)
INSERT INTO `quiz_order` (`quiz_order_id`, `quiz_order`) VALUES
	(1, 'Random'),
	(2, 'Default');

-- Dumping structure for table lms_enviiz_latest_no_data.quiz_reset_status
CREATE TABLE IF NOT EXISTS `quiz_reset_status` (
  `quiz_reset_status_id` int NOT NULL AUTO_INCREMENT,
  `quiz_reset_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`quiz_reset_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table lms_enviiz_latest_no_data.quiz_reset_status: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.quiz_type
CREATE TABLE IF NOT EXISTS `quiz_type` (
  `quiz_type_id` int NOT NULL AUTO_INCREMENT,
  `quiz_type` varchar(45) NOT NULL,
  PRIMARY KEY (`quiz_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.quiz_type: ~2 rows (approximately)
INSERT INTO `quiz_type` (`quiz_type_id`, `quiz_type`) VALUES
	(1, 'Random'),
	(2, 'Default');

-- Dumping structure for table lms_enviiz_latest_no_data.status
CREATE TABLE IF NOT EXISTS `status` (
  `status_id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(10) NOT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.status: ~2 rows (approximately)
INSERT INTO `status` (`status_id`, `status`) VALUES
	(1, 'Active'),
	(2, 'Inactive');

-- Dumping structure for table lms_enviiz_latest_no_data.students
CREATE TABLE IF NOT EXISTS `students` (
  `student_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `name_with_initials` varchar(45) NOT NULL,
  `date_of_birth` date NOT NULL,
  `nic` varchar(12) NOT NULL,
  `profile_img` varchar(45) DEFAULT NULL,
  `title_title_id` int NOT NULL,
  `marital_status_marital_status_id` int NOT NULL,
  `mobile` varchar(45) NOT NULL,
  `nationality` varchar(45) NOT NULL,
  `country_of_birth` varchar(45) NOT NULL,
  `country_of_residence` varchar(45) NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int DEFAULT NULL,
  `gender_gender_id` int NOT NULL,
  `login_details_login_details_id` int DEFAULT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `mobile_UNIQUE` (`mobile`),
  UNIQUE KEY `nic_UNIQUE` (`nic`),
  KEY `fk_students_title1_idx` (`title_title_id`),
  KEY `fk_students_marital_status1_idx` (`marital_status_marital_status_id`),
  KEY `fk_students_users1_idx` (`users_user_id`),
  KEY `fk_students_gender1_idx` (`gender_gender_id`),
  CONSTRAINT `fk_students_gender1` FOREIGN KEY (`gender_gender_id`) REFERENCES `gender` (`gender_id`),
  CONSTRAINT `fk_students_marital_status1` FOREIGN KEY (`marital_status_marital_status_id`) REFERENCES `marital_status` (`marital_status_id`),
  CONSTRAINT `fk_students_title1` FOREIGN KEY (`title_title_id`) REFERENCES `title` (`title_id`),
  CONSTRAINT `fk_students_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students: ~19 rows (approximately)
INSERT INTO `students` (`student_id`, `full_name`, `name_with_initials`, `date_of_birth`, `nic`, `profile_img`, `title_title_id`, `marital_status_marital_status_id`, `mobile`, `nationality`, `country_of_birth`, `country_of_residence`, `date_added`, `users_user_id`, `gender_gender_id`, `login_details_login_details_id`) VALUES
	(1, 'Sahan Athuraliya', 'A.Sahan', '2025-06-12', '123456789012', NULL, 5, 1, '0766859513', 'Sinhala', 'Sri Lanka', 'Sri Lanka', '2025-06-12 00:27:06', 1, 1, 1),
	(2, 'Nimal Perera', 'Nimal Perera', '2025-06-16', '123456789v', NULL, 5, 1, '0712345678', 'Sinhala', 'Sri Lanka', 'Sri Lanka', '2025-06-16 13:49:40', 1, 1, 2),
	(3, 'Sanduni Fernando', 'Sanduni Fernando', '2025-06-16', '987654321v', NULL, 7, 1, '0701122334', 'Sinhala', 'Sri Lanka', 'Sri Lanka', '2025-06-16 14:08:10', 1, 2, 3),
	(4, 'Tharindu Jayasinghe', 'Tharindu Jayasinghe', '2025-06-16', '121234341v', NULL, 5, 1, '0711223344', 'Sinhala', 'Sri Lanka', 'Sri Lanka', '2025-06-16 14:09:18', 1, 1, 4),
	(5, 'Lakshan Sasanka', 'Lakshan Sasanka', '2025-06-25', '200234567890', NULL, 5, 1, '0750699388', 'Sinhala', 'Sri Lanka', 'Sri Lanka', '2025-06-25 23:22:53', 1, 1, 5),
	(6, 'Rashmi Kaveesha', 'R.K Perera', '2005-03-19', '200557903332', NULL, 1, 1, '', 'Sinhala', 'Sri Lanka', 'Sri Lanka', '2025-06-26 01:22:29', 3, 2, 6),
	(7, 'Sadev Kaveesha', 'A S Kaveesha', '2002-12-14', '200234900760', NULL, 5, 1, '0775500325', 'Sinhala', 'Sri Lanka', 'Sri Lanka', '2025-08-14 00:11:29', 2, 1, 7),
	(8, 'Harinda Madushan Udugamavithana', 'H M Udugamavithana', '1990-02-15', '199002150000', NULL, 5, 2, '0719329935', 'Sinhala', 'Sri Lanka', 'Sri Lanka', '2025-08-24 20:51:05', 1, 1, 8),
	(9, 'Sahan Lakshitha', 'Sahan Lakshitha', '2025-11-04', '200000000000', NULL, 5, 1, '0767309224', 'Sinhala', 'Sri Lanka', 'Sri Lanka', '2025-08-24 22:55:32', 1, 1, 9),
	(32, 'Pathirannehelage Nevil Senarath Pathirana ', 'P.N.S.pathirana ', '1976-05-15', '197614000000', NULL, 1, 2, '0765767300', 'Sri lanken', 'sri lanka ', 'Sri lanka ', '2025-11-21 20:51:14', 3, 1, 15),
	(33, 'Hansani Malkanthi Kulasekara ', 'H.M.Kulasekara', '1997-05-05', '976264584V', NULL, 6, 2, '0771037763', 'Sri Lankan', 'Sri Lanka', 'Sri Lanka', '2025-11-21 20:53:37', 3, 2, 16),
	(34, 'Pitiye watte gedara Geethani samarakoon', 'P.W.G. Geethani samarakoon', '1985-11-16', '858211859V', NULL, 7, 2, '0786300433', 'Sinhala', 'Sri lanka', 'Sri lanka', '2025-11-21 20:53:37', 3, 2, 17),
	(35, 'Don Chula Harshani Jayasinghe ', 'D.C.H.Jayasinghe ', '1984-06-24', '198468000000', NULL, 6, 2, '0773850518', 'Sinhala ', 'Sri Lanka', 'Sri Lanka', '2025-11-21 20:53:37', 3, 2, 18),
	(36, 'Ranathunga Appuhamilage Erandi Anuruddhika Ranathunga ', 'R.A.E.A. Ranathunga ', '1980-02-08', '805397454V', NULL, 7, 2, '0767502881', 'Sinhala ', 'Sri Lanka', 'Yes', '2025-11-21 20:53:37', 3, 2, 19),
	(37, 'Basnayake Mudiyanselage Chamara Madusanka Basnayake ', 'B.M.C.M. Basnayake ', '1990-12-30', '903650885V', NULL, 5, 1, '0716509053', 'Sinhalese ', 'Sri Lanka', 'Sri Lanka ', '2025-11-21 20:53:37', 3, 1, 20),
	(38, 'Kanapaddala Gamage Chamila Udayani ', 'K. G. C. Udayani ', '1984-12-31', '848662046v', NULL, 6, 2, '0778322820', 'Sinhala ', 'Sri lanka ', 'Sri lanka ', '2025-11-21 20:53:37', 3, 2, 21),
	(39, 'Hewa Gajaman Paththinige Thisum Ishara Gajamange', 'H. G. P. Thisum Ishara Gajamange', '1989-04-23', '198961000000', NULL, 7, 1, '0710193655', 'Sri Lankan', 'Sri Lanka', 'Sri Lanka', '2025-11-21 20:53:37', 3, 2, 22),
	(40, 'Gajanayaka Kankanamalage Harsha Erandi Gajanayaka', 'G.K.H.E.Gajanayaka', '1987-06-04', '876560585V', NULL, 6, 2, '0766391459', 'Sri Lankan', 'Sri Lanka', 'Nittambuwa', '2025-11-21 20:53:37', 3, 2, 23),
	(41, 'Kuttipathiranage Sulochana Sandamali ', 'K.P.Sulochana Sandamali', '2001-07-10', '200169000000', NULL, 7, 1, '0760800314', 'Sinhala ', 'Sri Lanka ', 'Colombo ', '2025-11-21 20:53:37', 3, 2, 24);

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_assignments
CREATE TABLE IF NOT EXISTS `students_has_assignments` (
  `students_has_assignments_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `assignments_assignment_id` int NOT NULL,
  `upload` varchar(45) NOT NULL,
  `uploaded_at` datetime NOT NULL,
  `submission_submission_id` int NOT NULL,
  `assignment_marks_status_assignment_marks_status_id` int NOT NULL,
  PRIMARY KEY (`students_has_assignments_id`),
  KEY `fk_students_has_assignments_assignments1_idx` (`assignments_assignment_id`),
  KEY `fk_students_has_assignments_students1_idx` (`students_student_id`),
  KEY `fk_students_has_assignments_submission1_idx` (`submission_submission_id`),
  KEY `fk_students_has_assignments_assignment_marks_status1_idx` (`assignment_marks_status_assignment_marks_status_id`),
  CONSTRAINT `fk_students_has_assignments_assignment_marks_status1` FOREIGN KEY (`assignment_marks_status_assignment_marks_status_id`) REFERENCES `assignment_marks_status` (`assignment_marks_status_id`),
  CONSTRAINT `fk_students_has_assignments_assignments1` FOREIGN KEY (`assignments_assignment_id`) REFERENCES `assignments` (`assignment_id`),
  CONSTRAINT `fk_students_has_assignments_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`),
  CONSTRAINT `fk_students_has_assignments_submission1` FOREIGN KEY (`submission_submission_id`) REFERENCES `assignment_submission` (`submission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_assignments: ~0 rows (approximately)
INSERT INTO `students_has_assignments` (`students_has_assignments_id`, `students_student_id`, `assignments_assignment_id`, `upload`, `uploaded_at`, `submission_submission_id`, `assignment_marks_status_assignment_marks_status_id`) VALUES
	(6, 6, 1, '690d9b2964b37.pdf', '2025-11-07 12:39:29', 1, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_assignments_has_plagiarism
CREATE TABLE IF NOT EXISTS `students_has_assignments_has_plagiarism` (
  `students_has_plagiarism_id` int NOT NULL AUTO_INCREMENT,
  `students_has_assignments_students_has_assignments_id` int NOT NULL,
  `students_has_plagiarism_students_has_plagiarism_id` int NOT NULL,
  PRIMARY KEY (`students_has_plagiarism_id`),
  KEY `fk_students_has_assignments_has_plagiarism_students_has_ass_idx` (`students_has_assignments_students_has_assignments_id`),
  KEY `fk_students_has_assignments_has_plagiarism_students_has_pla_idx` (`students_has_plagiarism_students_has_plagiarism_id`),
  CONSTRAINT `fk_students_has_assignments_has_plagiarism_students_has_assig1` FOREIGN KEY (`students_has_assignments_students_has_assignments_id`) REFERENCES `students_has_assignments` (`students_has_assignments_id`),
  CONSTRAINT `fk_students_has_assignments_has_plagiarism_students_has_plagi1` FOREIGN KEY (`students_has_plagiarism_students_has_plagiarism_id`) REFERENCES `students_has_plagiarism` (`students_has_plagiarism_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_assignments_has_plagiarism: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_essay_questions
CREATE TABLE IF NOT EXISTS `students_has_essay_questions` (
  `students_has_essay_questions_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `essay_questions_essay_question_id` int NOT NULL,
  `answer` text NOT NULL,
  PRIMARY KEY (`students_has_essay_questions_id`),
  KEY `fk_students_has_essay_questions_essay_questions1_idx` (`essay_questions_essay_question_id`),
  KEY `fk_students_has_essay_questions_students1_idx` (`students_student_id`),
  CONSTRAINT `fk_students_has_essay_questions_essay_questions1` FOREIGN KEY (`essay_questions_essay_question_id`) REFERENCES `essay_questions` (`essay_question_id`),
  CONSTRAINT `fk_students_has_essay_questions_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_essay_questions: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_help_desk
CREATE TABLE IF NOT EXISTS `students_has_help_desk` (
  `students_has_help_desk_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `help_desk_help_desk` int NOT NULL,
  `date_added` datetime NOT NULL,
  `message` text,
  `help_desk_status_help_desk_status_id` int NOT NULL,
  `users_user_id` int DEFAULT NULL,
  PRIMARY KEY (`students_has_help_desk_id`),
  KEY `fk_students_has_help_desk_help_desk1_idx` (`help_desk_help_desk`),
  KEY `fk_students_has_help_desk_students1_idx` (`students_student_id`),
  KEY `fk_students_has_help_desk_help_desk_status1_idx` (`help_desk_status_help_desk_status_id`),
  KEY `FK_students_has_help_desk_users` (`users_user_id`),
  CONSTRAINT `fk_students_has_help_desk_help_desk1` FOREIGN KEY (`help_desk_help_desk`) REFERENCES `help_desk` (`help_desk`),
  CONSTRAINT `fk_students_has_help_desk_help_desk_status1` FOREIGN KEY (`help_desk_status_help_desk_status_id`) REFERENCES `help_desk_status` (`help_desk_status_id`),
  CONSTRAINT `fk_students_has_help_desk_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`),
  CONSTRAINT `FK_students_has_help_desk_users` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_help_desk: ~2 rows (approximately)
INSERT INTO `students_has_help_desk` (`students_has_help_desk_id`, `students_student_id`, `help_desk_help_desk`, `date_added`, `message`, `help_desk_status_help_desk_status_id`, `users_user_id`) VALUES
	(9, 6, 7, '2025-10-15 23:04:51', 'I’m unable to add my password. The system shows an error — please help me fix this.', 3, 1),
	(10, 6, 1, '2025-10-17 11:25:11', 'test', 2, 6);

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_intakes
CREATE TABLE IF NOT EXISTS `students_has_intakes` (
  `students_has_intakes_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `intakes_intake_id` int NOT NULL,
  `student_id` varchar(45) NOT NULL,
  `addmission_date` datetime NOT NULL,
  `status_status_id` int NOT NULL,
  `users_user_id` int DEFAULT NULL,
  PRIMARY KEY (`students_has_intakes_id`),
  KEY `fk_students_has_intakes_intakes1_idx` (`intakes_intake_id`),
  KEY `fk_students_has_intakes_students1_idx` (`students_student_id`),
  KEY `fk_students_has_intakes_status1_idx` (`status_status_id`),
  KEY `fk_students_has_intakes_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_students_has_intakes_intakes1` FOREIGN KEY (`intakes_intake_id`) REFERENCES `intakes` (`intake_id`),
  CONSTRAINT `fk_students_has_intakes_status1` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_students_has_intakes_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`),
  CONSTRAINT `fk_students_has_intakes_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_intakes: ~20 rows (approximately)
INSERT INTO `students_has_intakes` (`students_has_intakes_id`, `students_student_id`, `intakes_intake_id`, `student_id`, `addmission_date`, `status_status_id`, `users_user_id`) VALUES
	(1, 1, 1, 'STID/001', '2025-06-25 23:13:33', 1, 1),
	(2, 3, 1, 'STID/002', '2025-06-25 23:13:46', 1, 1),
	(3, 1, 3, 'STID/IT/001', '2025-06-25 23:14:38', 1, 1),
	(4, 4, 3, 'STID/IT/002', '2025-06-25 23:14:49', 1, 1),
	(5, 5, 1, 'STID/003', '2025-06-25 23:23:44', 1, 1),
	(6, 5, 3, 'STID/IT/003', '2025-06-25 23:24:20', 1, 1),
	(7, 6, 1, 'STID/004', '2025-06-26 01:23:46', 1, 3),
	(8, 6, 2, 'ID01', '2025-07-28 11:09:22', 1, 1),
	(9, 4, 1, 'STID/0002', '2025-07-30 20:20:32', 1, 1),
	(10, 7, 1, 'STU00199', '2025-08-14 00:15:04', 1, 2),
	(11, 2, 1, 'Sample', '2025-08-21 22:26:56', 1, 1),
	(12, 8, 1, 'Test/Harinda', '2025-08-25 19:23:08', 1, 1),
	(31, 32, 1, 'HDP/SNE/2026/001', '2025-11-21 20:51:14', 1, 3),
	(32, 33, 1, 'HDP/SNE/2026/002', '2025-11-21 20:53:37', 1, 3),
	(33, 34, 1, 'HDP/SNE/2026/003', '2025-11-21 20:53:37', 1, 3),
	(34, 35, 1, 'HDP/SNE/2026/004', '2025-11-21 20:53:37', 1, 3),
	(35, 36, 1, 'HDP/SNE/2026/005', '2025-11-21 20:53:37', 1, 3),
	(36, 37, 1, 'HDP/SNE/2026/006', '2025-11-21 20:53:37', 1, 3),
	(37, 38, 1, 'HDP/SNE/2026/007', '2025-11-21 20:53:37', 1, 3),
	(38, 39, 1, 'HDP/SNE/2026/008', '2025-11-21 20:53:37', 1, 3),
	(39, 40, 1, 'HDP/SNE/2026/009', '2025-11-21 20:53:37', 1, 3),
	(40, 41, 1, 'HDP/SNE/2026/010', '2025-11-21 20:53:37', 1, 3);

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_online_exam
CREATE TABLE IF NOT EXISTS `students_has_online_exam` (
  `students_has_online_exam_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `online_exam_online_exam_id` int NOT NULL,
  `exam_status_exam_status_id` int NOT NULL,
  `marks` double DEFAULT NULL,
  `submitted_at` datetime NOT NULL,
  PRIMARY KEY (`students_has_online_exam_id`),
  KEY `fk_students_has_online_exam_online_exam1_idx` (`online_exam_online_exam_id`),
  KEY `fk_students_has_online_exam_students1_idx` (`students_student_id`),
  KEY `fk_students_has_online_exam_exam_status1_idx` (`exam_status_exam_status_id`),
  CONSTRAINT `fk_students_has_online_exam_exam_status1` FOREIGN KEY (`exam_status_exam_status_id`) REFERENCES `exam_status` (`exam_status_id`),
  CONSTRAINT `fk_students_has_online_exam_online_exam1` FOREIGN KEY (`online_exam_online_exam_id`) REFERENCES `online_exam` (`online_exam_id`),
  CONSTRAINT `fk_students_has_online_exam_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_online_exam: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_physical_exam
CREATE TABLE IF NOT EXISTS `students_has_physical_exam` (
  `students_has_physical_exam_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `physical_exam_physical_exam_id` int NOT NULL,
  `admission` varchar(45) NOT NULL,
  `marks` double DEFAULT NULL,
  `exam_status_exam_status_id` int NOT NULL,
  PRIMARY KEY (`students_has_physical_exam_id`),
  KEY `fk_students_has_physical_exam_physical_exam1_idx` (`physical_exam_physical_exam_id`),
  KEY `fk_students_has_physical_exam_students1_idx` (`students_student_id`),
  KEY `fk_students_has_physical_exam_exam_status1_idx` (`exam_status_exam_status_id`),
  CONSTRAINT `fk_students_has_physical_exam_exam_status1` FOREIGN KEY (`exam_status_exam_status_id`) REFERENCES `exam_status` (`exam_status_id`),
  CONSTRAINT `fk_students_has_physical_exam_physical_exam1` FOREIGN KEY (`physical_exam_physical_exam_id`) REFERENCES `physical_exam` (`physical_exam_id`),
  CONSTRAINT `fk_students_has_physical_exam_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_physical_exam: ~8 rows (approximately)
INSERT INTO `students_has_physical_exam` (`students_has_physical_exam_id`, `students_student_id`, `physical_exam_physical_exam_id`, `admission`, `marks`, `exam_status_exam_status_id`) VALUES
	(15, 1, 3, '741552', NULL, 1),
	(16, 3, 3, '870279', NULL, 1),
	(17, 5, 3, '878528', NULL, 1),
	(18, 6, 3, '364712', NULL, 1),
	(19, 4, 3, '823761', NULL, 1),
	(20, 7, 3, '616712', NULL, 1),
	(21, 2, 3, '620830', NULL, 1),
	(22, 8, 3, '269534', NULL, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_plagiarism
CREATE TABLE IF NOT EXISTS `students_has_plagiarism` (
  `students_has_plagiarism_id` int NOT NULL AUTO_INCREMENT,
  `plagiarism` varchar(45) NOT NULL,
  `date_checked` datetime NOT NULL,
  `assignments_assignment_id` int NOT NULL,
  `students_student_id` int NOT NULL,
  PRIMARY KEY (`students_has_plagiarism_id`),
  KEY `fk_students_has_plagiarism_assignments1_idx` (`assignments_assignment_id`),
  KEY `fk_students_has_plagiarism_students1_idx` (`students_student_id`),
  CONSTRAINT `fk_students_has_plagiarism_assignments1` FOREIGN KEY (`assignments_assignment_id`) REFERENCES `assignments` (`assignment_id`),
  CONSTRAINT `fk_students_has_plagiarism_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_plagiarism: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_questions
CREATE TABLE IF NOT EXISTS `students_has_questions` (
  `students_has_questions_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `questions_question_id` int NOT NULL,
  `answers_answer_id` int NOT NULL,
  PRIMARY KEY (`students_has_questions_id`),
  KEY `fk_students_has_questions_questions1_idx` (`questions_question_id`),
  KEY `fk_students_has_questions_students1_idx` (`students_student_id`),
  KEY `fk_students_has_questions_answers1_idx` (`answers_answer_id`),
  CONSTRAINT `fk_students_has_questions_answers1` FOREIGN KEY (`answers_answer_id`) REFERENCES `answers` (`answer_id`),
  CONSTRAINT `fk_students_has_questions_questions1` FOREIGN KEY (`questions_question_id`) REFERENCES `questions` (`question_id`),
  CONSTRAINT `fk_students_has_questions_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_questions: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_quiz
CREATE TABLE IF NOT EXISTS `students_has_quiz` (
  `students_has_quiz_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `quiz_quiz_id` int NOT NULL,
  `marks` double NOT NULL,
  `exam_status_exam_status_id` int NOT NULL,
  `submitted_at` datetime NOT NULL,
  PRIMARY KEY (`students_has_quiz_id`),
  KEY `fk_students_has_quiz_quiz1_idx` (`quiz_quiz_id`),
  KEY `fk_students_has_quiz_students1_idx` (`students_student_id`),
  KEY `fk_students_has_quiz_exam_status1_idx` (`exam_status_exam_status_id`),
  CONSTRAINT `fk_students_has_quiz_exam_status1` FOREIGN KEY (`exam_status_exam_status_id`) REFERENCES `exam_status` (`exam_status_id`),
  CONSTRAINT `fk_students_has_quiz_quiz1` FOREIGN KEY (`quiz_quiz_id`) REFERENCES `quiz` (`quiz_id`),
  CONSTRAINT `fk_students_has_quiz_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_quiz: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_quiz_reset_request
CREATE TABLE IF NOT EXISTS `students_has_quiz_reset_request` (
  `students_has_quiz_reset_request_id` int NOT NULL AUTO_INCREMENT,
  `students_has_quiz_students_has_quiz_id` int NOT NULL,
  `request_date_time` datetime NOT NULL,
  `quiz_reset_status_quiz_reset_status_id` int NOT NULL,
  `users_user_id` int DEFAULT NULL,
  `update_date_tme` datetime DEFAULT NULL,
  PRIMARY KEY (`students_has_quiz_reset_request_id`),
  KEY `FK_students_has_quiz_reset_requests_students_has_quiz` (`students_has_quiz_students_has_quiz_id`),
  KEY `FK_students_has_quiz_reset_requests_quiz_reset_status` (`quiz_reset_status_quiz_reset_status_id`),
  KEY `FK_students_has_quiz_reset_requests_users` (`users_user_id`),
  CONSTRAINT `FK_students_has_quiz_reset_requests_quiz_reset_status` FOREIGN KEY (`quiz_reset_status_quiz_reset_status_id`) REFERENCES `quiz_reset_status` (`quiz_reset_status_id`),
  CONSTRAINT `FK_students_has_quiz_reset_requests_students_has_quiz` FOREIGN KEY (`students_has_quiz_students_has_quiz_id`) REFERENCES `students_has_quiz` (`students_has_quiz_id`),
  CONSTRAINT `FK_students_has_quiz_reset_requests_users` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_quiz_reset_request: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.students_has_subjects
CREATE TABLE IF NOT EXISTS `students_has_subjects` (
  `students_has_subjects_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `subjects_subject_id` int NOT NULL,
  `added_at` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  `status_status_id` int NOT NULL,
  PRIMARY KEY (`students_has_subjects_id`),
  KEY `fk_students_has_subjects_subjects1_idx` (`subjects_subject_id`),
  KEY `fk_students_has_subjects_students1_idx` (`students_student_id`),
  KEY `fk_students_has_subjects_users1_idx` (`users_user_id`),
  KEY `fk_students_has_subjects_status1_idx` (`status_status_id`),
  CONSTRAINT `fk_students_has_subjects_status1` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_students_has_subjects_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`),
  CONSTRAINT `fk_students_has_subjects_subjects1` FOREIGN KEY (`subjects_subject_id`) REFERENCES `subjects` (`subject_id`),
  CONSTRAINT `fk_students_has_subjects_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.students_has_subjects: ~0 rows (approximately)
INSERT INTO `students_has_subjects` (`students_has_subjects_id`, `students_student_id`, `subjects_subject_id`, `added_at`, `users_user_id`, `status_status_id`) VALUES
	(1, 1, 2, '2025-08-14 00:11:31', 1, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.student_has_address
CREATE TABLE IF NOT EXISTS `student_has_address` (
  `student_has_address_id` int NOT NULL AUTO_INCREMENT,
  `address_line_1` varchar(100) NOT NULL,
  `address_line_2` varchar(100) DEFAULT NULL,
  `postal_code` varchar(5) NOT NULL,
  `students_student_id` int NOT NULL,
  `city_city_id` int NOT NULL,
  PRIMARY KEY (`student_has_address_id`),
  KEY `fk_student_has_address_students1_idx` (`students_student_id`),
  KEY `fk_student_has_address_city1_idx` (`city_city_id`),
  CONSTRAINT `fk_student_has_address_city1` FOREIGN KEY (`city_city_id`) REFERENCES `city` (`city_id`),
  CONSTRAINT `fk_student_has_address_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.student_has_address: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.student_policy
CREATE TABLE IF NOT EXISTS `student_policy` (
  `student_policy_id` int NOT NULL AUTO_INCREMENT,
  `student_policy` text NOT NULL,
  `date_updated` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`student_policy_id`),
  KEY `fk_student_policy_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_student_policy_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.student_policy: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.subjects
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` int NOT NULL AUTO_INCREMENT,
  `subject` varchar(100) NOT NULL,
  `subject_cover` varchar(45) NOT NULL,
  `max_course_hours` double NOT NULL,
  `levels_level_id` int NOT NULL,
  `subjects_order` int NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  `subject_type_subject_type_id` int NOT NULL,
  `subject_group_subject_group_id` int DEFAULT NULL,
  PRIMARY KEY (`subject_id`),
  KEY `fk_subjects_levels1_idx` (`levels_level_id`),
  KEY `fk_subjects_users2_idx` (`users_user_id`),
  KEY `fk_subjects_subject_type1_idx` (`subject_type_subject_type_id`),
  KEY `fk_subjects_subject_group1_idx` (`subject_group_subject_group_id`),
  CONSTRAINT `fk_subjects_levels1` FOREIGN KEY (`levels_level_id`) REFERENCES `levels` (`level_id`),
  CONSTRAINT `fk_subjects_subject_group1` FOREIGN KEY (`subject_group_subject_group_id`) REFERENCES `subject_group` (`subject_group_id`),
  CONSTRAINT `fk_subjects_subject_type1` FOREIGN KEY (`subject_type_subject_type_id`) REFERENCES `subject_type` (`subject_type_id`),
  CONSTRAINT `fk_subjects_users2` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.subjects: ~4 rows (approximately)
INSERT INTO `subjects` (`subject_id`, `subject`, `subject_cover`, `max_course_hours`, `levels_level_id`, `subjects_order`, `date_added`, `users_user_id`, `subject_type_subject_type_id`, `subject_group_subject_group_id`) VALUES
	(1, 'OOPC', '6849d2dae4be8.png', 8, 1, 0, '2025-06-12 00:32:50', 1, 1, NULL),
	(2, 'Web Programing', '6849d3255018e.png', 10, 1, 0, '2025-06-12 00:34:05', 1, 2, 1),
	(3, 'Subject 001', '685c5d2cea511.png', 10, 10, 0, '2025-06-26 02:03:48', 3, 1, NULL),
	(4, 'Subjecct 002', '685c5d4f6c2e3.png', 10, 10, 0, '2025-06-26 02:04:23', 3, 1, NULL),
	(5, 'Android ', '6900734ed52c5.png', 10, 1, 0, '2025-10-28 13:09:58', 3, 2, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.subject_group
CREATE TABLE IF NOT EXISTS `subject_group` (
  `subject_group_id` int NOT NULL AUTO_INCREMENT,
  `subject_group` varchar(45) NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  `levels_level_id` int NOT NULL,
  PRIMARY KEY (`subject_group_id`),
  KEY `fk_subject_group_users1_idx` (`users_user_id`),
  KEY `fk_subject_group_levels1_idx` (`levels_level_id`),
  CONSTRAINT `fk_subject_group_levels1` FOREIGN KEY (`levels_level_id`) REFERENCES `levels` (`level_id`),
  CONSTRAINT `fk_subject_group_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.subject_group: ~0 rows (approximately)
INSERT INTO `subject_group` (`subject_group_id`, `subject_group`, `date_added`, `users_user_id`, `levels_level_id`) VALUES
	(1, 'Group 001', '2025-06-12 00:34:05', 1, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.subject_type
CREATE TABLE IF NOT EXISTS `subject_type` (
  `subject_type_id` int NOT NULL AUTO_INCREMENT,
  `subject_type` varchar(45) NOT NULL,
  PRIMARY KEY (`subject_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.subject_type: ~2 rows (approximately)
INSERT INTO `subject_type` (`subject_type_id`, `subject_type`) VALUES
	(1, 'Core Subject'),
	(2, 'Selective Subject');

-- Dumping structure for table lms_enviiz_latest_no_data.submission_sts
CREATE TABLE IF NOT EXISTS `submission_sts` (
  `submission_sts_id` int NOT NULL AUTO_INCREMENT,
  `submission_sts` varchar(45) NOT NULL,
  PRIMARY KEY (`submission_sts_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.submission_sts: ~2 rows (approximately)
INSERT INTO `submission_sts` (`submission_sts_id`, `submission_sts`) VALUES
	(1, 'Pending'),
	(2, 'Mark Released');

-- Dumping structure for table lms_enviiz_latest_no_data.system_languages
CREATE TABLE IF NOT EXISTS `system_languages` (
  `system_language_id` int NOT NULL AUTO_INCREMENT,
  `language` varchar(25) NOT NULL,
  PRIMARY KEY (`system_language_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table lms_enviiz_latest_no_data.system_languages: ~2 rows (approximately)
INSERT INTO `system_languages` (`system_language_id`, `language`) VALUES
	(1, 'English'),
	(2, 'Sinhala'),
	(3, 'Tamil');

-- Dumping structure for table lms_enviiz_latest_no_data.system_themes
CREATE TABLE IF NOT EXISTS `system_themes` (
  `system_theme_id` int NOT NULL AUTO_INCREMENT,
  `theme` varchar(10) NOT NULL,
  PRIMARY KEY (`system_theme_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table lms_enviiz_latest_no_data.system_themes: ~2 rows (approximately)
INSERT INTO `system_themes` (`system_theme_id`, `theme`) VALUES
	(1, 'Light'),
	(2, 'Dark');

-- Dumping structure for table lms_enviiz_latest_no_data.time_table
CREATE TABLE IF NOT EXISTS `time_table` (
  `time_table_id` int NOT NULL AUTO_INCREMENT,
  `date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `duration` double NOT NULL,
  `lecture_type_lecture_type_id` int NOT NULL,
  `lecture` text NOT NULL,
  `subjects_subject_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  `date_added` datetime NOT NULL,
  `time_table_status_time_table_status_id` int NOT NULL,
  `lecturers_lecturers_id` int NOT NULL,
  `lecture_attendance_status_lecture_attendance_status_id` int DEFAULT NULL,
  `lecture_title` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`time_table_id`),
  KEY `fk_time_table_users1_idx` (`users_user_id`),
  KEY `fk_time_table_lecture_type1_idx` (`lecture_type_lecture_type_id`),
  KEY `fk_time_table_subjects1_idx` (`subjects_subject_id`),
  KEY `fk_time_table_time_table_status1_idx` (`time_table_status_time_table_status_id`),
  KEY `fk_time_table_lecturers1_idx` (`lecturers_lecturers_id`),
  KEY `FK_time_table_lecture_attendnace_status` (`lecture_attendance_status_lecture_attendance_status_id`),
  CONSTRAINT `FK_time_table_lecture_attendnace_status` FOREIGN KEY (`lecture_attendance_status_lecture_attendance_status_id`) REFERENCES `lecture_attendnace_status` (`lecture_attendnace_status_id`),
  CONSTRAINT `fk_time_table_lecture_type1` FOREIGN KEY (`lecture_type_lecture_type_id`) REFERENCES `lecture_type` (`lecture_type_id`),
  CONSTRAINT `fk_time_table_lecturers1` FOREIGN KEY (`lecturers_lecturers_id`) REFERENCES `lecturers` (`lecturers_id`),
  CONSTRAINT `fk_time_table_subjects1` FOREIGN KEY (`subjects_subject_id`) REFERENCES `subjects` (`subject_id`),
  CONSTRAINT `fk_time_table_time_table_status1` FOREIGN KEY (`time_table_status_time_table_status_id`) REFERENCES `time_table_status` (`time_table_status_id`),
  CONSTRAINT `fk_time_table_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.time_table: ~31 rows (approximately)
INSERT INTO `time_table` (`time_table_id`, `date`, `start_time`, `end_time`, `duration`, `lecture_type_lecture_type_id`, `lecture`, `subjects_subject_id`, `users_user_id`, `date_added`, `time_table_status_time_table_status_id`, `lecturers_lecturers_id`, `lecture_attendance_status_lecture_attendance_status_id`, `lecture_title`) VALUES
	(1, '2025-06-26', '08:00:00', '15:00:00', 420, 1, '', 1, 3, '2025-06-26 01:41:46', 1, 1, NULL, NULL),
	(2, '2025-06-27', '20:00:00', '23:00:00', 180, 2, 'https://us05web.zoom.us/j/85839913761?pwd=8ol51cv4owDV7epoufpakgR2ABafnP.1', 1, 3, '2025-06-26 01:44:28', 1, 1, NULL, NULL),
	(3, '2025-06-28', '08:00:00', '11:00:00', 180, 1, '', 1, 3, '2025-06-26 01:47:07', 1, 1, NULL, NULL),
	(4, '0000-00-00', '00:00:00', '00:00:00', 60, 3, 'https://youtu.be/pTB0EiLXUC8?si=hR0TnY8Zhu0U-GlM', 1, 3, '2025-06-26 01:48:43', 1, 1, NULL, NULL),
	(5, '0000-00-00', '00:00:00', '00:00:00', 60, 3, '<iframe width="560" height="315" src="https://www.youtube.com/embed/U0ksk8Vo3dQ?si=QtFQ-kVYNJo8fkIj" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>', 1, 3, '2025-06-26 01:49:28', 1, 1, NULL, NULL),
	(6, '2025-06-29', '08:30:00', '00:30:00', 480, 1, '', 3, 3, '2025-06-26 02:26:42', 1, 2, NULL, NULL),
	(7, '2025-07-02', '08:30:00', '16:30:00', 480, 1, '', 3, 3, '2025-06-26 02:29:13', 1, 2, NULL, NULL),
	(8, '2025-07-03', '08:00:00', '11:00:00', 180, 2, 'https://javainstitute-edu-lk.zoom.us/meeting/register/GpAm3p_WSH6ATyc8NQmACw', 3, 3, '2025-06-26 02:45:27', 1, 2, NULL, NULL),
	(9, '2025-08-11', '14:00:00', '15:00:00', 60, 2, 'https://zoom.us/j/92216026821?pwd=WTfd346yzvAk6PkwqIzBjM7X883HN9.1', 1, 1, '2025-08-11 14:46:12', 1, 1, NULL, NULL),
	(10, '2025-08-13', '22:00:00', '23:55:00', 115, 1, '', 1, 3, '2025-08-13 21:58:40', 1, 1, NULL, NULL),
	(11, '2025-11-19', '08:00:00', '10:00:00', 120, 2, 'https://youtu.be/dQw4w9WgXcQ', 1, 2, '2025-08-14 00:01:37', 3, 1, NULL, 'Test'),
	(12, '2025-08-14', '12:00:00', '16:00:00', 240, 1, '', 1, 2, '2025-08-14 00:02:17', 1, 1, NULL, NULL),
	(13, '2025-08-14', '16:00:00', '18:00:00', 120, 2, 'https://youtu.be/dQw4w9WgXcQ', 2, 2, '2025-08-14 00:04:36', 1, 4, NULL, NULL),
	(22, '2025-10-19', '20:17:00', '23:05:00', 168, 2, 'https://javainstitute-edu-lk.zoom.us/w/89274456020?tk=lLAyn4N-WmJUlfLEIY2IyFDR33CAtOT2a5AMvFtaYpY.DQcAAAAUySwX1BZBamVrV0hmV1FaR2xObkdhQW0wUThBAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA&pwd=qnriBPuLPbHkj8pSaoIbC1yWUFIP4L.1#success', 1, 3, '2025-10-19 00:06:10', 1, 3, NULL, 'Web Day 1'),
	(23, '2025-10-20', '12:02:00', '15:01:00', 179, 1, '', 1, 3, '2025-10-20 12:02:08', 1, 1, NULL, 'Web Day 2'),
	(24, '2025-10-20', '19:58:00', '22:58:00', 180, 1, '', 1, 3, '2025-10-20 19:58:31', 1, 1, NULL, 'Web Day 3'),
	(25, '2025-10-21', '11:00:00', '16:53:00', 353, 1, '', 2, 3, '2025-10-21 10:53:37', 1, 4, NULL, 'Web Day 4'),
	(26, '2025-10-23', '12:36:00', '14:36:00', 120, 1, '', 1, 3, '2025-10-23 12:36:42', 1, 1, NULL, 'Web Day 1'),
	(27, '2025-10-24', '13:44:00', '15:44:00', 120, 1, '', 1, 3, '2025-10-23 12:45:10', 1, 3, NULL, 'Web Day 1'),
	(28, '2025-10-25', '01:36:00', '05:35:00', 239, 1, '', 1, 3, '2025-10-25 01:36:11', 1, 1, NULL, 'Web Day 10'),
	(29, '2025-10-26', '09:27:00', '11:25:00', 118, 2, 'https://javainstitute-edu-lk.zoom.us/w/89274456020?tk=lLAyn4N-WmJUlfLEIY2IyFDR33CAtOT2a5AMvFtaYpY.DQcAAAAUySwX1BZBamVrV0hmV1FaR2xObkdhQW0wUThBAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA&pwd=qnriBPuLPbHkj8pSaoIbC1yWUFIP4L.1#success', 1, 3, '2025-10-26 09:25:56', 1, 3, NULL, 'OOPC Day 11'),
	(30, '2025-10-27', '12:54:00', '15:53:00', 179, 2, 'https://javainstitute-edu-lk.zoom.us/w/89274456020?tk=lLAyn4N-WmJUlfLEIY2IyFDR33CAtOT2a5AMvFtaYpY.DQcAAAAUySwX1BZBamVrV0hmV1FaR2xObkdhQW0wUThBAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA&pwd=qnriBPuLPbHkj8pSaoIbC1yWUFIP4L.1#success', 1, 3, '2025-10-27 12:54:04', 1, 1, NULL, 'OOPC Concepts'),
	(31, '2025-10-29', '00:30:00', '03:29:00', 179, 1, '', 1, 3, '2025-10-29 00:29:28', 1, 1, NULL, NULL),
	(32, '2025-10-29', '02:30:00', '04:30:00', 120, 1, '', 1, 3, '2025-10-29 00:30:09', 1, 3, NULL, NULL),
	(33, '2025-10-29', '11:44:00', '14:47:00', 183, 1, '', 1, 3, '2025-10-29 11:45:18', 1, 1, NULL, 'Web III'),
	(34, '2025-11-07', '10:03:00', '11:02:00', 59, 1, '', 1, 3, '2025-11-07 10:02:23', 1, 1, NULL, 'OOPC Day 1'),
	(35, '2025-11-08', '12:26:00', '15:26:00', 180, 1, '', 1, 3, '2025-11-07 12:26:53', 1, 1, NULL, 'tt'),
	(36, '2025-11-09', '08:00:00', '09:00:00', 182, 1, '', 1, 3, '2025-11-09 00:02:34', 1, 3, NULL, 'OOPC Day 01'),
	(37, '2025-11-12', '08:29:00', '10:30:00', 121, 1, '', 1, 3, '2025-11-12 20:30:18', 1, 3, NULL, 'OOPC'),
	(38, '2025-11-11', '08:30:00', '10:30:00', 120, 1, '', 1, 3, '2025-11-12 20:31:08', 2, 3, NULL, 'OOPC'),
	(39, '2025-11-11', '11:30:00', '12:30:00', 60, 1, '', 1, 3, '2025-11-12 20:31:49', 1, 3, NULL, 'OOPC');

-- Dumping structure for table lms_enviiz_latest_no_data.time_table_status
CREATE TABLE IF NOT EXISTS `time_table_status` (
  `time_table_status_id` int NOT NULL AUTO_INCREMENT,
  `time_table_status` varchar(45) NOT NULL,
  PRIMARY KEY (`time_table_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.time_table_status: ~4 rows (approximately)
INSERT INTO `time_table_status` (`time_table_status_id`, `time_table_status`) VALUES
	(1, 'Active'),
	(2, 'Cancelled'),
	(3, 'Postponed'),
	(4, 'Study Leave');

-- Dumping structure for table lms_enviiz_latest_no_data.title
CREATE TABLE IF NOT EXISTS `title` (
  `title_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(10) NOT NULL,
  PRIMARY KEY (`title_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.title: ~7 rows (approximately)
INSERT INTO `title` (`title_id`, `title`) VALUES
	(1, 'Prof'),
	(2, 'Dr'),
	(3, 'Rev'),
	(4, 'Pr'),
	(5, 'Mr'),
	(6, 'Mrs'),
	(7, 'Miss');

-- Dumping structure for table lms_enviiz_latest_no_data.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `title_title_id` int NOT NULL,
  `user_fname` varchar(45) NOT NULL,
  `user_lname` varchar(45) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `user_mobile` varchar(12) NOT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_reg_date` datetime NOT NULL,
  `status_status_id` int NOT NULL,
  `user_roles_user_role_id` int NOT NULL,
  `user_verification` varchar(100) DEFAULT NULL,
  `user_reset_time` varchar(45) DEFAULT NULL,
  `profile_img` varchar(45) DEFAULT NULL,
  `nic` varchar(12) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender_gender_id` int NOT NULL,
  `added_by` int NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_email_UNIQUE` (`user_email`),
  UNIQUE KEY `user_mobile_UNIQUE` (`user_mobile`),
  KEY `fk_users_title1_idx` (`title_title_id`),
  KEY `fk_users_status1_idx` (`status_status_id`),
  KEY `fk_users_user_roles1_idx` (`user_roles_user_role_id`),
  KEY `fk_users_gender1_idx` (`gender_gender_id`),
  KEY `FK_users_users` (`added_by`),
  CONSTRAINT `fk_users_gender1` FOREIGN KEY (`gender_gender_id`) REFERENCES `gender` (`gender_id`),
  CONSTRAINT `fk_users_status1` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_users_title1` FOREIGN KEY (`title_title_id`) REFERENCES `title` (`title_id`),
  CONSTRAINT `fk_users_user_roles1` FOREIGN KEY (`user_roles_user_role_id`) REFERENCES `user_roles` (`user_role_id`),
  CONSTRAINT `FK_users_users` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.users: ~11 rows (approximately)
INSERT INTO `users` (`user_id`, `title_title_id`, `user_fname`, `user_lname`, `user_email`, `user_mobile`, `user_password`, `user_reg_date`, `status_status_id`, `user_roles_user_role_id`, `user_verification`, `user_reset_time`, `profile_img`, `nic`, `dob`, `gender_gender_id`, `added_by`) VALUES
	(1, 5, 'Sahan', 'Athuraliya', 'sahanathuraliya@enviiz.com', '0766859513', '$2y$10$b9d6FFr92QTgAV.AeYni2em1oqr.DqV4eLTcxCqieiqOs4PLMHtAG', '2025-05-20 17:10:46', 2, 1, NULL, NULL, NULL, NULL, NULL, 1, 1),
	(2, 5, 'Sadev', 'Kaveesha', 'sadev1214@gmail.com', '0763581578', '$2y$10$KCbfbxZemrbfamYrQENxwu2giI44j4QhhU.gafTf6MYGmpTgytrpC', '2025-05-20 17:10:47', 1, 1, NULL, NULL, NULL, NULL, NULL, 1, 1),
	(3, 7, 'Rashmi', 'Kaveesha', 'kaveesharashmi48@gmail.com', '0769679778', '$2y$10$zsyAZ3N/Rg.vH3elpzlrNOXdB.F72seMX3rEfMBfGa0Vqr/4NMnQu', '2025-05-20 17:10:48', 1, 1, 'yQWTzcbK9CglDqj1FT3wQme9OoAN2os4zAHVwhMbjrpzHtzCJpwjMGA0qsv5Q5i9YF9HjsAtmCyjx0oeY0MpvoXr9iT6f4NSGD', '2025-10-28 23:37:43', '690a1b9ed11ee.png', '200557903331', '2005-11-04', 2, 1),
	(4, 5, 'Lakshan', 'Sasanka', 'lakshansasa44@gmail.com', '0750699388', '$2y$10$U5ri48DkmCZy7LpSm.4V6.33I0i3qiE0Y3ErMph847ijbGIaflmAO', '2025-06-14 21:19:27', 1, 2, '9657', '2025-06-14 21:19:27', NULL, NULL, '2025-11-04', 1, 1),
	(5, 5, 'Kamal', 'Perera', 'kamal@gmail.com', '0712345678', '$2y$10$b9d6FFr92QTgAV.AeYni2em1oqr.DqV4eLTcxCqieiqOs4PLMHtAG', '2025-06-16 13:17:01', 1, 8, '2546', '2025-06-16 13:17:01', NULL, NULL, NULL, 1, 1),
	(6, 7, 'Pawani', 'Perera', 'pawani@gmail.com', '0771234567', '$2y$10$b9d6FFr92QTgAV.AeYni2em1oqr.DqV4eLTcxCqieiqOs4PLMHtAG', '2025-06-16 13:18:32', 1, 7, '8008', '2025-06-16 13:18:32', NULL, NULL, '2025-11-04', 2, 1),
	(7, 5, 'Prabudda', 'Dissanayake', 'prabudda@gmail.com', '0781234567', '$2y$10$b9d6FFr92QTgAV.AeYni2em1oqr.DqV4eLTcxCqieiqOs4PLMHtAG', '2025-06-16 13:32:15', 1, 5, '8267', '2025-06-16 13:32:15', NULL, NULL, NULL, 1, 1),
	(8, 5, 'Amal', 'Perera', 'amal@gmail.com', '0768956689', '$2y$10$zsyAZ3N/Rg.vH3elpzlrNOXdB.F72seMX3rEfMBfGa0Vqr/4NMnQu', '2025-06-26 01:38:20', 1, 8, 'hZYsUHPZOjWR2tNe5iFPw2tcHEN7Zw1D96wHYV5b7Lms7', '2025-08-14 20:26:44', '69140499f15b6.png', NULL, NULL, 1, 1),
	(9, 5, 'Sahan', 'Lakshitha', '2002lakshitha@gmail.com', '0773099513', '$2y$10$1uR5ETFE3acK7ROZDuUC8elIoZQFWKyO.RNaq6w1CwMU4bAARRlRW', '2025-08-14 00:58:31', 1, 6, '', '2025-08-24 22:56:16', NULL, '', NULL, 1, 1),
	(10, 5, 'Manjula', 'Ganepola', 'lasanthamanju@gmail.com', '0777261864', '$2y$10$FR8RZJc/mXfh9GOHTlu8H.aXhBG5J5lMqnjx3NbVO/9eOZERntfpO', '2025-08-14 13:33:40', 2, 2, '', '', NULL, NULL, '2025-11-04', 1, 1),
	(11, 5, 'Chandana', 'Abeywickrama', 'Thetuition.lk@gmail.com', '0760260260', '$2y$10$UF7DSiTRHWfNM2/jkVC3UuOF.AhogYtGieY3IBwnYLyZKb0QUsGr2', '2025-08-14 01:11:51', 1, 2, '80FLQsC3UjW24Q29mstIq1WbUYaNmX7nLvBwxObHJuvcBmPVLFOXyINLpLxtReKc3rjs9zvQpxRBGZqJKYLU7sLWeGsnv1', '2025-08-14 01:11:51', NULL, NULL, NULL, 1, 1);

-- Dumping structure for table lms_enviiz_latest_no_data.users_devices
CREATE TABLE IF NOT EXISTS `users_devices` (
  `users_devices_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) NOT NULL,
  `os` varchar(100) NOT NULL,
  `browser` varchar(100) NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `login_time` datetime NOT NULL,
  `last_active` datetime NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `is_active` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`users_devices_id`) USING BTREE,
  KEY `FK_user_devices_users` (`users_user_id`),
  CONSTRAINT `FK_user_devices_users` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table lms_enviiz_latest_no_data.users_devices: ~1 rows (approximately)
INSERT INTO `users_devices` (`users_devices_id`, `users_user_id`, `device_name`, `device_type`, `os`, `browser`, `ip_address`, `login_time`, `last_active`, `session_token`, `is_active`) VALUES
	(2, 3, 'WebKit', 'Desktop', 'Windows', 'Chrome', '::1', '2025-11-22 01:32:48', '2025-11-23 10:42:50', '344fc17fb604c11780556649ac3201c15fffadf343b8151adc1152832e29404d', 1),
	(3, 3, 'WebKit', 'Desktop', 'Windows', 'Edge', '::1', '2025-11-22 01:47:29', '2025-11-22 01:47:29', '9a4d141195d6a411588518d5195f5101b1692cb53b95049fc887014f02e91060', 1);

-- Dumping structure for table lms_enviiz_latest_no_data.users_has_permission_to_faculties
CREATE TABLE IF NOT EXISTS `users_has_permission_to_faculties` (
  `users_has_permission_to_faculties_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `permission_to_permission_to_id` int NOT NULL,
  `faculties_faculty_id` int NOT NULL,
  `added_by` int NOT NULL,
  `status_status_id` int NOT NULL,
  `added_at` datetime NOT NULL,
  PRIMARY KEY (`users_has_permission_to_faculties_id`),
  KEY `fk_users_has_permission_to1_permission_to1_idx` (`permission_to_permission_to_id`),
  KEY `fk_users_has_permission_to1_users1_idx` (`users_user_id`),
  KEY `fk_users_has_permission_to1_faculties1_idx` (`faculties_faculty_id`),
  KEY `fk_users_has_permission_to1_users2_idx` (`added_by`),
  KEY `fk_users_has_permission_to1_status1_idx` (`status_status_id`),
  CONSTRAINT `fk_users_has_permission_to1_faculties1` FOREIGN KEY (`faculties_faculty_id`) REFERENCES `faculties` (`faculty_id`),
  CONSTRAINT `fk_users_has_permission_to1_permission_to1` FOREIGN KEY (`permission_to_permission_to_id`) REFERENCES `permission_to` (`permission_to_id`),
  CONSTRAINT `fk_users_has_permission_to1_status1` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_users_has_permission_to1_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_users_has_permission_to1_users2` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.users_has_permission_to_faculties: ~7 rows (approximately)
INSERT INTO `users_has_permission_to_faculties` (`users_has_permission_to_faculties_id`, `users_user_id`, `permission_to_permission_to_id`, `faculties_faculty_id`, `added_by`, `status_status_id`, `added_at`) VALUES
	(4, 9, 59, 1, 3, 2, '2025-11-05 00:46:32'),
	(5, 9, 60, 1, 3, 2, '2025-11-05 00:46:32'),
	(6, 9, 61, 1, 3, 2, '2025-11-05 00:46:32'),
	(7, 7, 61, 1, 3, 1, '2025-11-06 00:52:45'),
	(8, 7, 62, 1, 3, 1, '2025-11-06 00:52:45'),
	(9, 7, 63, 1, 3, 1, '2025-11-06 00:52:45'),
	(10, 7, 60, 1, 3, 1, '2025-11-06 00:52:45');

-- Dumping structure for table lms_enviiz_latest_no_data.users_has_permission_to_intakes
CREATE TABLE IF NOT EXISTS `users_has_permission_to_intakes` (
  `users_has_permission_to_intakes_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `permission_to_permission_to_id` int NOT NULL,
  `intakes_intake_id` int NOT NULL,
  `added_by` int NOT NULL,
  `status_status_id` int NOT NULL,
  `added_at` datetime NOT NULL,
  PRIMARY KEY (`users_has_permission_to_intakes_id`),
  KEY `fk_users_has_permission_to1_permission_to3_idx` (`permission_to_permission_to_id`),
  KEY `fk_users_has_permission_to1_users5_idx` (`users_user_id`),
  KEY `fk_users_has_permission_to1_intakes1_idx` (`intakes_intake_id`),
  KEY `fk_users_has_permission_to1_users6_idx` (`added_by`),
  KEY `fk_users_has_permission_to1_status3_idx` (`status_status_id`),
  CONSTRAINT `fk_users_has_permission_to1_intakes1` FOREIGN KEY (`intakes_intake_id`) REFERENCES `intakes` (`intake_id`),
  CONSTRAINT `fk_users_has_permission_to1_permission_to3` FOREIGN KEY (`permission_to_permission_to_id`) REFERENCES `permission_to` (`permission_to_id`),
  CONSTRAINT `fk_users_has_permission_to1_status3` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_users_has_permission_to1_users5` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_users_has_permission_to1_users6` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.users_has_permission_to_intakes: ~13 rows (approximately)
INSERT INTO `users_has_permission_to_intakes` (`users_has_permission_to_intakes_id`, `users_user_id`, `permission_to_permission_to_id`, `intakes_intake_id`, `added_by`, `status_status_id`, `added_at`) VALUES
	(7, 9, 77, 1, 3, 1, '2025-11-03 20:47:45'),
	(8, 9, 78, 1, 3, 1, '2025-11-03 20:47:45'),
	(9, 9, 84, 1, 3, 1, '2025-11-03 20:47:45'),
	(10, 9, 83, 1, 3, 1, '2025-11-03 20:47:45'),
	(11, 9, 70, 1, 3, 1, '2025-11-03 20:47:45'),
	(12, 9, 71, 1, 3, 1, '2025-11-03 20:47:45'),
	(13, 8, 77, 1, 3, 1, '2025-11-06 01:25:06'),
	(14, 8, 80, 1, 3, 1, '2025-11-06 01:25:06'),
	(15, 8, 81, 1, 3, 1, '2025-11-06 01:25:06'),
	(16, 8, 78, 1, 3, 1, '2025-11-06 01:25:06'),
	(17, 8, 75, 1, 3, 1, '2025-11-06 01:25:06'),
	(18, 8, 72, 1, 3, 1, '2025-11-06 01:25:06'),
	(19, 8, 71, 1, 3, 1, '2025-11-06 01:25:06'),
	(20, 8, 69, 1, 3, 1, '2025-11-06 01:29:03'),
	(21, 8, 70, 1, 3, 1, '2025-11-06 01:29:03');

-- Dumping structure for table lms_enviiz_latest_no_data.users_has_permission_to_levels
CREATE TABLE IF NOT EXISTS `users_has_permission_to_levels` (
  `users_has_permission_to_levels_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `permission_to_permission_to_id` int NOT NULL,
  `levels_level_id` int NOT NULL,
  `status_status_id` int NOT NULL,
  `added_by` int NOT NULL,
  `added_at` datetime NOT NULL,
  PRIMARY KEY (`users_has_permission_to_levels_id`),
  KEY `fk_users_has_permission_to1_permission_to4_idx` (`permission_to_permission_to_id`),
  KEY `fk_users_has_permission_to1_users7_idx` (`users_user_id`),
  KEY `fk_users_has_permission_to1_levels1_idx` (`levels_level_id`),
  KEY `fk_users_has_permission_to1_status4_idx` (`status_status_id`),
  KEY `fk_users_has_permission_to1_users8_idx` (`added_by`),
  CONSTRAINT `fk_users_has_permission_to1_levels1` FOREIGN KEY (`levels_level_id`) REFERENCES `levels` (`level_id`),
  CONSTRAINT `fk_users_has_permission_to1_permission_to4` FOREIGN KEY (`permission_to_permission_to_id`) REFERENCES `permission_to` (`permission_to_id`),
  CONSTRAINT `fk_users_has_permission_to1_status4` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_users_has_permission_to1_users7` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_users_has_permission_to1_users8` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.users_has_permission_to_levels: ~2 rows (approximately)
INSERT INTO `users_has_permission_to_levels` (`users_has_permission_to_levels_id`, `users_user_id`, `permission_to_permission_to_id`, `levels_level_id`, `status_status_id`, `added_by`, `added_at`) VALUES
	(4, 9, 87, 1, 1, 3, '2025-11-03 21:11:50'),
	(5, 9, 88, 1, 1, 3, '2025-11-03 21:11:50'),
	(6, 9, 89, 1, 1, 3, '2025-11-06 01:38:19');

-- Dumping structure for table lms_enviiz_latest_no_data.users_has_permission_to_other
CREATE TABLE IF NOT EXISTS `users_has_permission_to_other` (
  `users_has_permission_to_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `permission_to_permission_to_id` int NOT NULL,
  `added_by` int NOT NULL,
  `status_status_id` int NOT NULL,
  `added_at` datetime NOT NULL,
  PRIMARY KEY (`users_has_permission_to_id`),
  KEY `fk_users_has_permission_to_permission_to1_idx` (`permission_to_permission_to_id`),
  KEY `fk_users_has_permission_to_users1_idx` (`users_user_id`),
  KEY `fk_users_has_permission_to_users2_idx` (`added_by`),
  KEY `fk_users_has_permission_to_status1_idx` (`status_status_id`),
  CONSTRAINT `fk_users_has_permission_to_permission_to1` FOREIGN KEY (`permission_to_permission_to_id`) REFERENCES `permission_to` (`permission_to_id`),
  CONSTRAINT `fk_users_has_permission_to_status1` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_users_has_permission_to_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_users_has_permission_to_users2` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.users_has_permission_to_other: ~16 rows (approximately)
INSERT INTO `users_has_permission_to_other` (`users_has_permission_to_id`, `users_user_id`, `permission_to_permission_to_id`, `added_by`, `status_status_id`, `added_at`) VALUES
	(1, 7, 44, 1, 1, '2025-07-28 11:06:24'),
	(2, 8, 44, 1, 2, '2025-07-30 20:38:07'),
	(3, 8, 49, 1, 2, '2025-07-30 20:38:07'),
	(4, 8, 46, 1, 2, '2025-07-30 20:38:07'),
	(5, 6, 52, 1, 2, '2025-08-21 21:42:16'),
	(6, 6, 54, 1, 2, '2025-08-21 21:42:16'),
	(7, 9, 92, 1, 1, '2025-09-19 10:09:50'),
	(20, 9, 44, 3, 1, '2025-11-03 19:26:44'),
	(21, 9, 45, 3, 1, '2025-11-03 19:26:44'),
	(22, 9, 46, 3, 1, '2025-11-03 19:26:44'),
	(23, 9, 60, 3, 1, '2025-11-03 19:55:49'),
	(24, 9, 61, 3, 1, '2025-11-03 19:55:49'),
	(25, 9, 62, 3, 1, '2025-11-03 19:55:49'),
	(26, 9, 94, 3, 1, '2025-11-04 01:13:02'),
	(27, 6, 51, 3, 2, '2025-11-05 00:18:43'),
	(28, 6, 53, 3, 2, '2025-11-05 00:18:43');

-- Dumping structure for table lms_enviiz_latest_no_data.users_has_permission_to_programmes
CREATE TABLE IF NOT EXISTS `users_has_permission_to_programmes` (
  `users_has_permission_to_programmes_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `permission_to_permission_to_id` int NOT NULL,
  `programmes_programme_id` int NOT NULL,
  `status_status_id` int NOT NULL,
  `added_by` int NOT NULL,
  `added_at` datetime NOT NULL,
  PRIMARY KEY (`users_has_permission_to_programmes_id`),
  KEY `fk_users_has_permission_to1_permission_to2_idx` (`permission_to_permission_to_id`),
  KEY `fk_users_has_permission_to1_users3_idx` (`users_user_id`),
  KEY `fk_users_has_permission_to1_programmes1_idx` (`programmes_programme_id`),
  KEY `fk_users_has_permission_to1_status2_idx` (`status_status_id`),
  KEY `fk_users_has_permission_to1_users4_idx` (`added_by`),
  CONSTRAINT `fk_users_has_permission_to1_permission_to2` FOREIGN KEY (`permission_to_permission_to_id`) REFERENCES `permission_to` (`permission_to_id`),
  CONSTRAINT `fk_users_has_permission_to1_programmes1` FOREIGN KEY (`programmes_programme_id`) REFERENCES `programmes` (`programme_id`),
  CONSTRAINT `fk_users_has_permission_to1_status2` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_users_has_permission_to1_users3` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_users_has_permission_to1_users4` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.users_has_permission_to_programmes: ~4 rows (approximately)
INSERT INTO `users_has_permission_to_programmes` (`users_has_permission_to_programmes_id`, `users_user_id`, `permission_to_permission_to_id`, `programmes_programme_id`, `status_status_id`, `added_by`, `added_at`) VALUES
	(7, 9, 64, 1, 1, 3, '2025-11-06 00:55:02'),
	(8, 9, 65, 1, 1, 3, '2025-11-06 00:55:02'),
	(9, 9, 66, 1, 1, 3, '2025-11-06 00:55:02'),
	(10, 9, 67, 1, 1, 3, '2025-11-06 01:05:23');

-- Dumping structure for table lms_enviiz_latest_no_data.users_has_permission_to_subject
CREATE TABLE IF NOT EXISTS `users_has_permission_to_subject` (
  `users_has_permission_to_subject_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `permission_to_permission_to_id` int NOT NULL,
  `subjects_subject_id` int NOT NULL,
  `status_status_id` int NOT NULL,
  `added_by` int NOT NULL,
  `added_at` datetime NOT NULL,
  PRIMARY KEY (`users_has_permission_to_subject_id`),
  KEY `fk_users_has_permission_to1_permission_to5_idx` (`permission_to_permission_to_id`),
  KEY `fk_users_has_permission_to1_users9_idx` (`users_user_id`),
  KEY `fk_users_has_permission_to1_subjects1_idx` (`subjects_subject_id`),
  KEY `fk_users_has_permission_to1_status5_idx` (`status_status_id`),
  KEY `fk_users_has_permission_to1_users10_idx` (`added_by`),
  CONSTRAINT `fk_users_has_permission_to1_permission_to5` FOREIGN KEY (`permission_to_permission_to_id`) REFERENCES `permission_to` (`permission_to_id`),
  CONSTRAINT `fk_users_has_permission_to1_status5` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_users_has_permission_to1_subjects1` FOREIGN KEY (`subjects_subject_id`) REFERENCES `subjects` (`subject_id`),
  CONSTRAINT `fk_users_has_permission_to1_users10` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `fk_users_has_permission_to1_users9` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.users_has_permission_to_subject: ~18 rows (approximately)
INSERT INTO `users_has_permission_to_subject` (`users_has_permission_to_subject_id`, `users_user_id`, `permission_to_permission_to_id`, `subjects_subject_id`, `status_status_id`, `added_by`, `added_at`) VALUES
	(10, 9, 91, 1, 1, 3, '2025-11-03 23:42:44'),
	(11, 9, 78, 1, 1, 3, '2025-11-03 23:42:44'),
	(12, 9, 92, 1, 1, 3, '2025-11-03 23:42:44'),
	(13, 9, 95, 1, 1, 3, '2025-11-03 23:42:44'),
	(14, 9, 79, 1, 1, 3, '2025-11-03 23:42:44'),
	(15, 9, 97, 1, 1, 3, '2025-11-03 23:42:44'),
	(16, 9, 96, 1, 1, 3, '2025-11-03 23:42:44'),
	(17, 9, 93, 1, 2, 3, '2025-11-03 23:42:44'),
	(18, 9, 80, 1, 2, 3, '2025-11-03 23:42:44'),
	(19, 9, 99, 1, 2, 3, '2025-11-03 23:42:44'),
	(20, 9, 100, 1, 2, 3, '2025-11-03 23:42:44'),
	(21, 7, 78, 1, 1, 3, '2025-11-03 23:58:43'),
	(22, 7, 92, 1, 1, 3, '2025-11-03 23:58:43'),
	(23, 7, 96, 1, 1, 3, '2025-11-03 23:58:43'),
	(24, 7, 97, 1, 1, 3, '2025-11-03 23:58:43'),
	(25, 7, 98, 1, 1, 3, '2025-11-03 23:58:43'),
	(26, 7, 100, 1, 1, 3, '2025-11-03 23:58:43'),
	(27, 7, 101, 1, 1, 3, '2025-11-03 23:58:43'),
	(28, 7, 95, 1, 1, 3, '2025-11-06 01:49:20'),
	(29, 7, 79, 1, 1, 3, '2025-11-06 01:49:20');

-- Dumping structure for table lms_enviiz_latest_no_data.users_settings
CREATE TABLE IF NOT EXISTS `users_settings` (
  `users_setting_id` int NOT NULL AUTO_INCREMENT,
  `users_user_id` int NOT NULL,
  `system_languages_system_language_id` int NOT NULL,
  `system_themes_system_theme_id` int NOT NULL,
  PRIMARY KEY (`users_setting_id`),
  KEY `FK_users_settings_users` (`users_user_id`),
  KEY `FK_users_settings_system_languages` (`system_languages_system_language_id`),
  KEY `FK_users_settings_system_themes` (`system_themes_system_theme_id`),
  CONSTRAINT `FK_users_settings_system_languages` FOREIGN KEY (`system_languages_system_language_id`) REFERENCES `system_languages` (`system_language_id`),
  CONSTRAINT `FK_users_settings_system_themes` FOREIGN KEY (`system_themes_system_theme_id`) REFERENCES `system_themes` (`system_theme_id`),
  CONSTRAINT `FK_users_settings_users` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table lms_enviiz_latest_no_data.users_settings: ~0 rows (approximately)

-- Dumping structure for table lms_enviiz_latest_no_data.user_has_address
CREATE TABLE IF NOT EXISTS `user_has_address` (
  `user_has_address_id` int NOT NULL AUTO_INCREMENT,
  `address_line_1` varchar(100) NOT NULL,
  `address_line_2` varchar(100) DEFAULT NULL,
  `postal_code` varchar(5) NOT NULL,
  `city_city_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`user_has_address_id`),
  KEY `fk_user_has_address_city1_idx` (`city_city_id`),
  KEY `fk_user_has_address_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_user_has_address_city1` FOREIGN KEY (`city_city_id`) REFERENCES `city` (`city_id`),
  CONSTRAINT `fk_user_has_address_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.user_has_address: ~0 rows (approximately)
INSERT INTO `user_has_address` (`user_has_address_id`, `address_line_1`, `address_line_2`, `postal_code`, `city_city_id`, `users_user_id`) VALUES
	(1, 'No.3/126,Sasrutha Mawatha,Gangarama Road', 'Wawela,Piliyandala', '10300', 434, 3);

-- Dumping structure for table lms_enviiz_latest_no_data.user_roles
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_role_id` int NOT NULL AUTO_INCREMENT,
  `user_role` varchar(45) NOT NULL,
  PRIMARY KEY (`user_role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table lms_enviiz_latest_no_data.user_roles: ~9 rows (approximately)
INSERT INTO `user_roles` (`user_role_id`, `user_role`) VALUES
	(1, 'Developer'),
	(2, 'Super Admin'),
	(3, 'Admin'),
	(4, 'Director'),
	(5, 'Manager'),
	(6, 'Assistant Manager'),
	(7, 'Academic'),
	(8, 'Lecturer'),
	(9, 'Accountant');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
