-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 09, 2025 at 09:48 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sksu_balancing_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `tblaccounts`
--

CREATE TABLE `tblaccounts` (
  `acc_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','vpaa','di','campus_ed','dean','program_head','scheduler','faculty') NOT NULL DEFAULT 'faculty',
  `campus_id` int(11) DEFAULT NULL,
  `college_id` int(11) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_academic_years`
--

CREATE TABLE `tbl_academic_years` (
  `ay_id` int(10) UNSIGNED NOT NULL,
  `ay` varchar(20) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_academic_years`
--

INSERT INTO `tbl_academic_years` (`ay_id`, `ay`, `status`, `date_created`) VALUES
(1, '2023-2024', 'active', '2025-12-07 18:01:23'),
(2, '2024-2025', 'active', '2025-12-07 18:01:23'),
(3, '2025-2026', 'active', '2025-12-07 18:01:23'),
(4, '2026-2027', 'active', '2025-12-07 18:01:23'),
(5, '2027-2028', 'active', '2025-12-07 18:01:23'),
(6, '2028-2029', 'active', '2025-12-07 18:01:23');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_campus`
--

CREATE TABLE `tbl_campus` (
  `campus_id` int(10) UNSIGNED NOT NULL,
  `campus_code` varchar(20) NOT NULL,
  `campus_name` varchar(200) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_campus`
--

INSERT INTO `tbl_campus` (`campus_id`, `campus_code`, `campus_name`, `status`, `date_created`) VALUES
(1, 'ISU', 'Isulan Campus', 'active', '2025-12-06 13:05:48'),
(2, 'TAC', 'Tacurong Campus', 'active', '2025-12-06 13:05:48'),
(3, 'BAG', 'Bagumbayan Campus', 'active', '2025-12-06 13:05:48'),
(4, 'KAL', 'Kalamansig Campus', 'active', '2025-12-06 13:05:48'),
(5, 'LUT', 'Lutayan Campus', 'active', '2025-12-06 13:05:48'),
(6, 'PAL', 'Palimbang Campus', 'active', '2025-12-06 13:05:48');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_class_schedule`
--

CREATE TABLE `tbl_class_schedule` (
  `schedule_id` int(11) UNSIGNED NOT NULL,
  `offering_id` int(11) UNSIGNED NOT NULL,
  `faculty_id` int(11) UNSIGNED NOT NULL,
  `room_id` int(11) UNSIGNED NOT NULL,
  `days_json` text NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_college`
--

CREATE TABLE `tbl_college` (
  `college_id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED NOT NULL,
  `college_code` varchar(20) NOT NULL,
  `college_name` varchar(200) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_college`
--

INSERT INTO `tbl_college` (`college_id`, `campus_id`, `college_code`, `college_name`, `status`, `date_created`) VALUES
(1, 1, 'CCS', 'COLLEGE OF COMPUTER STUDIES', 'active', '2025-12-06 14:02:39'),
(2, 1, 'COE', 'COLLEGE OF ENGINEERING', 'active', '2025-12-06 14:05:50'),
(3, 1, 'CIT', 'COLLEGE OF INDUSTRIAL TECHNOLOGY', 'active', '2025-12-06 14:06:07');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_college_faculty`
--

CREATE TABLE `tbl_college_faculty` (
  `college_faculty_id` int(10) UNSIGNED NOT NULL,
  `college_id` int(10) UNSIGNED NOT NULL,
  `faculty_id` int(10) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_college_faculty`
--

INSERT INTO `tbl_college_faculty` (`college_faculty_id`, `college_id`, `faculty_id`, `status`, `date_created`) VALUES
(1, 1, 1, 'active', '2025-12-07 16:54:41'),
(2, 1, 2, 'active', '2025-12-07 16:54:58'),
(3, 1, 3, 'active', '2025-12-07 16:55:16'),
(4, 1, 7, 'active', '2025-12-08 12:01:01'),
(5, 1, 6, 'active', '2025-12-08 12:04:44'),
(6, 2, 5, 'active', '2025-12-08 12:09:38'),
(7, 2, 6, 'inactive', '2025-12-08 12:09:42');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_days`
--

CREATE TABLE `tbl_days` (
  `day_id` int(11) NOT NULL,
  `day_code` varchar(5) NOT NULL,
  `day_name` varchar(20) NOT NULL,
  `ordering` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_days`
--

INSERT INTO `tbl_days` (`day_id`, `day_code`, `day_name`, `ordering`) VALUES
(1, 'M', 'Monday', 1),
(2, 'T', 'Tuesday', 2),
(3, 'W', 'Wednesday', 3),
(4, 'TH', 'Thursday', 4),
(5, 'F', 'Friday', 5),
(6, 'S', 'Saturday', 6);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_faculty`
--

CREATE TABLE `tbl_faculty` (
  `faculty_id` int(10) UNSIGNED NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `ext_name` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_faculty`
--

INSERT INTO `tbl_faculty` (`faculty_id`, `last_name`, `first_name`, `middle_name`, `ext_name`, `status`, `date_created`) VALUES
(1, 'ANTONIO', 'ELBREN', 'OSORIO', '', 'active', '2025-12-07 12:47:48'),
(2, 'RAEL', 'CYRUS', 'BANDIES', '', 'active', '2025-12-07 12:55:58'),
(3, 'RUBIN', 'CERILO', 'RELATOR', '', 'active', '2025-12-07 12:56:25'),
(5, 'SELAYRO', 'JOE', '', '', 'active', '2025-12-08 11:58:00'),
(6, 'PRADES', 'ROMA AMOR', 'CASTROMAYOR', '', 'active', '2025-12-08 11:58:19'),
(7, 'BAGUNDANG', 'ESNEHARA', '', '', 'active', '2025-12-08 11:58:35'),
(8, 'ACCAD', 'ABRAHAM', '', '', 'active', '2025-12-08 11:59:04'),
(9, 'APRESTO', 'ALEXIS', '', '', 'active', '2025-12-08 11:59:26'),
(10, 'APRESTO', 'ZIUS', '', '', 'active', '2025-12-08 11:59:38'),
(11, 'ANTONIO', 'ELBREN', '', '', 'active', '2025-12-09 09:45:27'),
(12, 'SELAYRO', 'JOE', '', '', 'active', '2025-12-09 09:45:27'),
(13, 'CASTROMAYOR', 'ROMA AMOR', '', '', 'active', '2025-12-09 09:45:27'),
(14, 'GALLO', 'ALEXES', '', '', 'active', '2025-12-09 09:45:27'),
(15, 'ESPINOSA', 'LOWELL', '', '', 'active', '2025-12-09 09:45:27'),
(16, 'BAGUNDANG', 'ESNEHARA', '', '', 'active', '2025-12-09 09:45:27'),
(17, 'AMPAS', 'KRISTINE MAE', '', '', 'active', '2025-12-09 09:45:27'),
(18, 'REMEGIO', 'FLORLYN MAE', '', '', 'active', '2025-12-09 09:45:27'),
(19, 'ARMADA', 'AMY', '', '', 'active', '2025-12-09 09:45:27'),
(20, 'DIZON', 'KYRENE', '', '', 'active', '2025-12-09 09:45:27'),
(21, 'ALI', 'JUNAI', '', '', 'active', '2025-12-09 09:45:27'),
(22, 'OMAR', 'GHEDYFUR', '', '', 'active', '2025-12-09 09:45:27'),
(23, 'SINGKAT', 'AIZA', '', '', 'active', '2025-12-09 09:45:27'),
(24, 'OBINQUE', 'JOSEPH A', '', '', 'active', '2025-12-09 09:45:27'),
(25, 'MAGLANA', 'LOVEMEE', '', '', 'active', '2025-12-09 09:45:27'),
(26, 'ABDULLA', 'PRINTIL', '', '', 'active', '2025-12-09 09:45:27'),
(27, 'CARDENUELA', 'FERDINAND', '', '', 'active', '2025-12-09 09:45:27'),
(28, 'SAGANIP', 'ANNA MAE', '', '', 'active', '2025-12-09 09:45:27'),
(29, 'CASISAN', 'HANNAH MARIE', '', '', 'active', '2025-12-09 09:45:27'),
(30, 'SUPAN', 'JOEL JR', '', '', 'active', '2025-12-09 09:45:27'),
(31, 'MORALDE', 'RUDY', '', '', 'active', '2025-12-09 09:45:27'),
(32, 'PAJELA', 'ROMEL', '', '', 'active', '2025-12-09 09:45:27'),
(33, 'BACLAAN', 'SHELDON', '', '', 'active', '2025-12-09 09:45:27'),
(34, 'ALABADO', 'JELLO', '', '', 'active', '2025-12-09 09:45:27'),
(35, 'CASIN', 'DAISY JOY', '', '', 'active', '2025-12-09 09:45:27'),
(36, 'BUGAAY', 'HAROLD', '', '', 'active', '2025-12-09 09:45:27'),
(37, 'SUGAPET', 'JEANE', '', '', 'active', '2025-12-09 09:45:27'),
(38, 'GAMALOD', 'JAMEEL', '', '', 'active', '2025-12-09 09:45:27'),
(39, 'GUTIERREZ', 'EDUARDO', '', '', 'active', '2025-12-09 09:45:27'),
(40, 'LAYON', 'REYNAN', '', '', 'active', '2025-12-09 09:45:27'),
(41, 'AMAN', 'MARIA LOURDES', '', '', 'active', '2025-12-09 09:45:27'),
(42, 'GALLO', 'RICARDO', '', '', 'active', '2025-12-09 09:45:27'),
(43, 'LIM', 'DEMIBELLE', '', '', 'active', '2025-12-09 09:45:27'),
(44, 'QUICOY', 'ROTCHEL', '', '', 'active', '2025-12-09 09:45:27'),
(45, 'CALDERON', 'STENLY', '', '', 'active', '2025-12-09 09:45:27'),
(46, 'MACARUBBO', 'SANDRA', '', '', 'active', '2025-12-09 09:45:27'),
(47, 'BANDOLA', 'JOHN NEL', '', '', 'active', '2025-12-09 09:45:27'),
(48, 'ARIO', 'JHONREY', '', '', 'active', '2025-12-09 09:45:27'),
(49, 'LAGMAY', 'ROSEMARIE', '', '', 'active', '2025-12-09 09:45:27'),
(50, 'PILA', 'ROVI JANE', '', '', 'active', '2025-12-09 09:45:27'),
(51, 'AGUSTINO', 'MICAYLAH ROSE', '', '', 'active', '2025-12-09 09:45:27'),
(52, 'LABUYO', 'DYNDA ANN', '', '', 'active', '2025-12-09 09:45:27'),
(53, 'ENORTIZA', 'MARLON', '', '', 'active', '2025-12-09 09:45:27'),
(54, 'GLARIJUELO', 'MARIAJANE', '', '', 'active', '2025-12-09 09:45:27'),
(55, 'IDIAN', 'WARLITO', '', '', 'active', '2025-12-09 09:45:27'),
(56, 'CABLE', 'KENNETH', '', '', 'active', '2025-12-09 09:45:27'),
(57, 'JALALODIN', 'OMAR', '', '', 'active', '2025-12-09 09:45:27'),
(58, 'GARAMBAS', 'GLEN', '', '', 'active', '2025-12-09 09:45:27'),
(59, 'DUCAN', 'SALUSSTRE', '', '', 'active', '2025-12-09 09:45:27'),
(60, 'SUMALDIA', 'JONATHAN', '', '', 'active', '2025-12-09 09:45:27'),
(61, 'BUNGABONG', 'ALLAN', '', '', 'active', '2025-12-09 09:45:27'),
(62, 'AMALLA', 'GERALD', '', '', 'active', '2025-12-09 09:45:27'),
(63, 'MAONGCO', 'AHMAD', '', '', 'active', '2025-12-09 09:45:27'),
(64, 'ESMAEL', 'NOEME', '', '', 'active', '2025-12-09 09:45:27'),
(65, 'SALANG', 'JOEL', '', '', 'active', '2025-12-09 09:45:27'),
(66, 'SAYO', 'FRELAN', '', '', 'active', '2025-12-09 09:45:27'),
(67, 'CACANANDO', 'SHIELA MAY', '', '', 'active', '2025-12-09 09:45:27'),
(68, 'LAMO', 'FLORDELIZA', '', '', 'active', '2025-12-09 09:45:27'),
(69, 'GARCIA', 'ABSALOM', '', '', 'active', '2025-12-09 09:45:27'),
(70, 'FORROGUEIRA', 'PIERE', '', '', 'active', '2025-12-09 09:45:27'),
(71, 'TALEON', 'MIRELLA JOY', '', '', 'active', '2025-12-09 09:45:27'),
(72, 'RAQUIDAN', 'MERA', '', '', 'active', '2025-12-09 09:45:27'),
(73, 'LUTERO', 'EDGAR', '', '', 'active', '2025-12-09 09:45:27'),
(74, 'CAHOY', 'LILIAN', '', '', 'active', '2025-12-09 09:45:27'),
(75, 'VILORIA', 'EVA', '', '', 'active', '2025-12-09 09:45:27'),
(76, 'BALAGTAS', 'GERMINA', '', '', 'active', '2025-12-09 09:45:27'),
(77, 'MACUD', 'SITTIE AISHA', '', '', 'active', '2025-12-09 09:45:27'),
(78, 'RAZA', 'JOEL', '', '', 'active', '2025-12-09 09:45:27'),
(79, 'RASONABLE', 'ROLAND', '', '', 'active', '2025-12-09 09:45:27'),
(80, 'TULING', 'ABREHEN', '', '', 'active', '2025-12-09 09:45:27'),
(81, 'DATINGIN', 'ILDEFONSO', '', '', 'active', '2025-12-09 09:45:27'),
(82, 'MAZINGAR', 'TIARE', '', '', 'active', '2025-12-09 09:45:27'),
(83, 'ESPERAGA', 'STEVE', '', '', 'active', '2025-12-09 09:45:27'),
(84, 'DELOS SANTOS', 'GLORIA', '', '', 'active', '2025-12-09 09:45:27'),
(85, 'LEYSON', 'RECHEL', '', '', 'active', '2025-12-09 09:45:27'),
(86, 'SITAR', 'MARY LOURIE', '', '', 'active', '2025-12-09 09:45:27'),
(87, 'TAPANG', 'EDEN RONALD', 'G.', '', 'active', '2025-12-09 09:45:27'),
(88, 'APILADO', 'CARLO', '', '', 'active', '2025-12-09 09:45:27'),
(89, 'BAES', 'APRIL JOY', '', '', 'active', '2025-12-09 09:45:27'),
(90, 'JAYA', 'SARAH', '', '', 'active', '2025-12-09 09:45:27'),
(91, 'ALCALA', 'HANNAH', '', '', 'active', '2025-12-09 09:45:27'),
(92, 'MILLENDEZ', 'GRACE JOY', 'T.', '', 'active', '2025-12-09 09:45:27'),
(93, 'LUMBO', 'JON MALCOLM', '', '', 'active', '2025-12-09 09:45:27'),
(94, 'LORA', 'DENICE', '', '', 'active', '2025-12-09 09:45:27'),
(95, 'SAPACAR', 'ROSALIA', '', '', 'active', '2025-12-09 09:45:27'),
(96, 'FULONG', 'AINILBABY', '', '', 'active', '2025-12-09 09:45:27'),
(97, 'BAISA', 'JERRY', '', '', 'active', '2025-12-09 09:45:27'),
(98, 'PANES', 'MARYANN', '', '', 'active', '2025-12-09 09:45:27'),
(99, 'BETING', 'SOJAY', '', '', 'active', '2025-12-09 09:45:27'),
(100, 'SINGKAT', 'JUNIFER', '', '', 'active', '2025-12-09 09:45:27'),
(101, 'CALVARIDO', 'KEMBERLY', 'H.', '', 'active', '2025-12-09 09:45:27'),
(102, 'SANTOS', 'NENITA', '', '', 'active', '2025-12-09 09:45:27'),
(103, 'PAZ', 'GYPSIE', '', '', 'active', '2025-12-09 09:45:27'),
(104, 'DAYA', 'MARY ROSELLE', '', '', 'active', '2025-12-09 09:45:27'),
(105, 'PAGUNAR', 'PIA ANGELIN', '', '', 'active', '2025-12-09 09:45:27'),
(106, 'BALDESCO', 'SOLEDAD', '', '', 'active', '2025-12-09 09:45:27'),
(107, 'CABUG', 'EDGAR', '', '', 'active', '2025-12-09 09:45:27'),
(108, 'TRABISURA', 'GLORIA', '', '', 'active', '2025-12-09 09:45:27'),
(109, 'BENDANILLO', 'LOUIS ANTONIO', '', '', 'active', '2025-12-09 09:45:27'),
(110, 'HERMOSO', 'MA. ROSABEL', '', '', 'active', '2025-12-09 09:45:27'),
(111, 'STACK', 'MARY KRIZZIA', '', '', 'active', '2025-12-09 09:45:27'),
(112, 'CANA', 'MILDRED', '', '', 'active', '2025-12-09 09:45:27'),
(113, 'IPAC', 'SERRA MAE', '', '', 'active', '2025-12-09 09:45:27'),
(114, 'CAMBE', 'SHEENA MAY', '', '', 'active', '2025-12-09 09:45:27'),
(115, 'MONTERO', 'EDSIG', '', '', 'active', '2025-12-09 09:45:27'),
(116, 'ARELLANO', 'PETER PAUL', '', '', 'active', '2025-12-09 09:45:27'),
(117, 'CARPIO', 'NOEME', '', '', 'active', '2025-12-09 09:45:27'),
(118, 'PANCHO', 'JENNIFER', '', '', 'active', '2025-12-09 09:45:27'),
(119, 'DELOS REYES', 'RYAN JOSEPH', '', '', 'active', '2025-12-09 09:45:27'),
(120, 'CATBALOGUEN', 'RECHIEL', '', '', 'active', '2025-12-09 09:45:27'),
(121, 'MONTALBAN', 'PHILIP', '', '', 'active', '2025-12-09 09:45:27'),
(122, 'DAPLIN', 'NECY', '', '', 'active', '2025-12-09 09:45:27'),
(123, 'PASCA', 'HEXEN', '', '', 'active', '2025-12-09 09:45:27'),
(124, 'MARIE', 'MA.', '', '', 'active', '2025-12-09 09:45:27'),
(125, 'ESMERIO', 'NOEL', '', '', 'active', '2025-12-09 09:45:27'),
(126, 'VARGAS', 'ANNA CLARISSE', '', '', 'active', '2025-12-09 09:45:27'),
(127, 'MISCALA', 'DEXTER', '', '', 'active', '2025-12-09 09:45:27'),
(128, 'FACUNDO', 'MARYLIN', '', '', 'active', '2025-12-09 09:45:27'),
(129, 'ANAYAN', 'MAY ANN', '', '', 'active', '2025-12-09 09:45:27'),
(130, 'PUEBLO', 'EMELYN', '', '', 'active', '2025-12-09 09:45:27'),
(131, 'ALIPALA', 'DEBBIE ROSE', '', '', 'active', '2025-12-09 09:45:27'),
(132, 'DABORDO', 'JEZEL', '', '', 'active', '2025-12-09 09:45:27'),
(133, 'RADIA', 'ANIDA MAE', '', '', 'active', '2025-12-09 09:45:27'),
(134, 'SARIPUDDIN', 'ROSE NIEL', '', '', 'active', '2025-12-09 09:45:27'),
(135, 'ABDULMANAF', 'MILDRED', '', '', 'active', '2025-12-09 09:45:27'),
(136, 'MALLAYO', 'HAIDE', '', '', 'active', '2025-12-09 09:45:27'),
(137, 'PATABALAN', 'GESELLE', '', '', 'active', '2025-12-09 09:45:27'),
(138, 'LINOGAO', 'ROSE JANE', '', '', 'active', '2025-12-09 09:45:27'),
(139, 'ESMAEL', 'SAHARA MAE', '', '', 'active', '2025-12-09 09:45:27'),
(140, 'GAMIL', 'CHERRY', '', '', 'active', '2025-12-09 09:45:27'),
(141, 'GALARIO', 'NELIA JR', '', '', 'active', '2025-12-09 09:45:27'),
(142, 'PACSON', 'MARK JOSEPH', '', '', 'active', '2025-12-09 09:45:27'),
(143, 'TUBONGBANUA', 'MARK STEVEN', '', '', 'active', '2025-12-09 09:45:27'),
(144, 'CAIN', 'RANDOLPH', '', '', 'active', '2025-12-09 09:45:27'),
(145, 'ALAO', 'MA. CHERLYN', '', '', 'active', '2025-12-09 09:45:27'),
(146, 'CABUET', 'HERIBERTO', '', '', 'active', '2025-12-09 09:45:27'),
(147, 'SARREAL', 'RALPH NOEL', '', '', 'active', '2025-12-09 09:45:27'),
(148, 'ALCON', 'LANI B', '', '', 'active', '2025-12-09 09:45:27'),
(149, 'TABANYAG', 'FLORENTINA', '', '', 'active', '2025-12-09 09:45:27'),
(150, 'SARIPUDDIN', 'ZERA SAMSULA', '', '', 'active', '2025-12-09 09:45:27'),
(151, 'LINOGAO', 'ANNABELLE', '', '', 'active', '2025-12-09 09:45:27'),
(152, 'MONTON', 'PEARL JOY', '', '', 'active', '2025-12-09 09:45:27'),
(153, 'MONTESTONE', 'REGLA', '', '', 'active', '2025-12-09 09:45:27'),
(154, 'CABAG', 'NOEMI', '', '', 'active', '2025-12-09 09:45:27'),
(155, 'ESMAEL', 'SATTIE NUR', '', '', 'active', '2025-12-09 09:45:27'),
(156, 'ESMAEL', 'HADJI ABDULMANAF', '', '', 'active', '2025-12-09 09:45:27'),
(157, 'PARAS', 'JULIE', '', '', 'active', '2025-12-09 09:45:27'),
(158, 'OREIRO', 'DESIREE', '', '', 'active', '2025-12-09 09:45:27'),
(159, 'ENOT', 'EMELIA', '', '', 'active', '2025-12-09 09:45:27'),
(160, 'OBIEDO', 'RONIE', '', '', 'active', '2025-12-09 09:45:27'),
(161, 'MONTEALTO', 'DENTY', '', '', 'active', '2025-12-09 09:45:27'),
(162, 'NARVACAN', 'SHEILLA', '', '', 'active', '2025-12-09 09:45:27'),
(163, 'TEMATEMIA', 'TESSIE', '', '', 'active', '2025-12-09 09:45:27'),
(164, 'DIMAILIG', 'JANICE', '', '', 'active', '2025-12-09 09:45:27'),
(165, 'MADOTOG', 'MT STACY MAE', '', '', 'active', '2025-12-09 09:45:27'),
(166, 'MAROHOM', 'AL NORSHIDA', '', '', 'active', '2025-12-09 09:45:27'),
(167, 'ABDULSAMAD', 'ROSELINE', '', '', 'active', '2025-12-09 09:45:27'),
(168, 'ORANCALES', 'BERNARD', '', '', 'active', '2025-12-09 09:45:27'),
(169, 'MACAS', 'JHESIRE JOY', '', '', 'active', '2025-12-09 09:45:27'),
(170, 'LIWAG', 'ROSEMARIE', '', '', 'active', '2025-12-09 09:45:27'),
(171, 'SUPOC', 'MARY ANN', '', '', 'active', '2025-12-09 09:45:27'),
(172, 'HAMISALIL', 'ROSELYN', '', '', 'active', '2025-12-09 09:45:27'),
(173, 'PIAY', 'NORLITA', '', '', 'active', '2025-12-09 09:45:27'),
(174, 'ABIANCE', 'SHERLIE ROSE', '', '', 'active', '2025-12-09 09:45:27'),
(175, 'GALLANO', 'ALVIN', '', '', 'active', '2025-12-09 09:45:27'),
(176, 'GALLANO', 'MAY', '', '', 'active', '2025-12-09 09:45:27'),
(177, 'BAGATSOL', 'REMEDIOS', '', '', 'active', '2025-12-09 09:46:42'),
(178, 'RANARA', 'JEANALYN', '', '', 'active', '2025-12-09 09:46:42'),
(179, 'WAJID', 'ROYHAINA', '', '', 'active', '2025-12-09 09:46:42'),
(180, 'MADAD', 'ANNABEL', '', '', 'active', '2025-12-09 09:46:42'),
(181, 'MALESE', 'JOSIE', '', '', 'active', '2025-12-09 09:46:42'),
(182, 'DRAIZEN', 'MARIA', '', '', 'active', '2025-12-09 09:46:42'),
(183, 'ISMAIL', 'AMYY', '', '', 'active', '2025-12-09 09:46:42'),
(184, 'DE LA CRUZ', 'ALVIN', '', '', 'active', '2025-12-09 09:46:42'),
(185, 'SOLANO', 'APRIL', '', '', 'active', '2025-12-09 09:46:42'),
(186, 'DELA CRUZ', 'APRIL GRACE', '', '', 'active', '2025-12-09 09:46:42'),
(187, 'AVILA', 'CRISTAL', '', '', 'active', '2025-12-09 09:46:42'),
(188, 'TUMAMBING', 'KAYE', '', '', 'active', '2025-12-09 09:46:42'),
(189, 'RANARA', 'MARYJOAN', '', '', 'active', '2025-12-09 09:46:42'),
(190, 'ARCAJE', 'DOROTEA', '', '', 'active', '2025-12-09 09:46:42'),
(191, 'DIONG ', 'CESAR', '', '', 'active', '2025-12-09 09:46:42'),
(192, 'UAIRE', 'ROSALEA', '', '', 'active', '2025-12-09 09:46:42'),
(193, 'ESTEBAN', 'AVIGAIL', '', '', 'active', '2025-12-09 09:46:42'),
(194, 'ELAAAA', 'JAEN', '', '', 'active', '2025-12-09 09:46:42'),
(195, 'CUDIA', 'CHERRY MAE', '', '', 'active', '2025-12-09 09:46:42'),
(196, 'SANCHEZ', 'JHONARD', '', '', 'active', '2025-12-09 09:46:42'),
(197, 'MALUCAY', 'PATRICIA COLLEEN', '', '', 'active', '2025-12-09 09:46:42'),
(198, 'DUMIH', 'JIMMY', '', '', 'active', '2025-12-09 09:46:42'),
(199, 'SABAON', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(200, 'OTMANE', 'SALMA', '', '', 'active', '2025-12-09 09:46:42'),
(201, 'ABDULLAN', 'AIHIKA', '', '', 'active', '2025-12-09 09:46:42'),
(202, 'DAYAN', 'ELJUN', '', '', 'active', '2025-12-09 09:46:42'),
(203, 'ALLARCON', 'ANA', '', '', 'active', '2025-12-09 09:46:42'),
(204, 'DT', 'CYNTHIA', '', '', 'active', '2025-12-09 09:46:42'),
(205, 'VILLAMOR', 'JERRY', '', '', 'active', '2025-12-09 09:46:42'),
(206, 'MONTECILLO', 'PABLO', '', '', 'active', '2025-12-09 09:46:42'),
(207, 'TIROL', 'GENEVIVE', '', '', 'active', '2025-12-09 09:46:42'),
(208, 'IMPERIAL', 'DINFIELD', '', '', 'active', '2025-12-09 09:46:42'),
(209, 'DANTAY', 'MA. MAY VETCHIE', '', '', 'active', '2025-12-09 09:46:42'),
(210, 'TAAS', 'ANNA MAE', '', '', 'active', '2025-12-09 09:46:42'),
(211, 'ALCANTARA', 'MYLENE', '', '', 'active', '2025-12-09 09:46:42'),
(212, 'CABALFIN', 'ANDREW', '', '', 'active', '2025-12-09 09:46:42'),
(213, 'CABAL', 'BARBARA', '', '', 'active', '2025-12-09 09:46:42'),
(214, 'MALOS', 'ANALIZA', '', '', 'active', '2025-12-09 09:46:42'),
(215, 'CANIEDO', 'MINEZA', '', '', 'active', '2025-12-09 09:46:42'),
(216, 'ULAMEN', 'ROWEN', '', '', 'active', '2025-12-09 09:46:42'),
(217, 'UBOD', 'MARVIN', '', '', 'active', '2025-12-09 09:46:42'),
(218, 'REMEGIO', 'GERLYN', '', '', 'active', '2025-12-09 09:46:42'),
(219, 'GACILAN', 'JOY', '', '', 'active', '2025-12-09 09:46:42'),
(220, 'BUGABAN', 'LORELLIE', '', '', 'active', '2025-12-09 09:46:42'),
(221, 'HUSAN', 'ELNA', '', '', 'active', '2025-12-09 09:46:42'),
(222, 'MARK', 'APHOL', '', '', 'active', '2025-12-09 09:46:42'),
(223, 'TAGOAYI', 'ZAMORA', '', '', 'active', '2025-12-09 09:46:42'),
(224, 'DACALLOS', 'LOVELY', '', '', 'active', '2025-12-09 09:46:42'),
(225, 'PACSA', 'ANNE', '', '', 'active', '2025-12-09 09:46:42'),
(226, 'ROA', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(227, 'MAGJAYO', 'APRIL JOY', '', '', 'active', '2025-12-09 09:46:42'),
(228, 'GALEON', 'GINA', '', '', 'active', '2025-12-09 09:46:42'),
(229, 'GALANCIEGO', 'LOYD', '', '', 'active', '2025-12-09 09:46:42'),
(230, 'GUNSON', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(231, 'EXANIMO', 'GRACIA', '', '', 'active', '2025-12-09 09:46:42'),
(232, 'MANONGGIT', 'JEAN', '', '', 'active', '2025-12-09 09:46:42'),
(233, 'DOMINGO', 'JASMINE', '', '', 'active', '2025-12-09 09:46:42'),
(234, 'ROFEROS', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(235, 'BANKI', 'ORESTE', '', '', 'active', '2025-12-09 09:46:42'),
(236, 'COLIPANO', 'RICA JOCELYN', '', '', 'active', '2025-12-09 09:46:42'),
(237, 'MANAGEN', 'SARAH', '', '', 'active', '2025-12-09 09:46:42'),
(238, 'BASE', 'MARK CHRISTIAN', '', '', 'active', '2025-12-09 09:46:42'),
(239, 'ABDULLAIN', 'MUH', '', '', 'active', '2025-12-09 09:46:42'),
(240, 'SUMA-AL', 'NORAYNA', '', '', 'active', '2025-12-09 09:46:42'),
(241, 'PAT', 'SHIENA', '', '', 'active', '2025-12-09 09:46:42'),
(242, 'CABIG', 'JACK', '', '', 'active', '2025-12-09 09:46:42'),
(243, 'GLAVEN', 'ALJON', '', '', 'active', '2025-12-09 09:46:42'),
(244, 'PORA', 'JOHN DENVER', '', '', 'active', '2025-12-09 09:46:42'),
(245, 'ACOBES', 'JOHN MICHAEL', '', '', 'active', '2025-12-09 09:46:42'),
(246, 'CORTEZ', 'WENDELL', '', '', 'active', '2025-12-09 09:46:42'),
(247, 'BAYHON', 'AIMEE', '', '', 'active', '2025-12-09 09:46:42'),
(248, 'LEURIO', 'ELON', '', '', 'active', '2025-12-09 09:46:42'),
(249, 'ABUCAY', 'ROCHELLE', '', '', 'active', '2025-12-09 09:46:42'),
(250, 'NAVARRO', 'AL JOHN', '', '', 'active', '2025-12-09 09:46:42'),
(251, 'SENA', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(252, 'AGUILAR', 'ABIGAIL', '', '', 'active', '2025-12-09 09:46:42'),
(253, 'FERNANDO', 'APRIL', '', '', 'active', '2025-12-09 09:46:42'),
(254, 'NIETO', 'MARY', '', '', 'active', '2025-12-09 09:46:42'),
(255, 'MULAWAN', 'DIVINA', '', '', 'active', '2025-12-09 09:46:42'),
(256, 'MARIÑAS', 'MARIELA', '', '', 'active', '2025-12-09 09:46:42'),
(257, 'AMARANTO', 'LIEZL', '', '', 'active', '2025-12-09 09:46:42'),
(258, 'ARANIL', 'ANGELICA', '', '', 'active', '2025-12-09 09:46:42'),
(259, 'DELPILAR', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(260, 'DAGONDONG', 'CRISTINE', '', '', 'active', '2025-12-09 09:46:42'),
(261, 'NUEVO', 'KATH', '', '', 'active', '2025-12-09 09:46:42'),
(262, 'LOGARTA', 'JHENNY', '', '', 'active', '2025-12-09 09:46:42'),
(263, 'MENDOZA', 'LENIELYN', '', '', 'active', '2025-12-09 09:46:42'),
(264, 'MANABE', 'ANGELICA', '', '', 'active', '2025-12-09 09:46:42'),
(265, 'ESPICUHELA', 'MARY JANE', '', '', 'active', '2025-12-09 09:46:42'),
(266, 'ALINDA', 'JENNY', '', '', 'active', '2025-12-09 09:46:42'),
(267, 'BALADAD', 'GENESIS', '', '', 'active', '2025-12-09 09:46:42'),
(268, 'FAYLON', 'CARMELA', '', '', 'active', '2025-12-09 09:46:42'),
(269, 'ABLAY', 'ANGELICA', '', '', 'active', '2025-12-09 09:46:42'),
(270, 'ARELLANO', 'MICHAEL', '', '', 'active', '2025-12-09 09:46:42'),
(271, 'BASCO', 'KRISTINE', '', '', 'active', '2025-12-09 09:46:42'),
(272, 'SAVALA', 'LEAH', '', '', 'active', '2025-12-09 09:46:42'),
(273, 'VILLANUEVA', 'KIM', '', '', 'active', '2025-12-09 09:46:42'),
(274, 'CARINO', 'ALICE', '', '', 'active', '2025-12-09 09:46:42'),
(275, 'CONDOR', 'RENA', '', '', 'active', '2025-12-09 09:46:42'),
(276, 'ALBANO', 'MARK', '', '', 'active', '2025-12-09 09:46:42'),
(277, 'PINEDA', 'CHARLENE', '', '', 'active', '2025-12-09 09:46:42'),
(278, 'CARINAL', 'MAY', '', '', 'active', '2025-12-09 09:46:42'),
(279, 'GABUTIN', 'KELLY', '', '', 'active', '2025-12-09 09:46:42'),
(280, 'JACINTO', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(281, 'ELEOTERIO', 'ELY', '', '', 'active', '2025-12-09 09:46:42'),
(282, 'AVANCENA', 'FROILAN', '', '', 'active', '2025-12-09 09:46:42'),
(283, 'GRETES', 'APRIL JOY', '', '', 'active', '2025-12-09 09:46:42'),
(284, 'REYES', 'ANGELICA', '', '', 'active', '2025-12-09 09:46:42'),
(285, 'SAN DIEGO', 'JESSIE', '', '', 'active', '2025-12-09 09:46:42'),
(286, 'DELA CRUZ', 'KAYE', '', '', 'active', '2025-12-09 09:46:42'),
(287, 'OLIVAR', 'ARNOLD', '', '', 'active', '2025-12-09 09:46:42'),
(288, 'ABELES', 'KATRINA', '', '', 'active', '2025-12-09 09:46:42'),
(289, 'BARRO', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(290, 'CALDERON', 'LYKA', '', '', 'active', '2025-12-09 09:46:42'),
(291, 'DELOSO', 'MARY ANN', '', '', 'active', '2025-12-09 09:46:42'),
(292, 'DY', 'CARLO', '', '', 'active', '2025-12-09 09:46:42'),
(293, 'MADORABLE', 'MEL', '', '', 'active', '2025-12-09 09:46:42'),
(294, 'MULI', 'CHERRY', '', '', 'active', '2025-12-09 09:46:42'),
(295, 'ORIBILLO', 'ALYSSA', '', '', 'active', '2025-12-09 09:46:42'),
(296, 'STA. ANA', 'LOUIE', '', '', 'active', '2025-12-09 09:46:42'),
(297, 'TAN', 'ROLAND', '', '', 'active', '2025-12-09 09:46:42'),
(298, 'TEMBLOR', 'MARITES', '', '', 'active', '2025-12-09 09:46:42'),
(299, 'BIRGOSO', 'ALDRIN', '', '', 'active', '2025-12-09 09:46:42'),
(300, 'CASTILLO', 'APRIL JOY', '', '', 'active', '2025-12-09 09:46:42'),
(301, 'GABAY', 'ANNA', '', '', 'active', '2025-12-09 09:46:42'),
(302, 'MONTALBO', 'RONALD', '', '', 'active', '2025-12-09 09:46:42'),
(303, 'RATILLA', 'MARY ELMA', '', '', 'active', '2025-12-09 09:46:42'),
(304, 'BAGA', 'MARY GRACE', '', '', 'active', '2025-12-09 09:46:42'),
(305, 'GACOSTA', 'HELEN', '', '', 'active', '2025-12-09 09:46:42'),
(306, 'MORENO', 'EDWIN', '', '', 'active', '2025-12-09 09:46:42'),
(307, 'DELA CRUZ', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(308, 'ESPEJO', 'LEONOR', '', '', 'active', '2025-12-09 09:46:42'),
(309, 'GALCERAN', 'JANICE', '', '', 'active', '2025-12-09 09:46:42'),
(310, 'LAGARAS', 'JOAN', '', '', 'active', '2025-12-09 09:46:42'),
(311, 'MENDOZA', 'ANNA', '', '', 'active', '2025-12-09 09:46:42'),
(312, 'OCAMPO', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(313, 'ORTOLA', 'JHEN', '', '', 'active', '2025-12-09 09:46:42'),
(314, 'POLINAR', 'REMY', '', '', 'active', '2025-12-09 09:46:42'),
(315, 'FAUNILLAN', 'KAREN', '', '', 'active', '2025-12-09 09:46:42'),
(316, 'CORDO', 'BEN', '', '', 'active', '2025-12-09 09:46:42'),
(317, 'FRANCISCO', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(318, 'MIGUEL', 'KATRINA', '', '', 'active', '2025-12-09 09:46:42'),
(319, 'SALVADOR', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(320, 'VALENCIA', 'MAY ANN', '', '', 'active', '2025-12-09 09:46:42'),
(321, 'MON', 'CECILLE', '', '', 'active', '2025-12-09 09:46:42'),
(322, 'DIMAALA', 'KIM', '', '', 'active', '2025-12-09 09:46:42'),
(323, 'MALINAO', 'JOAN', '', '', 'active', '2025-12-09 09:46:42'),
(324, 'PACLIBAR', 'MARY', '', '', 'active', '2025-12-09 09:46:42'),
(325, 'MACABANGUILAN', 'ANNA', '', '', 'active', '2025-12-09 09:46:42'),
(326, 'BALAOING', 'ANNA MARIE', '', '', 'active', '2025-12-09 09:46:42'),
(327, 'DELA ROSA', 'MARY', '', '', 'active', '2025-12-09 09:46:42'),
(328, 'GERONA', 'MARY', '', '', 'active', '2025-12-09 09:46:42'),
(329, 'RIVERA', 'CHERRY', '', '', 'active', '2025-12-09 09:46:42'),
(330, 'TORDESILLAS', 'KATH', '', '', 'active', '2025-12-09 09:46:42'),
(331, 'TORRALBA', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(332, 'VALDEZ', 'MARY', '', '', 'active', '2025-12-09 09:46:42'),
(333, 'VELASCO', 'GEMMA', '', '', 'active', '2025-12-09 09:46:42'),
(334, 'VELEZ', 'RYAN', '', '', 'active', '2025-12-09 09:46:42'),
(335, 'VILLANUEVA', 'APRIL', '', '', 'active', '2025-12-09 09:46:42'),
(336, 'CARNASO', 'MARY JOY', '', '', 'active', '2025-12-09 09:46:42'),
(337, 'DILLENA', 'ROGELIO', '', '', 'active', '2025-12-09 09:47:35'),
(338, 'BALLEZA', 'ROSE MARIE', '', '', 'active', '2025-12-09 09:47:35'),
(339, 'MAGBANUA', 'JAY', '', '', 'active', '2025-12-09 09:47:35'),
(340, 'SAMAN', 'SARAH MAE', '', '', 'active', '2025-12-09 09:47:35'),
(341, 'CATIIL', 'LESLIE', '', '', 'active', '2025-12-09 09:47:35'),
(342, 'RIVANO', 'KATHRYN', '', '', 'active', '2025-12-09 09:47:35'),
(343, 'FAMOSO', 'RACHELLE', '', '', 'active', '2025-12-09 09:47:35'),
(344, 'ALCAYAGA', 'GIRLIE', '', '', 'active', '2025-12-09 09:47:35'),
(345, 'CANIEA', 'ANA', '', '', 'active', '2025-12-09 09:47:35'),
(346, 'DAZA', 'MARIEL', '', '', 'active', '2025-12-09 09:47:35'),
(347, 'DELOS REYES', 'KARYL', '', '', 'active', '2025-12-09 09:47:35'),
(348, 'ROMARATE', 'ALIZA', '', '', 'active', '2025-12-09 09:47:35'),
(349, 'LOPEZ', 'BERNADETTE', '', '', 'active', '2025-12-09 09:47:35'),
(350, 'MANALANG', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(351, 'SALVADOR', 'MARIA', '', '', 'active', '2025-12-09 09:47:35'),
(352, 'MONTEFALCON', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(353, 'VALENCIA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(354, 'FLORO', 'TAM', '', '', 'active', '2025-12-09 09:47:35'),
(355, 'GALANG', 'ANNA MAE', '', '', 'active', '2025-12-09 09:47:35'),
(356, 'GARAY', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(357, 'MADRIGAL', 'ANNA', '', '', 'active', '2025-12-09 09:47:35'),
(358, 'CORTEL', 'MARY MAE', '', '', 'active', '2025-12-09 09:47:35'),
(359, 'YAP', 'CHARLES', '', '', 'active', '2025-12-09 09:47:35'),
(360, 'DIAZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(361, 'ADONG', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(362, 'ARANDIA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(363, 'FUENTES', 'ALLAN', '', '', 'active', '2025-12-09 09:47:35'),
(364, 'IGNACIO', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(365, 'ROXAS', 'JOHN', '', '', 'active', '2025-12-09 09:47:35'),
(366, 'SARMIENTO', 'MA', '', '', 'active', '2025-12-09 09:47:35'),
(367, 'TUNGOL', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(368, 'CALICDAN', 'ANNA', '', '', 'active', '2025-12-09 09:47:35'),
(369, 'CORPUZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(370, 'DE GUZMAN', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(371, 'DIZON', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(372, 'ESPIRITUSANTO', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(373, 'FERNANDEZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(374, 'MARQUEZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(375, 'MIRABUENOS', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(376, 'MIRANDA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(377, 'PASCUAL', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(378, 'QUINTANA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(379, 'RAMIREZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(380, 'RODRIGUEZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(381, 'SANTOS', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(382, 'SISON', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(383, 'TOBIAS', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(384, 'TORRES', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(385, 'VALENZUELA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(386, 'SAYSON', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(387, 'SALVADOR', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(388, 'SALAZAR', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(389, 'VILLANUEVA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(390, 'GARCIA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(391, 'MARTINEZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(392, 'DEL ROSARIO', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(393, 'CRUZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(394, 'OSEÑA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(395, 'PERALTA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(396, 'DE LEON', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(397, 'MANALO', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(398, 'OBRERO', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(399, 'PEREZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(400, 'ALVAREZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(401, 'CORTEZ', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(402, 'CASTRO', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(403, 'AQUINO', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(404, 'REYES', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(405, 'OLIVER', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(406, 'CUNANAN', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(407, 'GONZALES', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(408, 'MEDINA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(409, 'MERCADO', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(410, 'MENDOZA', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(411, 'DOMINGO', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(412, 'VILLAS', 'MARY', '', '', 'active', '2025-12-09 09:47:35'),
(413, 'CRUZ', 'JUAN', '', '', 'active', '2025-12-09 09:47:35'),
(414, 'SANTOS', 'PEDRO', '', '', 'active', '2025-12-09 09:47:35'),
(415, 'GONZALES', 'MARIA', '', '', 'active', '2025-12-09 09:47:35'),
(416, 'RODRIGUEZ', 'LUIS', '', '', 'active', '2025-12-09 09:47:35'),
(417, 'PEREZ', 'ANA', '', '', 'active', '2025-12-09 09:47:35'),
(418, 'FERNANDEZ', 'CARLOS', '', '', 'active', '2025-12-09 09:47:35'),
(419, 'RAMOS', 'JOSE', '', '', 'active', '2025-12-09 09:47:35'),
(420, 'VARGAS', 'JUAN', '', '', 'active', '2025-12-09 09:47:48'),
(421, 'BENITEZ', 'ROBERTO', '', '', 'active', '2025-12-09 09:47:48'),
(422, 'ORTEGA', 'PAOLO', '', '', 'active', '2025-12-09 09:47:48'),
(423, 'SALAS', 'DANIEL', '', '', 'active', '2025-12-09 09:47:48'),
(424, 'MORALES', 'ALFREDO', '', '', 'active', '2025-12-09 09:47:48'),
(425, 'NAVARRO', 'ELENA', '', '', 'active', '2025-12-09 09:47:48'),
(426, 'RIVERA', 'JULIA', '', '', 'active', '2025-12-09 09:47:48'),
(427, 'RUIZ', 'SANDRA', '', '', 'active', '2025-12-09 09:47:48'),
(428, 'SANTIAGO', 'ALICIA', '', '', 'active', '2025-12-09 09:47:48'),
(429, 'DELA CRUZ', 'ISABEL', '', '', 'active', '2025-12-09 09:47:48'),
(430, 'SUAREZ', 'RODOLFO', '', '', 'active', '2025-12-09 09:47:48'),
(431, 'CARRILLO', 'LORENZO', '', '', 'active', '2025-12-09 09:47:48'),
(432, 'MEJIA', 'NICOLAS', '', '', 'active', '2025-12-09 09:47:48'),
(433, 'MORALES', 'ALMA', '', '', 'active', '2025-12-09 09:47:48'),
(434, 'REYES', 'REMEDIOS', '', '', 'active', '2025-12-09 09:47:48'),
(435, 'ROMERO', 'ARMANDO', '', '', 'active', '2025-12-09 09:47:48'),
(436, 'SILVA', 'ERNESTO', '', '', 'active', '2025-12-09 09:47:48'),
(437, 'VILLANUEVA', 'DIEGO', '', '', 'active', '2025-12-09 09:47:48'),
(438, 'ZABALA', 'GLORIA', '', '', 'active', '2025-12-09 09:47:48'),
(439, 'SANDOVAL', 'RICARDO', '', '', 'active', '2025-12-09 09:47:48'),
(440, 'ROJAS', 'LUISA', '', '', 'active', '2025-12-09 09:47:48'),
(441, 'AGUILAR', 'PATRICIA', '', '', 'active', '2025-12-09 09:47:48'),
(442, 'VALDEZ', 'HUGO', '', '', 'active', '2025-12-09 09:47:48'),
(443, 'LOPEZ', 'SILVIA', '', '', 'active', '2025-12-09 09:47:48'),
(444, 'GOMEZ', 'PABLO', '', '', 'active', '2025-12-09 09:47:48'),
(445, 'MERCADO', 'JORGE', '', '', 'active', '2025-12-09 09:47:48'),
(446, 'MENDOZA', 'INIGO', '', '', 'active', '2025-12-09 09:47:48'),
(447, 'ROSALES', 'ESTELA', '', '', 'active', '2025-12-09 09:47:48'),
(448, 'TORRES', 'ENRIQUE', '', '', 'active', '2025-12-09 09:47:48'),
(449, 'ALONSO', 'BEATRIZ', '', '', 'active', '2025-12-09 09:47:48'),
(450, 'CRUZ', 'CAMILO', '', '', 'active', '2025-12-09 09:47:48'),
(451, 'DIAZ', 'MARISOL', '', '', 'active', '2025-12-09 09:47:48'),
(452, 'ESPINOZA', 'VICTORIA', '', '', 'active', '2025-12-09 09:47:48'),
(453, 'GARRIDO', 'FRANCISCA', '', '', 'active', '2025-12-09 09:47:48'),
(454, 'HERNANDEZ', 'RAFAEL', '', '', 'active', '2025-12-09 09:47:48'),
(455, 'IGLESIAS', 'FERNANDO', '', '', 'active', '2025-12-09 09:47:48'),
(456, 'JIMENEZ', 'ROXANNE', '', '', 'active', '2025-12-09 09:47:48'),
(457, 'LARA', 'MILAGROS', '', '', 'active', '2025-12-09 09:47:48'),
(458, 'MARTIN', 'GISELA', '', '', 'active', '2025-12-09 09:47:48'),
(459, 'NUNEZ', 'JUVENTINO', '', '', 'active', '2025-12-09 09:47:48'),
(460, 'ORTEGA', 'RICARDA', '', '', 'active', '2025-12-09 09:47:48'),
(461, 'PAREDES', 'ALFONSA', '', '', 'active', '2025-12-09 09:47:48'),
(462, 'QUINTERO', 'JACINTO', '', '', 'active', '2025-12-09 09:47:48'),
(463, 'SALGADO', 'MIRNA', '', '', 'active', '2025-12-09 09:47:48'),
(464, 'TELLO', 'LORENA', '', '', 'active', '2025-12-09 09:47:48'),
(465, 'URBINA', 'ELOY', '', '', 'active', '2025-12-09 09:47:48'),
(466, 'VALENCIA', 'YESENIA', '', '', 'active', '2025-12-09 09:47:48'),
(467, 'ZAMORA', 'GILBERTO', '', '', 'active', '2025-12-09 09:47:48'),
(468, 'MACARAEG', 'JUAN', '', '', 'active', '2025-12-09 09:47:48'),
(469, 'SISON', 'LAURA', '', '', 'active', '2025-12-09 09:47:48'),
(470, 'YAP', 'JULIO', '', '', 'active', '2025-12-09 09:47:48'),
(471, 'ONG', 'JACOB', '', '', 'active', '2025-12-09 09:47:48'),
(472, 'SANTOS', 'GABRIEL', '', '', 'active', '2025-12-09 09:47:48'),
(473, 'EUSEBIO', 'ROBERTO', '', '', 'active', '2025-12-09 09:47:48'),
(474, 'LOPEZ', 'MARIO', '', '', 'active', '2025-12-09 09:47:48'),
(475, 'CRUZ', 'ANDRES', '', '', 'active', '2025-12-09 09:47:48'),
(476, 'PASCUAL', 'MONICA', '', '', 'active', '2025-12-09 09:47:48'),
(477, 'REYES', 'CARMEN', '', '', 'active', '2025-12-09 09:47:48');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_faculty_workload`
--

CREATE TABLE `tbl_faculty_workload` (
  `workload_id` int(10) UNSIGNED NOT NULL,
  `faculty_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `year_level` tinyint(3) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `ay` varchar(20) NOT NULL,
  `semester` enum('1st','2nd','Midyear') NOT NULL DEFAULT '1st',
  `days_json` text NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `units` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `hours_lec` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `hours_lab` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `load_value` decimal(5,2) NOT NULL DEFAULT 0.00,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_program`
--

CREATE TABLE `tbl_program` (
  `program_id` int(10) UNSIGNED NOT NULL,
  `college_id` int(10) UNSIGNED NOT NULL,
  `program_code` varchar(20) NOT NULL,
  `program_name` varchar(255) NOT NULL,
  `major` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_program`
--

INSERT INTO `tbl_program` (`program_id`, `college_id`, `program_code`, `program_name`, `major`, `status`, `date_created`) VALUES
(1, 1, 'BSIT', 'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY', '', 'active', '2025-12-06 14:09:35'),
(2, 1, 'BSCS', 'BACHELOR OF SCIENCE IN COMPUTER SCIENCE', '', 'active', '2025-12-06 14:09:51'),
(3, 1, 'BSIS', 'BACHELOR OF SCIENCE IN INFORMATION SYSTEMS', '', 'active', '2025-12-06 14:10:09');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prospectus_header`
--

CREATE TABLE `tbl_prospectus_header` (
  `prospectus_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `cmo_no` varchar(150) NOT NULL,
  `effective_sy` varchar(50) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_prospectus_header`
--

INSERT INTO `tbl_prospectus_header` (`prospectus_id`, `program_id`, `cmo_no`, `effective_sy`, `remarks`, `date_created`) VALUES
(25, 1, 'CMO NNo. 25, s. 2015, CMO No. 20, s. 2013, CMO No. 39, s. 2021', '2023-2024', '', '2025-12-09 09:01:02');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prospectus_offering`
--

CREATE TABLE `tbl_prospectus_offering` (
  `offering_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `prospectus_id` int(10) UNSIGNED NOT NULL,
  `ps_id` int(10) UNSIGNED NOT NULL,
  `year_level` enum('1','2','3','4','5','6') NOT NULL,
  `semester` enum('1','2','3') NOT NULL,
  `ay` varchar(20) NOT NULL,
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','active','locked') DEFAULT 'pending',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_prospectus_offering`
--

INSERT INTO `tbl_prospectus_offering` (`offering_id`, `program_id`, `prospectus_id`, `ps_id`, `year_level`, `semester`, `ay`, `section_id`, `status`, `date_created`) VALUES
(1, 1, 25, 21, '1', '1', '2', 21, '', '2025-12-09 13:03:14'),
(2, 1, 25, 22, '1', '1', '2', 21, '', '2025-12-09 13:03:14'),
(3, 1, 25, 23, '1', '1', '2', 21, '', '2025-12-09 13:03:14'),
(4, 1, 25, 24, '1', '1', '2', 21, '', '2025-12-09 13:03:14'),
(5, 1, 25, 25, '1', '1', '2', 21, '', '2025-12-09 13:03:14'),
(6, 1, 25, 26, '1', '1', '2', 21, '', '2025-12-09 13:03:14'),
(7, 1, 25, 27, '1', '1', '2', 21, '', '2025-12-09 13:03:14'),
(8, 1, 25, 28, '1', '1', '2', 21, '', '2025-12-09 13:03:14'),
(9, 1, 25, 29, '1', '1', '2', 21, '', '2025-12-09 13:03:14'),
(10, 1, 25, 21, '1', '1', '2', 22, '', '2025-12-09 13:03:14'),
(11, 1, 25, 22, '1', '1', '2', 22, '', '2025-12-09 13:03:14'),
(12, 1, 25, 23, '1', '1', '2', 22, '', '2025-12-09 13:03:14'),
(13, 1, 25, 24, '1', '1', '2', 22, '', '2025-12-09 13:03:14'),
(14, 1, 25, 25, '1', '1', '2', 22, '', '2025-12-09 13:03:14'),
(15, 1, 25, 26, '1', '1', '2', 22, '', '2025-12-09 13:03:14'),
(16, 1, 25, 27, '1', '1', '2', 22, '', '2025-12-09 13:03:14'),
(17, 1, 25, 28, '1', '1', '2', 22, '', '2025-12-09 13:03:14'),
(18, 1, 25, 29, '1', '1', '2', 22, '', '2025-12-09 13:03:14');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prospectus_subjects`
--

CREATE TABLE `tbl_prospectus_subjects` (
  `ps_id` int(10) UNSIGNED NOT NULL,
  `pys_id` int(10) UNSIGNED NOT NULL,
  `sub_id` int(10) UNSIGNED NOT NULL,
  `lec_units` tinyint(3) UNSIGNED DEFAULT 0,
  `lab_units` tinyint(3) UNSIGNED DEFAULT 0,
  `total_units` tinyint(4) DEFAULT NULL,
  `prerequisites` varchar(255) DEFAULT NULL,
  `sort_order` int(10) UNSIGNED DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_prospectus_subjects`
--

INSERT INTO `tbl_prospectus_subjects` (`ps_id`, `pys_id`, `sub_id`, `lec_units`, `lab_units`, `total_units`, `prerequisites`, `sort_order`) VALUES
(21, 8, 79, 3, 0, 3, '', 1),
(22, 8, 80, 3, 0, 3, '', 1),
(23, 8, 81, 3, 0, 3, '', 1),
(24, 8, 90, 3, 0, 3, '', 1),
(25, 8, 24, 2, 3, 3, '', 1),
(26, 8, 25, 2, 3, 3, '', 1),
(27, 8, 130, 3, 0, 3, '', 1),
(28, 8, 169, 2, 0, 2, '', 1),
(29, 8, 164, 3, 0, 3, '', 1),
(30, 9, 83, 0, 0, 3, '', 1),
(31, 9, 85, 3, 0, 3, '', 1),
(32, 9, 98, 2, 3, 3, '', 1),
(33, 9, 26, 2, 3, 3, 'CC 112', 1),
(34, 9, 27, 2, 2, 3, 'IT 111', 1),
(35, 9, 132, 2, 3, 3, 'CC 112', 1),
(36, 9, 170, 2, 0, 2, 'PATHFIT 1', 1),
(37, 9, 167, 3, 0, 3, 'PATHFIT 1', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prospectus_year_sem`
--

CREATE TABLE `tbl_prospectus_year_sem` (
  `pys_id` int(10) UNSIGNED NOT NULL,
  `prospectus_id` int(10) UNSIGNED NOT NULL,
  `year_level` enum('1','2','3','4') NOT NULL,
  `semester` enum('1','2','3') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_prospectus_year_sem`
--

INSERT INTO `tbl_prospectus_year_sem` (`pys_id`, `prospectus_id`, `year_level`, `semester`) VALUES
(8, 25, '1', '1'),
(9, 25, '1', '2');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_rooms`
--

CREATE TABLE `tbl_rooms` (
  `room_id` int(10) UNSIGNED NOT NULL,
  `college_id` int(10) UNSIGNED NOT NULL,
  `room_code` varchar(50) NOT NULL,
  `room_name` varchar(255) DEFAULT NULL,
  `room_type` enum('lecture','laboratory','lec_lab') NOT NULL,
  `capacity` int(10) UNSIGNED DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_rooms`
--

INSERT INTO `tbl_rooms` (`room_id`, `college_id`, `room_code`, `room_name`, `room_type`, `capacity`, `status`, `date_created`) VALUES
(2, 1, 'CCS 202', 'ROOM 202', 'lecture', 51, 'active', '2025-12-07 14:30:54'),
(3, 1, 'CCS 201', 'ROOM 201', 'lecture', 0, 'active', '2025-12-07 21:06:01'),
(4, 1, 'CCS 208', 'ROOM 208', 'laboratory', 0, 'active', '2025-12-07 21:06:16'),
(5, 1, 'CCS 209', 'ROOM 209', 'laboratory', 0, 'active', '2025-12-07 21:06:23'),
(6, 1, 'CCS 301', 'ROOM 301', 'lecture', 50, 'active', '2025-12-07 21:06:39');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sections`
--

CREATE TABLE `tbl_sections` (
  `section_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `year_level` enum('1','2','3','4','5','6') NOT NULL,
  `section_name` varchar(10) NOT NULL,
  `full_section` varchar(50) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_sections`
--

INSERT INTO `tbl_sections` (`section_id`, `program_id`, `year_level`, `section_name`, `full_section`, `status`, `date_created`) VALUES
(1, 2, '1', '1A', 'BSCS 1A', 'active', '2025-12-07 15:33:30'),
(2, 2, '1', '1B', 'BSCS 1B', 'active', '2025-12-07 15:33:30'),
(4, 3, '1', '1A', 'BSIS 1A', 'active', '2025-12-07 15:38:48'),
(5, 3, '1', '1B', 'BSIS 1B', 'active', '2025-12-07 15:38:49'),
(6, 3, '1', '1C', 'BSIS 1C', 'active', '2025-12-07 15:38:49'),
(7, 3, '1', '1D', 'BSIS 1D', 'active', '2025-12-07 15:38:49'),
(8, 3, '1', '1E', 'BSIS 1E', 'active', '2025-12-07 15:38:49'),
(9, 2, '2', '2A', 'BSCS 2A', 'active', '2025-12-07 15:59:46'),
(10, 2, '2', '2B', 'BSCS 2B', 'active', '2025-12-07 15:59:46'),
(12, 2, '3', '3A', 'BSCS 3A', 'active', '2025-12-07 21:04:46'),
(13, 2, '3', '3B', 'BSCS 3B', 'active', '2025-12-07 21:04:46'),
(14, 2, '4', '4A', 'BSCS 4A', 'active', '2025-12-07 21:04:56'),
(15, 3, '2', '2A', 'BSIS 2A', 'active', '2025-12-07 21:05:20'),
(16, 3, '2', '2B', 'BSIS 2B', 'active', '2025-12-07 21:05:20'),
(17, 3, '3', '3A', 'BSIS 3A', 'active', '2025-12-07 21:05:27'),
(18, 3, '3', '3B', 'BSIS 3B', 'active', '2025-12-07 21:05:27'),
(19, 3, '4', '4A', 'BSIS 4A', 'active', '2025-12-07 21:05:33'),
(20, 3, '4', '4B', 'BSIS 4B', 'active', '2025-12-07 21:05:33'),
(21, 1, '1', '1A', 'BSIT 1A', 'active', '2025-12-09 13:02:23'),
(22, 1, '1', '1B', 'BSIT 1B', 'active', '2025-12-09 13:02:23');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_section_prospectus`
--

CREATE TABLE `tbl_section_prospectus` (
  `sp_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `p_sub_id` int(10) UNSIGNED NOT NULL,
  `ay` varchar(20) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subject_masterlist`
--

CREATE TABLE `tbl_subject_masterlist` (
  `sub_id` int(10) UNSIGNED NOT NULL,
  `sub_code` varchar(50) NOT NULL,
  `sub_description` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `date_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_subject_masterlist`
--

INSERT INTO `tbl_subject_masterlist` (`sub_id`, `sub_code`, `sub_description`, `status`, `date_created`) VALUES
(9, 'STAT 003', 'STATISTICS WITH COMPUTER APPLICATION', 'active', '2025-12-09 09:18:57'),
(10, 'AC 221', 'ALGORITHMS AND COMPLEXITY', 'active', '2025-12-09 09:18:57'),
(11, 'ACCTG 111', 'FINANCIAL ACCOUNTING AND REPORTING', 'active', '2025-12-09 09:18:57'),
(12, 'ACCTG 121', 'BASIC ACCOUNTING FOR PARTNERSHIP AND CORPORATE ENTITIES', 'active', '2025-12-09 09:18:57'),
(13, 'AE 001', 'FINANCIAL ACCOUNTING AND REPORTING', 'active', '2025-12-09 09:18:57'),
(14, 'AI 215', 'ARTIFICIAL INTELLIGENCE', 'active', '2025-12-09 09:18:57'),
(15, 'ALT 312', 'AUTOMATA THEORY AND FORMAL LANGUAGES', 'active', '2025-12-09 09:18:57'),
(16, 'APC 211', 'GRAPHICS AND MULTIMEDIA SYSTEMS', 'active', '2025-12-09 09:18:57'),
(17, 'AR 214', 'ARCHITECTURE AND ORGANIZATION', 'active', '2025-12-09 09:18:57'),
(18, 'AT 316', 'DIGITAL DESIGN', 'active', '2025-12-09 09:18:57'),
(19, 'AT 324', 'EMBEDDED SYSTEMS', 'active', '2025-12-09 09:18:57'),
(20, 'AT 327', 'MOBILE COMPUTING', 'active', '2025-12-09 09:18:57'),
(21, 'BES 321', 'TECHNOPRENEURSHIP 101', 'active', '2025-12-09 09:18:57'),
(22, 'CAP 325', 'CAPSTONE PROJECT AND RESEARCH 1', 'active', '2025-12-09 09:18:57'),
(23, 'CAP 420', 'CAPSTONE PROJECT AND RESEARCH 2', 'active', '2025-12-09 09:18:57'),
(24, 'CC 111', 'INTRODUCTION TO COMPUTING', 'active', '2025-12-09 09:18:57'),
(25, 'CC 112', 'COMPUTER PROGRAMMING 1', 'active', '2025-12-09 09:18:57'),
(26, 'CC 113', 'COMPUTER PROGRAMMING 2', 'active', '2025-12-09 09:18:57'),
(27, 'CC 114', 'DATA STRUCTURES AND ALGORITHMS', 'active', '2025-12-09 09:18:57'),
(28, 'CC 115', 'INFORMATION MANAGEMENT', 'active', '2025-12-09 09:18:57'),
(29, 'CC 116', 'APPLICATION DEVELOPMENT AND EMERGING TECHNOLOGIES', 'active', '2025-12-09 09:18:57'),
(30, 'CC 117', 'ADVANCED TECHNICAL WRITING', 'active', '2025-12-09 09:18:57'),
(31, 'CC 121', 'INTERMEDIATE PROGRAMMING', 'active', '2025-12-09 09:18:57'),
(32, 'CC 213', 'DATA STRUCTURE AND ALGORITHMS', 'active', '2025-12-09 09:18:57'),
(33, 'CES RES 400B', 'CS THESIS WRITING 2', 'active', '2025-12-09 09:18:57'),
(34, 'CPC 001', 'DATA STRUCTURES AND ALGORITHM', 'active', '2025-12-09 09:18:57'),
(35, 'CS 111', 'DISCRETE STRUCTURE 1', 'active', '2025-12-09 09:18:57'),
(36, 'CS 114', 'DISCRETE STRUCTURES 2', 'active', '2025-12-09 09:18:57'),
(37, 'CS 121', 'INTERMEDIATE PROGRAMMING', 'active', '2025-12-09 09:18:57'),
(38, 'CS 122', 'DISCRETE STRUCTURE 2', 'active', '2025-12-09 09:18:57'),
(39, 'CS 221', 'ALGORITHM AND COMPLEXITY', 'active', '2025-12-09 09:18:57'),
(40, 'CS 222', 'ARCHITECTURE AND ORGANIZATION', 'active', '2025-12-09 09:18:57'),
(41, 'CS 300', 'PRACTICUM (162 HOURS)', 'active', '2025-12-09 09:18:57'),
(42, 'CS 311', 'AUTOMATA THEORY AND FORMAL LANGUAGE', 'active', '2025-12-09 09:18:57'),
(43, 'CS 312', 'INFORMATION ASSURANCE AND SECURITY', 'active', '2025-12-09 09:18:57'),
(44, 'CS 321', 'PROGRAMMING LANGUAGES', 'active', '2025-12-09 09:18:57'),
(45, 'CS 322', 'SOFTWARE ENGINEERING 1', 'active', '2025-12-09 09:18:57'),
(46, 'CS 323', 'NETWORKS AND COMMUNICATIONS', 'active', '2025-12-09 09:18:57'),
(47, 'CS 324', 'HUMAN COMPUTER INTERACTION', 'active', '2025-12-09 09:18:57'),
(48, 'CS 400', 'CS THESIS WRITING 1', 'active', '2025-12-09 09:18:57'),
(49, 'CS 411', 'OPERATING SYSTEM', 'active', '2025-12-09 09:18:57'),
(50, 'CS 412', 'SOFTWARE ENGINEERING 2', 'active', '2025-12-09 09:18:57'),
(51, 'CS 421', 'MOBILE COMPUTING', 'active', '2025-12-09 09:18:57'),
(52, 'CS 422', 'SOCIAL ISSUES AND PROFESSIONAL PRACTICE', 'active', '2025-12-09 09:18:57'),
(53, 'CS 450', 'CS THESIS WRITING 2', 'active', '2025-12-09 09:18:57'),
(54, 'CS ELEC 311', 'SYSTEM FUNDAMENTALS', 'active', '2025-12-09 09:18:57'),
(55, 'CS ELEC 321', 'COMPUTATIONAL SCIENCE', 'active', '2025-12-09 09:18:57'),
(56, 'CS ELEC 322', 'INTELLIGENT SYSTEMS', 'active', '2025-12-09 09:18:57'),
(57, 'CS ELEC 411', 'PARALLEL AND DISTRIBUTED COMPUTING', 'active', '2025-12-09 09:18:57'),
(58, 'CS ELEC 421', 'GRAPHICS AND VISUAL COMPUTING', 'active', '2025-12-09 09:18:57'),
(59, 'ELECT 1', 'ELECTIVE 1 (CUSTOMER RELATIONSHIP MANAGEMENT)', 'active', '2025-12-09 09:20:28'),
(60, 'ELECT 2', 'ELECTIVE 2 (DATA MINING)', 'active', '2025-12-09 09:20:28'),
(61, 'ELECT 3', 'ELECTIVE 3 (SUPPLY CHAIN MANAGEMENT)', 'active', '2025-12-09 09:20:28'),
(62, 'ELECT 4', 'ELECTIVE 4 IV (BUSINESS INTELLIGENCE)', 'active', '2025-12-09 09:20:28'),
(63, 'ENG 001', 'ADVANCED TECHNICAL WRITING', 'active', '2025-12-09 09:20:28'),
(64, 'FIN 212', 'FINANCIAL MANAGEMENT', 'active', '2025-12-09 09:20:28'),
(65, 'FTS 321', 'FIELD TRIP AND SEMINARS', 'active', '2025-12-09 09:20:28'),
(66, 'GE 701', 'MATHEMATICS IN THE MODERN WORLD', 'active', '2025-12-09 09:20:28'),
(67, 'GE 702', 'PURPOSIVE COMMUNICATION', 'active', '2025-12-09 09:20:28'),
(68, 'GE 703', 'ETHICS', 'active', '2025-12-09 09:20:28'),
(70, 'GE 705', 'THE CONTEMPORARY WORLD', 'active', '2025-12-09 09:20:28'),
(71, 'GE 706', 'ART APPRECIATION', 'active', '2025-12-09 09:20:28'),
(72, 'GE 707', 'READINGS IN PHILIPPINE HISTORY', 'active', '2025-12-09 09:20:28'),
(73, 'GE 708', 'UNDERSTANDING THE SELF', 'active', '2025-12-09 09:20:28'),
(74, 'GE 709', 'THE LIFE AND WORKS OF JOSE RIZAL', 'active', '2025-12-09 09:20:28'),
(75, 'GE 711', 'CULTURES OF MINDANAO', 'active', '2025-12-09 09:20:28'),
(76, 'GE 712', 'GENDER AND SOCIETY', 'active', '2025-12-09 09:20:28'),
(77, 'GE 713', 'KONTEKSWALISADONG KOMUNIKASYON SA FILIPINO (KOMFIL)', 'active', '2025-12-09 09:20:28'),
(78, 'GE 715', 'FILIPINO AT IBA\'T IBANG DISIPLINA (FILDIS)', 'active', '2025-12-09 09:20:28'),
(79, 'GEC 001', 'PURPOSIVE COMMUNICATION / MALAYUNING KOMUNIKASYON', 'active', '2025-12-09 09:20:28'),
(80, 'GEC 002', 'MATHEMATICS IN THE MODERN WORLD / MATEMATIKA SA MAKABAGONG DAIGDIG', 'active', '2025-12-09 09:20:28'),
(81, 'GEC 003', 'SCIENCE, TECHNOLOGY AND SOCIETY / AGHAM, TEKNOLOHIYA AT LIPUNAN', 'active', '2025-12-09 09:20:28'),
(82, 'GEC 004', 'UNDERSTANDING THE SELF', 'active', '2025-12-09 09:20:28'),
(83, 'GEC 005', 'THE CONTEMPORARY WORLD / AND KASALUKUYANG DAIGDIG', 'active', '2025-12-09 09:20:28'),
(84, 'GEC 006', 'ART APPRECIATION', 'active', '2025-12-09 09:20:28'),
(85, 'GEC 007', 'READINGS IN PHILIPPINE HISTORY / MGA BABASAHIN HINGGIL SA KASAYSAYAN NG PILIPINAS', 'active', '2025-12-09 09:20:28'),
(86, 'GEC 008', 'ART APPRECIATION', 'active', '2025-12-09 09:20:28'),
(87, 'GEC 009', 'THE LIFE AND WORKS OF RIZAL', 'active', '2025-12-09 09:20:28'),
(88, 'GEE 005', 'WORLD LITERATURE', 'active', '2025-12-09 09:20:28'),
(89, 'GEE 006', 'PHILIPPINE INDIGENOUS COMMUNITIES', 'active', '2025-12-09 09:20:28'),
(90, 'GEE 007', 'GENDER AND SOCIETY', 'active', '2025-12-09 09:20:28'),
(91, 'GEE 010', 'PHILIPPINE POPULAR CULTURE', 'active', '2025-12-09 09:20:28'),
(92, 'HCI 221', 'INTRODUCTION TO HUMAN COMPUTER INTERACTION', 'active', '2025-12-09 09:20:28'),
(93, 'HCI 316', 'HUMAN COMPUTER INTERACTION', 'active', '2025-12-09 09:20:28'),
(94, 'IAS 314', 'INFORMATION ASSURANCE AND SECURITY 1', 'active', '2025-12-09 09:20:28'),
(95, 'IAS 322', 'INFORMATION ASSURANCE AND SECURITY 2', 'active', '2025-12-09 09:20:28'),
(96, 'IAS 327', 'INFORMATION ASSURANCE AND SECURITY', 'active', '2025-12-09 09:20:28'),
(97, 'IGE 001', 'PEACE AND DEVELOPMENT WITH EMPHASIS ON CULTURES OF MINDANAO', 'active', '2025-12-09 09:20:28'),
(98, 'IGE 002', 'STATISTICS WITH COMPUTER APPLICATIONS', 'active', '2025-12-09 09:20:28'),
(99, 'IM 121', 'FUNDAMENTALS OF DATABASE SYSTEMS', 'active', '2025-12-09 09:20:28'),
(100, 'IM 223', 'ADVANCED DATABASE SYSTEM', 'active', '2025-12-09 09:20:28'),
(101, 'IPT 225', 'INTEGRATIVE PROGRAMMING & TECHNOLOGIES 1', 'active', '2025-12-09 09:20:28'),
(102, 'IPT 313', 'INTEGRATIVE PROGRAMMING & TECHNOLOGIES 2', 'active', '2025-12-09 09:20:28'),
(103, 'IS 111', 'FUNDAMENTALS OF INFORMATION SYSTEMS', 'active', '2025-12-09 09:20:28'),
(104, 'IS 121', 'IT INFRASTRUCTURE AND NETWORK TECHNOLOGIES', 'active', '2025-12-09 09:20:28'),
(105, 'IS 122', 'ORGANIZATION AND MANAGEMENT CONCEPTS', 'active', '2025-12-09 09:20:28'),
(106, 'IS 211', 'ENTERPRISE ARCHITECTURE', 'active', '2025-12-09 09:20:28'),
(107, 'IS 212', 'FINANCIAL MANAGEMENT', 'active', '2025-12-09 09:20:28'),
(108, 'IS 221', 'SYSTEMS ANALYSIS AND DESIGN', 'active', '2025-12-09 09:20:28'),
(109, 'IS 222', 'ENTERPRISE ARCHITECTURE', 'active', '2025-12-09 09:20:28'),
(110, 'IS 223', 'BUSINESS PROCESS AND MANAGEMENT', 'active', '2025-12-09 09:20:28'),
(111, 'IS 300A', 'CAPSTONE PROJECT 1', 'active', '2025-12-09 09:20:28'),
(112, 'IS 300B', 'CAPSTONE PROJECT 2', 'active', '2025-12-09 09:20:28'),
(113, 'IS 311', 'EVALUATION OF BUSINESS PERFORMANCE', 'active', '2025-12-09 09:20:28'),
(114, 'IS 312', 'SYSTEM INFRASTRUCTURE AND INTEGRATION', 'active', '2025-12-09 09:20:28'),
(115, 'IS 313', 'IS PROJECT MANAGEMENT 1', 'active', '2025-12-09 09:20:28'),
(116, 'IS 314', 'PROFESSIONAL ISSUE IN INFORMATION SYSTEMS', 'active', '2025-12-09 09:20:28'),
(117, 'IS 321', 'MANAGEMENT INFORMATION SYSTEMS', 'active', '2025-12-09 09:20:28'),
(118, 'IS 322', 'CAPSTONE PROJECT 1', 'active', '2025-12-09 09:20:28'),
(119, 'IS 323', 'QUANTITATIVE METHODS', 'active', '2025-12-09 09:20:28'),
(120, 'IS 324', 'INFORMATION SYSTEM POLICY', 'active', '2025-12-09 09:20:28'),
(121, 'IS 411', 'IS STRATEGY, MANAGEMENT AND ACQUISITION', 'active', '2025-12-09 09:20:28'),
(122, 'IS 412', 'CAPSTONE PROJECT 2', 'active', '2025-12-09 09:20:28'),
(123, 'IS 421', 'PRACTICUM FOR INFORMATION SYSTEM (486 HOURS)', 'active', '2025-12-09 09:20:28'),
(124, 'IS ACC 121', 'IS ACCOUNTING FOR PARTNERSHIP AND CORPORATE ENTITIES', 'active', '2025-12-09 09:20:28'),
(125, 'IS ELEC 311', 'CUSTOMER RELATIONSHIP MANAGEMENT', 'active', '2025-12-09 09:20:28'),
(126, 'IS ELEC 321', 'DATA MINING', 'active', '2025-12-09 09:20:28'),
(127, 'IS ELEC 322', 'SUPPLY CHAIN MANAGEMENT', 'active', '2025-12-09 09:20:28'),
(128, 'IS ELEC 411', 'BUSINESS INTELLIGENCE', 'active', '2025-12-09 09:20:28'),
(129, 'IT 111', 'COMPUTER PROGRAMMING 1', 'active', '2025-12-09 09:21:39'),
(130, 'IT 111', 'DISCRETE MATHEMATICS', 'active', '2025-12-09 09:21:39'),
(131, 'IT 117', 'ADVANCED TECHNICAL WRITING', 'active', '2025-12-09 09:21:39'),
(132, 'IT 121', 'FUNDAMENTALS OF DATABASE SYSTEMS', 'active', '2025-12-09 09:21:39'),
(133, 'IT 211', 'INTRODUCTION TO HUMAN COMPUTER INTERACTION', 'active', '2025-12-09 09:21:39'),
(134, 'IT 221', 'ADVANCED DATABASE SYSTEMS', 'active', '2025-12-09 09:21:39'),
(135, 'IT 222', 'QUANTITATIVE METHODS (INCLUDING MODELING AND SIMULATION)', 'active', '2025-12-09 09:21:39'),
(136, 'IT 223', 'EVENT-DRIVEN PROGRAMMING', 'active', '2025-12-09 09:21:39'),
(137, 'IT 234', 'EMBEDDED SYSTEMS', 'active', '2025-12-09 09:21:39'),
(138, 'IT 300A', 'CAPSTONE PROJECT AND RESEARCH 1', 'active', '2025-12-09 09:21:39'),
(139, 'IT 300B', 'CAPSTONE PROJECT AND RESEARCH 2', 'active', '2025-12-09 09:21:39'),
(140, 'IT 311', 'NETWORKING 1', 'active', '2025-12-09 09:21:39'),
(141, 'IT 312', 'INFORMATION ASSURANCE AND SECURITY 1', 'active', '2025-12-09 09:21:39'),
(142, 'IT 313', 'SYSTEMS INTEGRATION AND ARCHITECTURE 1', 'active', '2025-12-09 09:21:39'),
(143, 'IT 314', 'INTEGRATIVE PROGRAMMING AND TECHNOLOGIES 1', 'active', '2025-12-09 09:21:39'),
(144, 'IT 321', 'NETWORKING 2', 'active', '2025-12-09 09:21:39'),
(145, 'IT 322', 'INFORMATION ASSURANCE AND SECURITY 2', 'active', '2025-12-09 09:21:39'),
(146, 'IT 324', 'CAPSTONE PROJECT AND RESEARCH 1', 'active', '2025-12-09 09:21:39'),
(147, 'IT 411', 'PRACTICUM (486 HOURS)', 'active', '2025-12-09 09:21:39'),
(148, 'IT 422', 'SYSTEM ADMINISTRATION AND MAINTENANCE', 'active', '2025-12-09 09:21:39'),
(149, 'IT 423', 'SOCIAL AND PROFESSIONAL ISSUES', 'active', '2025-12-09 09:21:39'),
(150, 'IT 424', 'GRAPHICS AND MULTIMEDIA SYSTEMS', 'active', '2025-12-09 09:21:39'),
(151, 'IT ELEC 211', 'OBJECT-ORIENTED PROGRAMMING', 'active', '2025-12-09 09:21:39'),
(152, 'IT ELEC 212', 'PLATFORM TECHNOLOGIES', 'active', '2025-12-09 09:21:39'),
(153, 'IT ELEC 221', 'WEB SYSTEMS AND TECHNOLOGIES', 'active', '2025-12-09 09:21:39'),
(154, 'IT ELEC 311', 'WEB SYSTEMS AND TECHNOLOGIES', 'active', '2025-12-09 09:21:39'),
(155, 'IT ELEC 321', 'INTEGRATIVE PROGRAMMING AND TECHNOLOGIES 2', 'active', '2025-12-09 09:21:39'),
(156, 'MATH 005', 'ANALYTIC GEOMETRY', 'active', '2025-12-09 09:21:39'),
(157, 'MATH 110', 'CALCULUS', 'active', '2025-12-09 09:21:39'),
(158, 'MET 311', 'NETWORKING 1', 'active', '2025-12-09 09:21:39'),
(159, 'MS 121', 'DISCRETE MATHEMATICS', 'active', '2025-12-09 09:21:39'),
(160, 'MS 312', 'QUANTITATIVE METHODS (INCLUDING MODELING AND SIMULATION)', 'active', '2025-12-09 09:21:39'),
(161, 'NC 225', 'NETWORK AND COMMUNICATION', 'active', '2025-12-09 09:21:39'),
(162, 'NET 311', 'NETWORKING 1', 'active', '2025-12-09 09:21:39'),
(163, 'NET 321', 'NETWORKING 2', 'active', '2025-12-09 09:21:39'),
(164, 'NSTP 1', 'NATIONAL SERVICE TRAINING PROGRAM 1', 'active', '2025-12-09 09:21:39'),
(165, 'NSTP 101', 'NATIONAL SERVICE TRAINING PROGRAM 1', 'active', '2025-12-09 09:21:39'),
(166, 'NSTP 102', 'NATIONAL SERVICE TRAINING PROGRAM 2', 'active', '2025-12-09 09:21:39'),
(167, 'NSTP 2', 'NATIONAL SERVICE TRAINING PROGRAM 2', 'active', '2025-12-09 09:21:39'),
(168, 'OS 223', 'OPERATING SYSTEMS', 'active', '2025-12-09 09:21:39'),
(169, 'PATHFIT 1', 'PHYSICAL ACTIVITIES TOWARD HEALTH AND FITNESS 1: MOVEMENT COMPETENCY TRAINING', 'active', '2025-12-09 09:21:39'),
(170, 'PATHFIT 2', 'PHYSICAL ACTIVITIES TOWARD HEALTH AND FITNESS 2: EXERCISE-BASED FITNESS ACTIVITIES', 'active', '2025-12-09 09:21:39'),
(171, 'PATHFIT 3', 'PHYSICAL ACTIVITIES TOWARD HEALTH AND FITNESS 3: DANCE, SPORTS, MARTIAL ARTS, GROUP EXERCISE, OUTDOOR ACTIVITIES', 'active', '2025-12-09 09:21:39'),
(172, 'PATHFIT 4', 'PHYSICAL ACTIVITIES TOWARD HEALTH AND FITNESS 4: ADVANCED ACTIVITIES', 'active', '2025-12-09 09:21:39'),
(173, 'PE 101', 'PHYSICAL FITNESS AND SELF-TESTING ACTIVITIES', 'active', '2025-12-09 09:21:39'),
(174, 'PE 102', 'RHYTHMIC ACTIVITIES', 'active', '2025-12-09 09:21:39'),
(175, 'PE 103', 'RECREATIONAL ACTIVITIES', 'active', '2025-12-09 09:21:39'),
(176, 'PE 104', 'TEAM SPORTS', 'active', '2025-12-09 09:21:39'),
(177, 'PF 211', 'OBJECT-ORIENTED PROGRAMMING', 'active', '2025-12-09 09:21:39'),
(178, 'PF 221', 'EVENT-DRIVEN PROGRAMMING', 'active', '2025-12-09 09:21:39'),
(179, 'PFC 001', 'OBJECT-ORIENTED PROGRAMMING', 'active', '2025-12-09 09:21:39'),
(180, 'PL 222', 'PROGRAMMING LANGUAGES', 'active', '2025-12-09 09:21:39'),
(181, 'PRACTI 101', 'PRACTICUM (486 HOURS)', 'active', '2025-12-09 09:21:39'),
(182, 'PT 212', 'PLATFORM TECHNOLOGIES', 'active', '2025-12-09 09:21:39'),
(183, 'RES 111', 'METHODS OF RESEARCH', 'active', '2025-12-09 09:21:39'),
(184, 'SA 421', 'SYSTEM ADMINISTRATION AND MAINTENANCE', 'active', '2025-12-09 09:21:39'),
(185, 'SE 314', 'SOFTWARE ENGINEERING 1', 'active', '2025-12-09 09:21:39'),
(186, 'SE 324', 'SOFTWARE ENGINEERING 2', 'active', '2025-12-09 09:21:39'),
(187, 'SIA 317', 'SYSTEMS INTEGRATION AND ARCHITECTURE 1', 'active', '2025-12-09 09:21:39'),
(188, 'SP 326', 'SOCIAL AND PROFESSIONAL ISSUES', 'active', '2025-12-09 09:21:39'),
(189, 'SPI 411', 'SOCIAL ISSUES AND PROFESSIONAL PRACTICE', 'active', '2025-12-09 09:21:39'),
(190, 'WS 213', 'WEB SYSTEMS AND TECHNOLOGIES', 'active', '2025-12-09 09:21:39'),
(191, 'WS 213', 'WEB SYSTEMS AND TECHNOLOGIES', 'active', '2025-12-09 09:22:30');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_useraccount`
--

CREATE TABLE `tbl_useraccount` (
  `user_id` int(11) NOT NULL,
  `username` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','scheduler','viewer') NOT NULL DEFAULT 'scheduler',
  `college_id` int(10) UNSIGNED DEFAULT NULL,
  `date_registered` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbl_useraccount`
--

INSERT INTO `tbl_useraccount` (`user_id`, `username`, `email`, `password`, `role`, `college_id`, `date_registered`, `status`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$I.deCCm/mNjdOTf7LZEPxurC4EBprEskgTaUWBREK3jDWFrv6BrSi', 'admin', NULL, '2025-12-05 18:31:22', 'active'),
(2, 'elbren', 'elbrenantonio@sksu.edu.ph', '$2y$10$U217F/jI0oyOylpDXGz4ReobG2E4gUuEkzaRGqygYcuhnng9nPls2', 'scheduler', 1, '2025-12-05 20:14:59', 'active'),
(3, 'catajay', 'catajay@sksu.edu.ph', '$2y$10$AWDEJCWxFu3bErypb9wdOOY2z4m202RfVBtBtPOFMmm7OukMmACba', 'scheduler', 2, '2025-12-07 19:58:56', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblaccounts`
--
ALTER TABLE `tblaccounts`
  ADD PRIMARY KEY (`acc_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `tbl_academic_years`
--
ALTER TABLE `tbl_academic_years`
  ADD PRIMARY KEY (`ay_id`),
  ADD UNIQUE KEY `ay` (`ay`);

--
-- Indexes for table `tbl_campus`
--
ALTER TABLE `tbl_campus`
  ADD PRIMARY KEY (`campus_id`);

--
-- Indexes for table `tbl_class_schedule`
--
ALTER TABLE `tbl_class_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_offering` (`offering_id`),
  ADD KEY `idx_faculty` (`faculty_id`),
  ADD KEY `idx_room` (`room_id`);

--
-- Indexes for table `tbl_college`
--
ALTER TABLE `tbl_college`
  ADD PRIMARY KEY (`college_id`),
  ADD KEY `fk_college_campus` (`campus_id`);

--
-- Indexes for table `tbl_college_faculty`
--
ALTER TABLE `tbl_college_faculty`
  ADD PRIMARY KEY (`college_faculty_id`),
  ADD UNIQUE KEY `unique_college_faculty` (`college_id`,`faculty_id`);

--
-- Indexes for table `tbl_days`
--
ALTER TABLE `tbl_days`
  ADD PRIMARY KEY (`day_id`);

--
-- Indexes for table `tbl_faculty`
--
ALTER TABLE `tbl_faculty`
  ADD PRIMARY KEY (`faculty_id`);

--
-- Indexes for table `tbl_faculty_workload`
--
ALTER TABLE `tbl_faculty_workload`
  ADD PRIMARY KEY (`workload_id`),
  ADD KEY `fk_fw_faculty` (`faculty_id`),
  ADD KEY `fk_fw_program` (`program_id`),
  ADD KEY `fk_fw_section` (`section_id`),
  ADD KEY `fk_fw_subject` (`subject_id`),
  ADD KEY `fk_fw_room` (`room_id`);

--
-- Indexes for table `tbl_program`
--
ALTER TABLE `tbl_program`
  ADD PRIMARY KEY (`program_id`),
  ADD KEY `fk_program_college` (`college_id`);

--
-- Indexes for table `tbl_prospectus_header`
--
ALTER TABLE `tbl_prospectus_header`
  ADD PRIMARY KEY (`prospectus_id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `tbl_prospectus_offering`
--
ALTER TABLE `tbl_prospectus_offering`
  ADD PRIMARY KEY (`offering_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `prospectus_id` (`prospectus_id`),
  ADD KEY `ps_id` (`ps_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `tbl_prospectus_subjects`
--
ALTER TABLE `tbl_prospectus_subjects`
  ADD PRIMARY KEY (`ps_id`),
  ADD KEY `pys_id` (`pys_id`),
  ADD KEY `sub_id` (`sub_id`);

--
-- Indexes for table `tbl_prospectus_year_sem`
--
ALTER TABLE `tbl_prospectus_year_sem`
  ADD PRIMARY KEY (`pys_id`),
  ADD UNIQUE KEY `uq_prospectus_year_sem` (`prospectus_id`,`year_level`,`semester`);

--
-- Indexes for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `fk_room_college` (`college_id`);

--
-- Indexes for table `tbl_sections`
--
ALTER TABLE `tbl_sections`
  ADD PRIMARY KEY (`section_id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `tbl_section_prospectus`
--
ALTER TABLE `tbl_section_prospectus`
  ADD PRIMARY KEY (`sp_id`),
  ADD KEY `fk_sp_section` (`section_id`),
  ADD KEY `fk_sp_psub` (`p_sub_id`);

--
-- Indexes for table `tbl_subject_masterlist`
--
ALTER TABLE `tbl_subject_masterlist`
  ADD PRIMARY KEY (`sub_id`);

--
-- Indexes for table `tbl_useraccount`
--
ALTER TABLE `tbl_useraccount`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_useraccount_college` (`college_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tblaccounts`
--
ALTER TABLE `tblaccounts`
  MODIFY `acc_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_academic_years`
--
ALTER TABLE `tbl_academic_years`
  MODIFY `ay_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_campus`
--
ALTER TABLE `tbl_campus`
  MODIFY `campus_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_class_schedule`
--
ALTER TABLE `tbl_class_schedule`
  MODIFY `schedule_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_college`
--
ALTER TABLE `tbl_college`
  MODIFY `college_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_college_faculty`
--
ALTER TABLE `tbl_college_faculty`
  MODIFY `college_faculty_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_days`
--
ALTER TABLE `tbl_days`
  MODIFY `day_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_faculty`
--
ALTER TABLE `tbl_faculty`
  MODIFY `faculty_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=478;

--
-- AUTO_INCREMENT for table `tbl_faculty_workload`
--
ALTER TABLE `tbl_faculty_workload`
  MODIFY `workload_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_program`
--
ALTER TABLE `tbl_program`
  MODIFY `program_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_prospectus_header`
--
ALTER TABLE `tbl_prospectus_header`
  MODIFY `prospectus_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `tbl_prospectus_offering`
--
ALTER TABLE `tbl_prospectus_offering`
  MODIFY `offering_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `tbl_prospectus_subjects`
--
ALTER TABLE `tbl_prospectus_subjects`
  MODIFY `ps_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `tbl_prospectus_year_sem`
--
ALTER TABLE `tbl_prospectus_year_sem`
  MODIFY `pys_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  MODIFY `room_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbl_sections`
--
ALTER TABLE `tbl_sections`
  MODIFY `section_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `tbl_section_prospectus`
--
ALTER TABLE `tbl_section_prospectus`
  MODIFY `sp_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_subject_masterlist`
--
ALTER TABLE `tbl_subject_masterlist`
  MODIFY `sub_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=193;

--
-- AUTO_INCREMENT for table `tbl_useraccount`
--
ALTER TABLE `tbl_useraccount`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_college`
--
ALTER TABLE `tbl_college`
  ADD CONSTRAINT `fk_college_campus` FOREIGN KEY (`campus_id`) REFERENCES `tbl_campus` (`campus_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_faculty_workload`
--
ALTER TABLE `tbl_faculty_workload`
  ADD CONSTRAINT `fk_fw_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `tbl_faculty` (`faculty_id`),
  ADD CONSTRAINT `fk_fw_program` FOREIGN KEY (`program_id`) REFERENCES `tbl_program` (`program_id`),
  ADD CONSTRAINT `fk_fw_room` FOREIGN KEY (`room_id`) REFERENCES `tbl_rooms` (`room_id`),
  ADD CONSTRAINT `fk_fw_section` FOREIGN KEY (`section_id`) REFERENCES `tbl_sections` (`section_id`),
  ADD CONSTRAINT `fk_fw_subject` FOREIGN KEY (`subject_id`) REFERENCES `tbl_subject_masterlist` (`sub_id`);

--
-- Constraints for table `tbl_program`
--
ALTER TABLE `tbl_program`
  ADD CONSTRAINT `fk_program_college` FOREIGN KEY (`college_id`) REFERENCES `tbl_college` (`college_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_prospectus_header`
--
ALTER TABLE `tbl_prospectus_header`
  ADD CONSTRAINT `tbl_prospectus_header_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `tbl_program` (`program_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_prospectus_offering`
--
ALTER TABLE `tbl_prospectus_offering`
  ADD CONSTRAINT `tbl_prospectus_offering_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `tbl_program` (`program_id`),
  ADD CONSTRAINT `tbl_prospectus_offering_ibfk_2` FOREIGN KEY (`prospectus_id`) REFERENCES `tbl_prospectus_header` (`prospectus_id`),
  ADD CONSTRAINT `tbl_prospectus_offering_ibfk_3` FOREIGN KEY (`ps_id`) REFERENCES `tbl_prospectus_subjects` (`ps_id`),
  ADD CONSTRAINT `tbl_prospectus_offering_ibfk_4` FOREIGN KEY (`section_id`) REFERENCES `tbl_sections` (`section_id`);

--
-- Constraints for table `tbl_prospectus_subjects`
--
ALTER TABLE `tbl_prospectus_subjects`
  ADD CONSTRAINT `tbl_prospectus_subjects_ibfk_1` FOREIGN KEY (`pys_id`) REFERENCES `tbl_prospectus_year_sem` (`pys_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_prospectus_subjects_ibfk_2` FOREIGN KEY (`sub_id`) REFERENCES `tbl_subject_masterlist` (`sub_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_prospectus_year_sem`
--
ALTER TABLE `tbl_prospectus_year_sem`
  ADD CONSTRAINT `tbl_prospectus_year_sem_ibfk_1` FOREIGN KEY (`prospectus_id`) REFERENCES `tbl_prospectus_header` (`prospectus_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_rooms`
--
ALTER TABLE `tbl_rooms`
  ADD CONSTRAINT `fk_room_college` FOREIGN KEY (`college_id`) REFERENCES `tbl_college` (`college_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_sections`
--
ALTER TABLE `tbl_sections`
  ADD CONSTRAINT `tbl_sections_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `tbl_program` (`program_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_section_prospectus`
--
ALTER TABLE `tbl_section_prospectus`
  ADD CONSTRAINT `fk_sp_psub` FOREIGN KEY (`p_sub_id`) REFERENCES `tbl_prospectus_subjects` (`ps_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sp_section` FOREIGN KEY (`section_id`) REFERENCES `tbl_sections` (`section_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_useraccount`
--
ALTER TABLE `tbl_useraccount`
  ADD CONSTRAINT `fk_useraccount_college` FOREIGN KEY (`college_id`) REFERENCES `tbl_college` (`college_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
