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

-- Dumping structure for table ade-db.answers
CREATE TABLE IF NOT EXISTS `answers` (
  `answer_id` int NOT NULL AUTO_INCREMENT,
  `answer` text NOT NULL,
  `answer_order` int NOT NULL,
  `questions_question_id` int NOT NULL,
  PRIMARY KEY (`answer_id`),
  KEY `fk_answers_questions1_idx` (`questions_question_id`),
  CONSTRAINT `fk_answers_questions1` FOREIGN KEY (`questions_question_id`) REFERENCES `questions` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.answers: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignments
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.assignments: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignments_logs
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.assignments_logs: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignment_marking_log
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.assignment_marking_log: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignment_marks_status
CREATE TABLE IF NOT EXISTS `assignment_marks_status` (
  `assignment_marks_status_id` int NOT NULL AUTO_INCREMENT,
  `assignment_marks_status` varchar(45) NOT NULL,
  PRIMARY KEY (`assignment_marks_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.assignment_marks_status: ~0 rows (approximately)
INSERT INTO `assignment_marks_status` (`assignment_marks_status_id`, `assignment_marks_status`) VALUES
	(1, 'Pending'),
	(2, 'Marking Assigned'),
	(3, 'Pending Approval'),
	(4, 'Mark Released'),
	(5, 'Recorrection Applied');

-- Dumping structure for table ade-db.assignment_payments
CREATE TABLE IF NOT EXISTS `assignment_payments` (
  `assignment_payments_id` int NOT NULL AUTO_INCREMENT,
  `lecturers_lecturers_id` int NOT NULL,
  `assignments_assignment_id` int NOT NULL,
  `amount` double NOT NULL,
  `earning_status_earning_status_id` int NOT NULL,
  PRIMARY KEY (`assignment_payments_id`),
  KEY `fk_lecturers_has_assignments_assignments1_idx` (`assignments_assignment_id`),
  KEY `fk_lecturers_has_assignments_lecturers1_idx` (`lecturers_lecturers_id`),
  KEY `fk_assignment_payments_earning_status1_idx` (`earning_status_earning_status_id`),
  CONSTRAINT `fk_assignment_payments_earning_status1` FOREIGN KEY (`earning_status_earning_status_id`) REFERENCES `earning_status` (`earning_status_id`),
  CONSTRAINT `fk_lecturers_has_assignments_assignments1` FOREIGN KEY (`assignments_assignment_id`) REFERENCES `assignments` (`assignment_id`),
  CONSTRAINT `fk_lecturers_has_assignments_lecturers1` FOREIGN KEY (`lecturers_lecturers_id`) REFERENCES `lecturers` (`lecturers_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.assignment_payments: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignment_payments_logs
CREATE TABLE IF NOT EXISTS `assignment_payments_logs` (
  `assignment_payments_logs_id` int NOT NULL AUTO_INCREMENT,
  `note` varchar(45) NOT NULL,
  `date_updated` datetime NOT NULL,
  `assignment_payments_assignment_payments_id` int NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`assignment_payments_logs_id`),
  KEY `fk_assignment_payments_logs_users1_idx` (`users_user_id`),
  KEY `fk_assignment_payments_logs_lecturer_payments1_idx` (`assignment_payments_assignment_payments_id`) USING BTREE,
  CONSTRAINT `FK_assignment_payments_logs_assignments` FOREIGN KEY (`assignment_payments_assignment_payments_id`) REFERENCES `assignments` (`assignment_id`),
  CONSTRAINT `fk_assignment_payments_logs_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.assignment_payments_logs: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignment_recorrection
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.assignment_recorrection: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignment_recorrection_fee
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

-- Dumping data for table ade-db.assignment_recorrection_fee: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignment_recorrection_log
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

-- Dumping data for table ade-db.assignment_recorrection_log: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignment_recorrection_payment
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

-- Dumping data for table ade-db.assignment_recorrection_payment: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignment_recorrection_payment_log
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

-- Dumping data for table ade-db.assignment_recorrection_payment_log: ~0 rows (approximately)

-- Dumping structure for table ade-db.assignment_status
CREATE TABLE IF NOT EXISTS `assignment_status` (
  `assignment_status_id` int NOT NULL AUTO_INCREMENT,
  `assignment_status` varchar(45) NOT NULL,
  PRIMARY KEY (`assignment_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.assignment_status: ~5 rows (approximately)
INSERT INTO `assignment_status` (`assignment_status_id`, `assignment_status`) VALUES
	(1, 'Not Schedule'),
	(2, 'Schedule'),
	(3, 'Cancelled'),
	(4, 'Recorrection Enabled'),
	(5, 'Late Submission Enabled');

-- Dumping structure for table ade-db.assignment_submission
CREATE TABLE IF NOT EXISTS `assignment_submission` (
  `submission_id` int NOT NULL AUTO_INCREMENT,
  `submission` varchar(45) NOT NULL,
  PRIMARY KEY (`submission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.assignment_submission: ~3 rows (approximately)
INSERT INTO `assignment_submission` (`submission_id`, `submission`) VALUES
	(1, 'On-Time'),
	(2, 'Late'),
	(3, 'Repeat');

-- Dumping structure for table ade-db.attachments
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.attachments: ~0 rows (approximately)

-- Dumping structure for table ade-db.attendance
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.attendance: ~0 rows (approximately)

-- Dumping structure for table ade-db.attendance_status
CREATE TABLE IF NOT EXISTS `attendance_status` (
  `attendance_status_id` int NOT NULL AUTO_INCREMENT,
  `attendance_status` varchar(45) NOT NULL,
  PRIMARY KEY (`attendance_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.attendance_status: ~3 rows (approximately)
INSERT INTO `attendance_status` (`attendance_status_id`, `attendance_status`) VALUES
	(1, 'Half Verify'),
	(2, 'Present'),
	(3, 'Absent');

-- Dumping structure for table ade-db.bank
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.bank: ~0 rows (approximately)

-- Dumping structure for table ade-db.bank_account
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

-- Dumping data for table ade-db.bank_account: ~0 rows (approximately)

-- Dumping structure for table ade-db.city
CREATE TABLE IF NOT EXISTS `city` (
  `city_id` int NOT NULL AUTO_INCREMENT,
  `city` varchar(45) NOT NULL,
  `district_district_id` int NOT NULL,
  PRIMARY KEY (`city_id`),
  KEY `fk_city_district1_idx` (`district_district_id`),
  CONSTRAINT `fk_city_district1` FOREIGN KEY (`district_district_id`) REFERENCES `district` (`district_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1991 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.city: ~1,000 rows (approximately)
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
	(1000, 'Teldeniya', 11);

-- Dumping structure for table ade-db.comments
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.comments: ~0 rows (approximately)

-- Dumping structure for table ade-db.course_payments
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.course_payments: ~0 rows (approximately)

-- Dumping structure for table ade-db.course_payment_slips
CREATE TABLE IF NOT EXISTS `course_payment_slips` (
  `course_payment_slip_id` int NOT NULL AUTO_INCREMENT,
  `reference_number` varchar(45) NOT NULL,
  `slip_hash` text NOT NULL,
  `slip_name` varchar(45) NOT NULL,
  `course_payments_course_payments_id` int NOT NULL,
  PRIMARY KEY (`course_payment_slip_id`) USING BTREE,
  KEY `fk_course_payment_slips_course_payments1_idx` (`course_payments_course_payments_id`),
  CONSTRAINT `fk_course_payment_slips_course_payments1` FOREIGN KEY (`course_payments_course_payments_id`) REFERENCES `course_payments` (`course_payments_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.course_payment_slips: ~0 rows (approximately)

-- Dumping structure for table ade-db.discount_type
CREATE TABLE IF NOT EXISTS `discount_type` (
  `discount_type_id` int NOT NULL AUTO_INCREMENT,
  `discount_type` varchar(45) NOT NULL,
  PRIMARY KEY (`discount_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.discount_type: ~3 rows (approximately)
INSERT INTO `discount_type` (`discount_type_id`, `discount_type`) VALUES
	(1, 'No Discount'),
	(2, 'Fixed Amount Discount'),
	(3, 'Percentage Based Discount');

-- Dumping structure for table ade-db.district
CREATE TABLE IF NOT EXISTS `district` (
  `district_id` int NOT NULL AUTO_INCREMENT,
  `district` varchar(45) NOT NULL,
  `province_province_id` int NOT NULL,
  PRIMARY KEY (`district_id`),
  KEY `fk_district_province1_idx` (`province_province_id`),
  CONSTRAINT `fk_district_province1` FOREIGN KEY (`province_province_id`) REFERENCES `province` (`province_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.district: ~25 rows (approximately)
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

-- Dumping structure for table ade-db.earning_status
CREATE TABLE IF NOT EXISTS `earning_status` (
  `earning_status_id` int NOT NULL AUTO_INCREMENT,
  `earning_status` varchar(45) NOT NULL,
  PRIMARY KEY (`earning_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.earning_status: ~3 rows (approximately)
INSERT INTO `earning_status` (`earning_status_id`, `earning_status`) VALUES
	(1, 'Pending'),
	(2, 'Approval'),
	(3, 'Reject');

-- Dumping structure for table ade-db.essay_questions
CREATE TABLE IF NOT EXISTS `essay_questions` (
  `essay_question_id` int NOT NULL AUTO_INCREMENT,
  `essay_question` text NOT NULL,
  `online_exam_online_exam_id` int NOT NULL,
  PRIMARY KEY (`essay_question_id`),
  KEY `fk_essay_questions_online_exam1_idx` (`online_exam_online_exam_id`),
  CONSTRAINT `fk_essay_questions_online_exam1` FOREIGN KEY (`online_exam_online_exam_id`) REFERENCES `online_exam` (`online_exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.essay_questions: ~0 rows (approximately)

-- Dumping structure for table ade-db.exams
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.exams: ~0 rows (approximately)

-- Dumping structure for table ade-db.exam_payments
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

-- Dumping data for table ade-db.exam_payments: ~0 rows (approximately)

-- Dumping structure for table ade-db.exam_payments_logs
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

-- Dumping data for table ade-db.exam_payments_logs: ~0 rows (approximately)

-- Dumping structure for table ade-db.exam_status
CREATE TABLE IF NOT EXISTS `exam_status` (
  `exam_status_id` int NOT NULL AUTO_INCREMENT,
  `exam_status` varchar(45) NOT NULL,
  PRIMARY KEY (`exam_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.exam_status: ~3 rows (approximately)
INSERT INTO `exam_status` (`exam_status_id`, `exam_status`) VALUES
	(1, 'Pending'),
	(2, 'Mark submitted'),
	(3, 'Marks Released');

-- Dumping structure for table ade-db.exam_types
CREATE TABLE IF NOT EXISTS `exam_types` (
  `exam_type_id` int NOT NULL AUTO_INCREMENT,
  `exam_type` varchar(45) NOT NULL,
  PRIMARY KEY (`exam_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.exam_types: ~3 rows (approximately)
INSERT INTO `exam_types` (`exam_type_id`, `exam_type`) VALUES
	(1, 'Physical Exam'),
	(2, 'Online Exam'),
	(3, 'Quiz Exam');

-- Dumping structure for table ade-db.faculties
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.faculties: ~0 rows (approximately)

-- Dumping structure for table ade-db.faq
CREATE TABLE IF NOT EXISTS `faq` (
  `faq_id` int NOT NULL AUTO_INCREMENT,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `date_added` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  `faq_order` int NOT NULL,
  PRIMARY KEY (`faq_id`),
  KEY `fk_faq_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_faq_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.faq: ~0 rows (approximately)

-- Dumping structure for table ade-db.gender
CREATE TABLE IF NOT EXISTS `gender` (
  `gender_id` int NOT NULL AUTO_INCREMENT,
  `gender` varchar(45) NOT NULL,
  PRIMARY KEY (`gender_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.gender: ~2 rows (approximately)
INSERT INTO `gender` (`gender_id`, `gender`) VALUES
	(1, 'Male'),
	(2, 'Female');

-- Dumping structure for table ade-db.guardian_details
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.guardian_details: ~0 rows (approximately)

-- Dumping structure for table ade-db.guardian_relation
CREATE TABLE IF NOT EXISTS `guardian_relation` (
  `guardian_relation_id` int NOT NULL AUTO_INCREMENT,
  `guardian_relation` varchar(45) NOT NULL,
  PRIMARY KEY (`guardian_relation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.guardian_relation: ~9 rows (approximately)
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

-- Dumping structure for table ade-db.help_desk
CREATE TABLE IF NOT EXISTS `help_desk` (
  `help_desk` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `help_desk_category_help_desk_category_id` int NOT NULL,
  `help_desk_order` int NOT NULL,
  PRIMARY KEY (`help_desk`),
  KEY `fk_help_desk_help_desk_category1_idx` (`help_desk_category_help_desk_category_id`),
  CONSTRAINT `fk_help_desk_help_desk_category1` FOREIGN KEY (`help_desk_category_help_desk_category_id`) REFERENCES `help_desk_category` (`help_desk_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.help_desk: ~0 rows (approximately)

-- Dumping structure for table ade-db.help_desk_category
CREATE TABLE IF NOT EXISTS `help_desk_category` (
  `help_desk_category_id` int NOT NULL AUTO_INCREMENT,
  `help_desk_category` varchar(45) NOT NULL,
  PRIMARY KEY (`help_desk_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.help_desk_category: ~0 rows (approximately)

-- Dumping structure for table ade-db.help_desk_logs
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.help_desk_logs: ~0 rows (approximately)

-- Dumping structure for table ade-db.help_desk_status
CREATE TABLE IF NOT EXISTS `help_desk_status` (
  `help_desk_status_id` int NOT NULL AUTO_INCREMENT,
  `help_desk_status` varchar(45) NOT NULL,
  PRIMARY KEY (`help_desk_status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.help_desk_status: ~0 rows (approximately)

-- Dumping structure for table ade-db.intakes
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.intakes: ~0 rows (approximately)

-- Dumping structure for table ade-db.lecturers
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.lecturers: ~0 rows (approximately)

-- Dumping structure for table ade-db.lecturers_has_students_has_assignments
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.lecturers_has_students_has_assignments: ~0 rows (approximately)

-- Dumping structure for table ade-db.lecturer_payments
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.lecturer_payments: ~0 rows (approximately)

-- Dumping structure for table ade-db.lecturer_payments_logs
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.lecturer_payments_logs: ~0 rows (approximately)

-- Dumping structure for table ade-db.lecturer_payouts
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.lecturer_payouts: ~0 rows (approximately)

-- Dumping structure for table ade-db.lecture_attendnace_status
CREATE TABLE IF NOT EXISTS `lecture_attendnace_status` (
  `lecture_attendnace_status_id` int NOT NULL AUTO_INCREMENT,
  `lecture_attendnace_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`lecture_attendnace_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ade-db.lecture_attendnace_status: ~2 rows (approximately)
INSERT INTO `lecture_attendnace_status` (`lecture_attendnace_status_id`, `lecture_attendnace_status`) VALUES
	(1, 'Enable'),
	(2, 'Disable');

-- Dumping structure for table ade-db.lecture_log
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

-- Dumping data for table ade-db.lecture_log: ~0 rows (approximately)

-- Dumping structure for table ade-db.lecture_progress
CREATE TABLE IF NOT EXISTS `lecture_progress` (
  `lecture_progress_id` int NOT NULL AUTO_INCREMENT,
  `lecture_progress` varchar(45) NOT NULL,
  PRIMARY KEY (`lecture_progress_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.lecture_progress: ~0 rows (approximately)

-- Dumping structure for table ade-db.lecture_recording
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.lecture_recording: ~0 rows (approximately)

-- Dumping structure for table ade-db.lecture_type
CREATE TABLE IF NOT EXISTS `lecture_type` (
  `lecture_type_id` int NOT NULL AUTO_INCREMENT,
  `lecture_type` varchar(45) NOT NULL,
  PRIMARY KEY (`lecture_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.lecture_type: ~3 rows (approximately)
INSERT INTO `lecture_type` (`lecture_type_id`, `lecture_type`) VALUES
	(1, 'Physical'),
	(2, 'Online'),
	(3, 'Recording');

-- Dumping structure for table ade-db.levels
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.levels: ~0 rows (approximately)

-- Dumping structure for table ade-db.likes
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

-- Dumping data for table ade-db.likes: ~0 rows (approximately)

-- Dumping structure for table ade-db.marital_status
CREATE TABLE IF NOT EXISTS `marital_status` (
  `marital_status_id` int NOT NULL AUTO_INCREMENT,
  `marital_status` varchar(45) NOT NULL,
  PRIMARY KEY (`marital_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.marital_status: ~3 rows (approximately)
INSERT INTO `marital_status` (`marital_status_id`, `marital_status`) VALUES
	(1, 'Single'),
	(2, 'Married'),
	(3, 'Divorced');

-- Dumping structure for table ade-db.news
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.news: ~0 rows (approximately)

-- Dumping structure for table ade-db.news_imgs
CREATE TABLE IF NOT EXISTS `news_imgs` (
  `news_imgs_id` int NOT NULL AUTO_INCREMENT,
  `news_img` varchar(45) NOT NULL,
  `news_news_id` int NOT NULL,
  PRIMARY KEY (`news_imgs_id`),
  KEY `fk_news_imgs_news1_idx` (`news_news_id`),
  CONSTRAINT `fk_news_imgs_news1` FOREIGN KEY (`news_news_id`) REFERENCES `news` (`news_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.news_imgs: ~0 rows (approximately)

-- Dumping structure for table ade-db.online_exam
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

-- Dumping data for table ade-db.online_exam: ~0 rows (approximately)

-- Dumping structure for table ade-db.optional_details
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

-- Dumping data for table ade-db.optional_details: ~0 rows (approximately)

-- Dumping structure for table ade-db.payment_category
CREATE TABLE IF NOT EXISTS `payment_category` (
  `payment_category_id` int NOT NULL AUTO_INCREMENT,
  `payment_category` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.payment_category: ~0 rows (approximately)

-- Dumping structure for table ade-db.payment_logs
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.payment_logs: ~0 rows (approximately)

-- Dumping structure for table ade-db.payment_method
CREATE TABLE IF NOT EXISTS `payment_method` (
  `payment_method_id` int NOT NULL AUTO_INCREMENT,
  `payment_method` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_method_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.payment_method: ~2 rows (approximately)
INSERT INTO `payment_method` (`payment_method_id`, `payment_method`) VALUES
	(1, 'Bank Transfer'),
	(2, 'Card Payment');

-- Dumping structure for table ade-db.payment_plan
CREATE TABLE IF NOT EXISTS `payment_plan` (
  `payment_plan_id` int NOT NULL AUTO_INCREMENT,
  `payment_plan` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.payment_plan: ~5 rows (approximately)
INSERT INTO `payment_plan` (`payment_plan_id`, `payment_plan`) VALUES
	(1, 'Full Payment'),
	(2, 'Monthly Installments'),
	(3, 'Sem Installments'),
	(4, 'Yearly Installments'),
	(5, 'Installments');

-- Dumping structure for table ade-db.payment_plan_has_students_has_intakes
CREATE TABLE IF NOT EXISTS `payment_plan_has_students_has_intakes` (
  `payment_plan_has_students_has_intakes_id` int NOT NULL AUTO_INCREMENT,
  `payment_plan_payment_plan_id` int NOT NULL,
  `students_has_intakes_students_has_intakes_id` int NOT NULL,
  `payment_category_payment_category_id` int NOT NULL,
  `discount_type_discount_type_id` int NOT NULL,
  `amount` double NOT NULL,
  `discount` double NOT NULL,
  `discount_note` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`payment_plan_has_students_has_intakes_id`),
  KEY `fk_payment_plan_has_students_has_intakes_students_has_intak_idx` (`students_has_intakes_students_has_intakes_id`),
  KEY `fk_payment_plan_has_students_has_intakes_payment_plan1_idx` (`payment_plan_payment_plan_id`),
  KEY `fk_payment_plan_has_students_has_intakes_payment_category1_idx` (`payment_category_payment_category_id`),
  KEY `fk_payment_plan_has_students_has_intakes_discount_type1_idx` (`discount_type_discount_type_id`),
  CONSTRAINT `fk_payment_plan_has_students_has_intakes_discount_type1` FOREIGN KEY (`discount_type_discount_type_id`) REFERENCES `discount_type` (`discount_type_id`),
  CONSTRAINT `fk_payment_plan_has_students_has_intakes_payment_category1` FOREIGN KEY (`payment_category_payment_category_id`) REFERENCES `payment_category` (`payment_category_id`),
  CONSTRAINT `fk_payment_plan_has_students_has_intakes_payment_plan1` FOREIGN KEY (`payment_plan_payment_plan_id`) REFERENCES `payment_plan` (`payment_plan_id`),
  CONSTRAINT `fk_payment_plan_has_students_has_intakes_students_has_intakes1` FOREIGN KEY (`students_has_intakes_students_has_intakes_id`) REFERENCES `students_has_intakes` (`students_has_intakes_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.payment_plan_has_students_has_intakes: ~0 rows (approximately)

-- Dumping structure for table ade-db.payment_status
CREATE TABLE IF NOT EXISTS `payment_status` (
  `payment_status_id` int NOT NULL AUTO_INCREMENT,
  `payment_status` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.payment_status: ~8 rows (approximately)
INSERT INTO `payment_status` (`payment_status_id`, `payment_status`) VALUES
	(1, 'Awaiting Payment'),
	(2, 'Awaiting Approval(Aca Officer)'),
	(3, 'Approved(Ac Officer)'),
	(4, 'Rejected(Ac Officer)'),
	(5, 'Awaiting Approval(Acc)'),
	(6, 'Approval(Accountant)'),
	(7, 'Rejected(Accountant)'),
	(8, 'Payment Accepted');

-- Dumping structure for table ade-db.payment_type
CREATE TABLE IF NOT EXISTS `payment_type` (
  `payment_type_id` int NOT NULL AUTO_INCREMENT,
  `payment_type` varchar(45) NOT NULL,
  PRIMARY KEY (`payment_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.payment_type: ~2 rows (approximately)
INSERT INTO `payment_type` (`payment_type_id`, `payment_type`) VALUES
	(1, 'Registration Fee'),
	(3, 'Installment Fee');

-- Dumping structure for table ade-db.payout_data
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.payout_data: ~0 rows (approximately)

-- Dumping structure for table ade-db.payout_logs
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.payout_logs: ~0 rows (approximately)

-- Dumping structure for table ade-db.payout_status
CREATE TABLE IF NOT EXISTS `payout_status` (
  `payout_status_id` int NOT NULL AUTO_INCREMENT,
  `payout_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`payout_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ade-db.payout_status: ~7 rows (approximately)
INSERT INTO `payout_status` (`payout_status_id`, `payout_status`) VALUES
	(1, 'Awaiting Approval Manager'),
	(2, 'Approved Manager'),
	(3, 'Rejected Manager'),
	(4, 'Awaiting Approval Accountant'),
	(5, 'Approved Accountant'),
	(6, 'Rejected Accountant'),
	(7, 'Paid');

-- Dumping structure for table ade-db.payout_type
CREATE TABLE IF NOT EXISTS `payout_type` (
  `payout_type_id` int NOT NULL AUTO_INCREMENT,
  `payout_type` varchar(45) NOT NULL,
  PRIMARY KEY (`payout_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.payout_type: ~3 rows (approximately)
INSERT INTO `payout_type` (`payout_type_id`, `payout_type`) VALUES
	(1, 'Lecture Payments'),
	(2, 'Assignment Payments'),
	(3, 'Exam Payments');

-- Dumping structure for table ade-db.permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `permission` varchar(45) NOT NULL,
  PRIMARY KEY (`permission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.permissions: ~23 rows (approximately)
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
	(23, 'Approve Reset Requests');

-- Dumping structure for table ade-db.permission_to
CREATE TABLE IF NOT EXISTS `permission_to` (
  `permission_to_id` int NOT NULL AUTO_INCREMENT,
  `permissions_permission_id` int NOT NULL,
  `permission_type_permission_type_id` int NOT NULL,
  PRIMARY KEY (`permission_to_id`),
  KEY `fk_permission_to_permissions1_idx` (`permissions_permission_id`),
  KEY `fk_permission_to_permission_type1_idx` (`permission_type_permission_type_id`),
  CONSTRAINT `fk_permission_to_permission_type1` FOREIGN KEY (`permission_type_permission_type_id`) REFERENCES `permission_type` (`permission_type_id`),
  CONSTRAINT `fk_permission_to_permissions1` FOREIGN KEY (`permissions_permission_id`) REFERENCES `permissions` (`permission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.permission_to: ~96 rows (approximately)
INSERT INTO `permission_to` (`permission_to_id`, `permissions_permission_id`, `permission_type_permission_type_id`) VALUES
	(1, 1, 1),
	(2, 2, 1),
	(3, 3, 1),
	(4, 4, 1),
	(5, 5, 1),
	(6, 6, 1),
	(7, 10, 1),
	(8, 11, 1),
	(9, 1, 2),
	(10, 2, 2),
	(11, 3, 2),
	(12, 4, 2),
	(13, 7, 2),
	(14, 8, 2),
	(15, 10, 2),
	(16, 11, 2),
	(17, 1, 3),
	(18, 2, 3),
	(19, 4, 3),
	(20, 10, 3),
	(21, 11, 3),
	(22, 1, 4),
	(23, 2, 4),
	(24, 4, 4),
	(25, 10, 4),
	(26, 11, 4),
	(27, 1, 5),
	(28, 2, 5),
	(29, 4, 5),
	(30, 10, 5),
	(31, 11, 5),
	(32, 1, 6),
	(33, 2, 6),
	(34, 4, 6),
	(35, 10, 6),
	(36, 11, 6),
	(37, 1, 7),
	(38, 2, 7),
	(39, 4, 7),
	(40, 10, 7),
	(41, 11, 7),
	(42, 1, 8),
	(43, 2, 8),
	(44, 3, 8),
	(45, 4, 8),
	(46, 9, 8),
	(47, 10, 8),
	(48, 1, 9),
	(49, 2, 9),
	(50, 4, 9),
	(51, 10, 9),
	(52, 1, 10),
	(53, 12, 10),
	(54, 13, 10),
	(55, 14, 10),
	(56, 15, 10),
	(57, 11, 10),
	(58, 1, 11),
	(59, 2, 11),
	(60, 10, 11),
	(61, 11, 11),
	(62, 1, 12),
	(63, 2, 12),
	(64, 10, 12),
	(65, 11, 12),
	(66, 1, 13),
	(67, 2, 13),
	(68, 10, 13),
	(69, 11, 13),
	(70, 1, 14),
	(71, 2, 14),
	(72, 4, 14),
	(73, 10, 14),
	(74, 11, 14),
	(75, 1, 15),
	(76, 2, 15),
	(77, 10, 15),
	(78, 11, 15),
	(79, 16, 15),
	(80, 17, 15),
	(81, 20, 15),
	(82, 1, 16),
	(83, 2, 16),
	(84, 10, 16),
	(85, 11, 16),
	(86, 16, 16),
	(87, 17, 16),
	(88, 20, 16),
	(89, 1, 17),
	(90, 2, 17),
	(91, 10, 17),
	(92, 11, 17),
	(93, 23, 17),
	(94, 1, 18),
	(95, 2, 18),
	(96, 10, 18);

-- Dumping structure for table ade-db.permission_type
CREATE TABLE IF NOT EXISTS `permission_type` (
  `permission_type_id` int NOT NULL AUTO_INCREMENT,
  `permission_type` varchar(45) NOT NULL,
  PRIMARY KEY (`permission_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.permission_type: ~19 rows (approximately)
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

-- Dumping structure for table ade-db.physical_exam
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.physical_exam: ~0 rows (approximately)

-- Dumping structure for table ade-db.plagiarism_status
CREATE TABLE IF NOT EXISTS `plagiarism_status` (
  `plagiarism_status_id` int NOT NULL AUTO_INCREMENT,
  `plagiarism_status` varchar(45) NOT NULL,
  PRIMARY KEY (`plagiarism_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.plagiarism_status: ~2 rows (approximately)
INSERT INTO `plagiarism_status` (`plagiarism_status_id`, `plagiarism_status`) VALUES
	(1, 'Required'),
	(2, 'Not Required');

-- Dumping structure for table ade-db.portal_payment
CREATE TABLE IF NOT EXISTS `portal_payment` (
  `portal_payment_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `amount` double NOT NULL,
  `discount` double NOT NULL,
  `discount_note` varchar(45) DEFAULT NULL,
  `from` datetime NOT NULL,
  `to` datetime NOT NULL,
  `date_paid` datetime NOT NULL,
  `discount_type_discount_type_id` int NOT NULL,
  `refference` varchar(45) NOT NULL,
  `receipt` varchar(45) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.portal_payment: ~0 rows (approximately)

-- Dumping structure for table ade-db.portal_payment_logs
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.portal_payment_logs: ~0 rows (approximately)

-- Dumping structure for table ade-db.portal_payment_plan
CREATE TABLE IF NOT EXISTS `portal_payment_plan` (
  `portal_payment_plan_id` int NOT NULL AUTO_INCREMENT,
  `portal_payment_plan` varchar(45) NOT NULL,
  PRIMARY KEY (`portal_payment_plan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.portal_payment_plan: ~3 rows (approximately)
INSERT INTO `portal_payment_plan` (`portal_payment_plan_id`, `portal_payment_plan`) VALUES
	(1, 'Trial'),
	(2, 'Monthly'),
	(3, 'Yearly');

-- Dumping structure for table ade-db.portal_payment_status
CREATE TABLE IF NOT EXISTS `portal_payment_status` (
  `portal_payment_status_id` int NOT NULL AUTO_INCREMENT,
  `portal_payment_status` varchar(45) NOT NULL,
  PRIMARY KEY (`portal_payment_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.portal_payment_status: ~3 rows (approximately)
INSERT INTO `portal_payment_status` (`portal_payment_status_id`, `portal_payment_status`) VALUES
	(1, 'Awaiting Approval'),
	(2, 'Accepted'),
	(3, 'Rejected');

-- Dumping structure for table ade-db.programmes
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.programmes: ~0 rows (approximately)

-- Dumping structure for table ade-db.programme_duration
CREATE TABLE IF NOT EXISTS `programme_duration` (
  `programme_duration_id` int NOT NULL AUTO_INCREMENT,
  `programme_duration` varchar(45) NOT NULL,
  `programme_duration_hours` double NOT NULL,
  `order_by` int NOT NULL,
  PRIMARY KEY (`programme_duration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.programme_duration: ~0 rows (approximately)

-- Dumping structure for table ade-db.province
CREATE TABLE IF NOT EXISTS `province` (
  `province_id` int NOT NULL AUTO_INCREMENT,
  `province` varchar(45) NOT NULL,
  PRIMARY KEY (`province_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.province: ~9 rows (approximately)
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

-- Dumping structure for table ade-db.questions
CREATE TABLE IF NOT EXISTS `questions` (
  `question_id` int NOT NULL AUTO_INCREMENT,
  `question` text NOT NULL,
  `correct_answer` int NOT NULL,
  `quiz_quiz_id` int NOT NULL,
  `questions_order` int NOT NULL,
  PRIMARY KEY (`question_id`),
  KEY `fk_questions_quiz1_idx` (`quiz_quiz_id`),
  CONSTRAINT `fk_questions_quiz1` FOREIGN KEY (`quiz_quiz_id`) REFERENCES `quiz` (`quiz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.questions: ~0 rows (approximately)

-- Dumping structure for table ade-db.quiz
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.quiz: ~0 rows (approximately)

-- Dumping structure for table ade-db.quiz_order
CREATE TABLE IF NOT EXISTS `quiz_order` (
  `quiz_order_id` int NOT NULL AUTO_INCREMENT,
  `quiz_order` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`quiz_order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ade-db.quiz_order: ~2 rows (approximately)
INSERT INTO `quiz_order` (`quiz_order_id`, `quiz_order`) VALUES
	(1, 'Random'),
	(2, 'Default');

-- Dumping structure for table ade-db.quiz_reset_status
CREATE TABLE IF NOT EXISTS `quiz_reset_status` (
  `quiz_reset_status_id` int NOT NULL AUTO_INCREMENT,
  `quiz_reset_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`quiz_reset_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ade-db.quiz_reset_status: ~2 rows (approximately)
INSERT INTO `quiz_reset_status` (`quiz_reset_status_id`, `quiz_reset_status`) VALUES
	(1, 'Pending'),
	(2, 'Approved');

-- Dumping structure for table ade-db.quiz_type
CREATE TABLE IF NOT EXISTS `quiz_type` (
  `quiz_type_id` int NOT NULL AUTO_INCREMENT,
  `quiz_type` varchar(45) NOT NULL,
  PRIMARY KEY (`quiz_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.quiz_type: ~2 rows (approximately)
INSERT INTO `quiz_type` (`quiz_type_id`, `quiz_type`) VALUES
	(1, 'One-Time'),
	(2, 'Multiple');

-- Dumping structure for table ade-db.status
CREATE TABLE IF NOT EXISTS `status` (
  `status_id` int NOT NULL AUTO_INCREMENT,
  `status` varchar(10) NOT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.status: ~0 rows (approximately)
INSERT INTO `status` (`status_id`, `status`) VALUES
	(1, 'Active'),
	(2, 'Inactive'),
	(3, 'Left');

-- Dumping structure for table ade-db.students
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
  `whatsapp` varchar(45) NOT NULL,
  `nationality` varchar(45) NOT NULL,
  `country_of_birth` varchar(45) NOT NULL,
  `country_of_residence` varchar(45) NOT NULL,
  `date_registered` datetime NOT NULL,
  `users_user_id` int DEFAULT NULL,
  `gender_gender_id` int NOT NULL,
  `first_name` varchar(45) NOT NULL,
  `last_name` varchar(45) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `verification` text,
  `reset_time` varchar(45) DEFAULT NULL,
  `status_status_id` int NOT NULL,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `mobile_UNIQUE` (`mobile`),
  UNIQUE KEY `whatsapp_UNIQUE` (`whatsapp`),
  UNIQUE KEY `nic_UNIQUE` (`nic`),
  UNIQUE KEY `email_UNIQUE` (`email`),
  KEY `fk_students_title1_idx` (`title_title_id`),
  KEY `fk_students_marital_status1_idx` (`marital_status_marital_status_id`),
  KEY `fk_students_users1_idx` (`users_user_id`),
  KEY `fk_students_gender1_idx` (`gender_gender_id`),
  KEY `fk_students_status1_idx` (`status_status_id`),
  CONSTRAINT `fk_students_gender1` FOREIGN KEY (`gender_gender_id`) REFERENCES `gender` (`gender_id`),
  CONSTRAINT `fk_students_marital_status1` FOREIGN KEY (`marital_status_marital_status_id`) REFERENCES `marital_status` (`marital_status_id`),
  CONSTRAINT `fk_students_status1` FOREIGN KEY (`status_status_id`) REFERENCES `status` (`status_id`),
  CONSTRAINT `fk_students_title1` FOREIGN KEY (`title_title_id`) REFERENCES `title` (`title_id`),
  CONSTRAINT `fk_students_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.students: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_assignments
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.students_has_assignments: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_assignments_has_plagiarism
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

-- Dumping data for table ade-db.students_has_assignments_has_plagiarism: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_essay_questions
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

-- Dumping data for table ade-db.students_has_essay_questions: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_help_desk
CREATE TABLE IF NOT EXISTS `students_has_help_desk` (
  `students_has_help_desk_id` int NOT NULL AUTO_INCREMENT,
  `students_student_id` int NOT NULL,
  `help_desk_help_desk` int NOT NULL,
  `users_user_id` int NOT NULL,
  `date_added` datetime NOT NULL,
  `help_desk_status_help_desk_status_id` int NOT NULL,
  PRIMARY KEY (`students_has_help_desk_id`),
  KEY `fk_students_has_help_desk_help_desk1_idx` (`help_desk_help_desk`),
  KEY `fk_students_has_help_desk_students1_idx` (`students_student_id`),
  KEY `fk_students_has_help_desk_users1_idx` (`users_user_id`),
  KEY `fk_students_has_help_desk_help_desk_status1_idx` (`help_desk_status_help_desk_status_id`),
  CONSTRAINT `fk_students_has_help_desk_help_desk1` FOREIGN KEY (`help_desk_help_desk`) REFERENCES `help_desk` (`help_desk`),
  CONSTRAINT `fk_students_has_help_desk_help_desk_status1` FOREIGN KEY (`help_desk_status_help_desk_status_id`) REFERENCES `help_desk_status` (`help_desk_status_id`),
  CONSTRAINT `fk_students_has_help_desk_students1` FOREIGN KEY (`students_student_id`) REFERENCES `students` (`student_id`),
  CONSTRAINT `fk_students_has_help_desk_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.students_has_help_desk: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_intakes
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.students_has_intakes: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_online_exam
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

-- Dumping data for table ade-db.students_has_online_exam: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_physical_exam
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.students_has_physical_exam: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_plagiarism
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

-- Dumping data for table ade-db.students_has_plagiarism: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_questions
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.students_has_questions: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_quiz
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.students_has_quiz: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_quiz_reset_request
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table ade-db.students_has_quiz_reset_request: ~0 rows (approximately)

-- Dumping structure for table ade-db.students_has_subjects
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.students_has_subjects: ~0 rows (approximately)

-- Dumping structure for table ade-db.student_has_address
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.student_has_address: ~0 rows (approximately)

-- Dumping structure for table ade-db.student_policy
CREATE TABLE IF NOT EXISTS `student_policy` (
  `student_policy_id` int NOT NULL AUTO_INCREMENT,
  `student_policy` text NOT NULL,
  `date_updated` datetime NOT NULL,
  `users_user_id` int NOT NULL,
  PRIMARY KEY (`student_policy_id`),
  KEY `fk_student_policy_users1_idx` (`users_user_id`),
  CONSTRAINT `fk_student_policy_users1` FOREIGN KEY (`users_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.student_policy: ~0 rows (approximately)

-- Dumping structure for table ade-db.subjects
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.subjects: ~0 rows (approximately)

-- Dumping structure for table ade-db.subject_group
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.subject_group: ~0 rows (approximately)

-- Dumping structure for table ade-db.subject_type
CREATE TABLE IF NOT EXISTS `subject_type` (
  `subject_type_id` int NOT NULL AUTO_INCREMENT,
  `subject_type` varchar(45) NOT NULL,
  PRIMARY KEY (`subject_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.subject_type: ~2 rows (approximately)
INSERT INTO `subject_type` (`subject_type_id`, `subject_type`) VALUES
	(1, 'Core Subject'),
	(2, 'Elective Subject');

-- Dumping structure for table ade-db.submission_sts
CREATE TABLE IF NOT EXISTS `submission_sts` (
  `submission_sts_id` int NOT NULL AUTO_INCREMENT,
  `submission_sts` varchar(45) NOT NULL,
  PRIMARY KEY (`submission_sts_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.submission_sts: ~2 rows (approximately)
INSERT INTO `submission_sts` (`submission_sts_id`, `submission_sts`) VALUES
	(1, 'Pending'),
	(2, 'Mark Released');

-- Dumping structure for table ade-db.time_table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.time_table: ~0 rows (approximately)

-- Dumping structure for table ade-db.time_table_status
CREATE TABLE IF NOT EXISTS `time_table_status` (
  `time_table_status_id` int NOT NULL AUTO_INCREMENT,
  `time_table_status` varchar(45) NOT NULL,
  PRIMARY KEY (`time_table_status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.time_table_status: ~4 rows (approximately)
INSERT INTO `time_table_status` (`time_table_status_id`, `time_table_status`) VALUES
	(1, 'Active'),
	(2, 'Cancelled'),
	(3, 'Postponed'),
	(4, 'Study Leave');

-- Dumping structure for table ade-db.title
CREATE TABLE IF NOT EXISTS `title` (
  `title_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(10) NOT NULL,
  PRIMARY KEY (`title_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.title: ~0 rows (approximately)
INSERT INTO `title` (`title_id`, `title`) VALUES
	(1, 'Prof'),
	(2, 'Dr'),
	(3, 'Rev'),
	(4, 'Pr'),
	(5, 'Mr'),
	(6, 'Mrs'),
	(7, 'Miss');

-- Dumping structure for table ade-db.users
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.users: ~0 rows (approximately)

-- Dumping structure for table ade-db.users_has_permission_to_faculties
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.users_has_permission_to_faculties: ~0 rows (approximately)

-- Dumping structure for table ade-db.users_has_permission_to_intakes
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.users_has_permission_to_intakes: ~0 rows (approximately)

-- Dumping structure for table ade-db.users_has_permission_to_levels
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.users_has_permission_to_levels: ~0 rows (approximately)

-- Dumping structure for table ade-db.users_has_permission_to_other
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.users_has_permission_to_other: ~0 rows (approximately)

-- Dumping structure for table ade-db.users_has_permission_to_programmes
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.users_has_permission_to_programmes: ~0 rows (approximately)

-- Dumping structure for table ade-db.users_has_permission_to_subject
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.users_has_permission_to_subject: ~0 rows (approximately)

-- Dumping structure for table ade-db.user_has_address
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.user_has_address: ~0 rows (approximately)

-- Dumping structure for table ade-db.user_roles
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_role_id` int NOT NULL AUTO_INCREMENT,
  `user_role` varchar(45) NOT NULL,
  PRIMARY KEY (`user_role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb3;

-- Dumping data for table ade-db.user_roles: ~9 rows (approximately)
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
