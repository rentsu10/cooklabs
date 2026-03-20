-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 20, 2026 at 01:23 AM
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
-- Database: `cooklabs_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--

CREATE TABLE `assessments` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `attempts_allowed` int(11) DEFAULT NULL,
  `passing_score` int(11) DEFAULT 75,
  `question_count` int(11) DEFAULT 0,
  `total_points` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessments`
--

INSERT INTO `assessments` (`id`, `course_id`, `title`, `description`, `time_limit`, `attempts_allowed`, `passing_score`, `question_count`, `total_points`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 2, 'Training Regulation Post Test', '', NULL, NULL, 75, 4, 0, 1, '2026-03-12 14:01:52', '2026-03-13 07:42:10'),
(3, 1, 'aadaaaaaaaaaaaaaaa', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaa', 2, NULL, 75, 1, 0, 1, '2026-03-17 08:19:15', NULL),
(4, 3, 'asdasda', 'dasdasdasd', NULL, NULL, 75, 1, 0, 3, '2026-03-19 07:16:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_answers`
--

CREATE TABLE `assessment_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` char(1) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_answers`
--

INSERT INTO `assessment_answers` (`id`, `attempt_id`, `question_id`, `selected_option`, `is_correct`, `points_earned`, `answered_at`) VALUES
(41, 12, 8, 'C', 1, 1, '2026-03-19 07:41:16'),
(42, 12, 5, 'D', 1, 1, '2026-03-19 07:41:19'),
(43, 12, 6, 'C', 0, 0, '2026-03-19 07:41:21'),
(44, 12, 7, 'A', 1, 1, '2026-03-19 07:41:23');

-- --------------------------------------------------------

--
-- Table structure for table `assessment_attempts`
--

CREATE TABLE `assessment_attempts` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `attempt_number` int(11) DEFAULT 1,
  `score` decimal(5,2) DEFAULT 0.00,
  `total_points` int(11) DEFAULT 0,
  `earned_points` int(11) DEFAULT 0,
  `status` enum('in_progress','completed','timeout') DEFAULT 'in_progress',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `time_spent` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_attempts`
--

INSERT INTO `assessment_attempts` (`id`, `assessment_id`, `user_id`, `attempt_number`, `score`, `total_points`, `earned_points`, `status`, `started_at`, `completed_at`, `time_spent`) VALUES
(12, 2, 27, 1, 75.00, 0, 3, 'completed', '2026-03-19 07:41:08', '2026-03-19 07:41:25', 0),
(13, 2, 27, 1, 0.00, 0, NULL, 'completed', '2026-03-19 07:42:15', '2026-03-19 07:42:55', 0);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_questions`
--

CREATE TABLE `assessment_questions` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `correct_option` char(1) NOT NULL,
  `points` int(11) DEFAULT 1,
  `order_num` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_questions`
--

INSERT INTO `assessment_questions` (`id`, `assessment_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `points`, `order_num`, `created_at`) VALUES
(5, 2, 'Who is Imman', 'Emman', 'Backend', 'Bisaya', 'Regine Velasquez', 'D', 1, 1, '2026-03-13 07:42:10'),
(6, 2, 'Who is Mika', 'Akim', 'Azalea', 'Mikasaurusussy', 'All of the Above', 'D', 1, 2, '2026-03-13 07:42:10'),
(7, 2, 'Who is Marga', 'Dauzu', 'Margie', 'Blush on', 'Pushable', 'A', 1, 3, '2026-03-13 07:42:10'),
(8, 2, 'Who is Kooky', 'Scammer', 'Kookycoin', 'Momi Oni Top Fan 1000%', 'Kokey', 'C', 1, 4, '2026-03-13 07:42:10'),
(9, 3, 'adasdasd', 'asdwasdwasd', 'wasdwasdw', 'asdwasdwasdasdw', 'wasdwasdwdd', 'A', 1, 1, '2026-03-17 08:19:15'),
(10, 4, 'asdasd', 'ssssssss', 'dddddddddd', 'aaaaaaaaaa', 'ddddddddddd', 'A', 1, 1, '2026-03-19 07:16:38');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_replied` tinyint(1) DEFAULT 0,
  `admin_notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `proponent_id` int(11) NOT NULL,
  `file_pdf` varchar(255) DEFAULT NULL,
  `total_pages` int(11) DEFAULT 0 COMMENT 'Total pages in course PDF',
  `file_video` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `summary` varchar(2500) DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `thumbnail`, `proponent_id`, `file_pdf`, `total_pages`, `file_video`, `created_at`, `updated_at`, `expires_at`, `is_active`, `summary`, `edited_at`) VALUES
(1, 'course 1', 'course 1course 1', '3f4f85413b5ff3e8.jpg', 1, NULL, 0, NULL, '2026-03-03 13:34:48', '2026-03-18 06:05:05', NULL, 1, 'course 1course 1course 1course 1course 1course 1course 1course 1course 1course 1course 1course 1', NULL),
(2, 'Training Regulations', 'Training RegulationsTraining Regulations', 'b3d2a09b4be0d731.png', 1, '029ef61120c5415b.pdf', 95, NULL, '2026-03-10 16:27:40', NULL, NULL, 1, 'Training RegulationsTraining RegulationsTraining Regulations', NULL),
(3, 'test expired', 'This is an expired course', NULL, 4, NULL, 0, NULL, '2026-03-18 05:57:40', NULL, '2026-03-15', 1, 'No Summary - Expired', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `edit`
--

CREATE TABLE `edit` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `proponent_id` int(11) NOT NULL,
  `file_pdf` varchar(255) DEFAULT NULL,
  `file_video` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `thumbnail` varchar(255) DEFAULT NULL,
  `summary` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `expired_at` date DEFAULT NULL,
  `progress` decimal(5,2) DEFAULT 0.00,
  `pages_viewed` int(11) DEFAULT 0 COMMENT 'Number of PDF pages viewed',
  `last_viewed_page` int(11) DEFAULT 0 COMMENT 'Last page viewed by student',
  `last_activity` timestamp NULL DEFAULT NULL COMMENT 'Last activity timestamp',
  `status` enum('ongoing','completed','expired') DEFAULT 'ongoing',
  `is_archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `user_id`, `course_id`, `enrolled_at`, `completed_at`, `expired_at`, `progress`, `pages_viewed`, `last_viewed_page`, `last_activity`, `status`, `is_archived`) VALUES
(45, 20, 1, '2026-03-18 14:17:41', NULL, NULL, 0.00, 0, 0, NULL, 'ongoing', 0),
(46, 24, 1, '2026-03-18 14:17:42', NULL, NULL, 0.00, 0, 0, NULL, 'ongoing', 0),
(47, 26, 1, '2026-03-18 14:18:06', NULL, NULL, 0.00, 0, 0, NULL, 'ongoing', 0),
(52, 26, 3, '2026-03-18 15:05:20', NULL, NULL, 0.00, 0, 0, NULL, 'ongoing', 0),
(53, 16, 3, '2026-03-18 15:05:20', NULL, NULL, 0.00, 0, 0, NULL, 'ongoing', 0),
(54, 20, 3, '2026-03-18 15:05:20', NULL, NULL, 0.00, 0, 0, NULL, 'ongoing', 0),
(55, 27, 2, '2026-03-19 07:39:55', '2026-03-19 07:40:15', NULL, 100.00, 95, 0, NULL, 'completed', 0),
(56, 16, 1, '2026-03-19 08:59:41', NULL, NULL, 0.00, 0, 0, NULL, 'ongoing', 0);

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_published` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news`
--

INSERT INTO `news` (`id`, `title`, `body`, `created_by`, `created_at`, `is_published`) VALUES
(1, 'First News', 'First NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst NewsFirst News', 1, '2026-03-02 18:28:05', 1),
(2, 'Bruno', 'We dont talk about bruno!', 1, '2026-03-13 00:26:14', 1),
(3, 'Senate passes GIDA Schools Act on third reading to improve education in marginalized communities', 'Senate Bill No. 1937, or the “GIDA Schools Act”, has passed on its third and final reading today, March 16, 2026. Sponsored by Second Congressional Commission (EDCOM II) Co-Chairperson Senator Bam Aquino, this landmark legislation seeks to institutionalize crucial support for public basic education schools in Geographically Isolated and Disadvantaged Areas (GIDA).\r\n\r\nThe passage of SBN 1937 marks a monumental step in ensuring that the constitutional right to quality education reaches every Filipino child, no matter how remote their community. The bill comprehensively targets schools facing severe geographic, infrastructural, and access-related barriers, specifically those that endure a lack of electricity, have makeshift or multigrade classrooms, or require more than an hour of travel to reach.\r\n\r\nIn its Final Report, EDCOM II found that learners in GIDA, or “last mile schools”, suffer from acute proficiency gaps. For instance, in the Grade 10 and Grade 12 National Achievement Tests (NAT), these students are heavily concentrated in the low and not-proficient bands, with almost none achieving proficiency, and a staggering 81.59% below the minimum proficiency threshold. \r\n\r\nKey provisions of SBN 1937 seek to reverse this, with crucial supports including:\r\n\r\nNational Accessibility Standards and Mapping: The bill mandates a National GIDA Schools Mapping System for targeted resource allocation. It also enforces a strict accessibility standard, ensuring students have access to a school within a 3-kilometer walkable distance, or are otherwise provided with safe transportation arrangements.\r\nWhole-of-Government Infrastructure Support: Recognizing that education access goes beyond school walls, the bill requires the Department of Education (DepEd) to collaborate with the DPWH for access roads, the DOE and NEA for electricity, and the DICT for internet connectivity.\r\nContext-Responsive Facilities: SBN 1937 ensures that school buildings are durable, disaster-resilient, and culturally suitable, even allowing for the use of sustainable, locally sourced materials like engineered bamboo when compliant with safety metrics.\r\nComprehensive Support for Teachers: To attract and retain quality educators in remote areas, the bill provides a robust support package. This includes additional compensation (hardship allowance, hazard pay, relocation assistance), housing and accommodation support, and targeted scholarships.\r\nLocalized and Inclusive Recruitment: The legislation prioritizes hiring qualified teachers who reside in the local community and speak the local language. It also mandates the integration of Indigenous Knowledge Systems and Practices (IKSP) into the curriculum and allows for the engagement of community elders as Indigenous Knowledge Educators.\r\nSBN 1937 was approved in substitution of several Senate Bills and took into consideration House Bill No. 4745 (the Last Mile Schools Act). SBN 1937 is also principally authored by EDCOM 2 Co-Chairperson Sen. Loren Legarda, co-authored by Commissioner Sen. Alan Peter Cayetano, and co-sponsored by Commissioner Sen. Joel Villanueva. \r\n\r\nEDCOM 2 urges the swift harmonization of the Senate and House versions of the bill to ensure its immediate enactment into law. By investing in resilient school infrastructure, access roads, and our frontline educators, the GIDA Schools Act will finally bring quality education to the “last mile” and ensure that no Filipino learner is left behind.', 3, '2026-03-17 01:39:52', 1),
(4, 'PH fuel prices ‘most expensive’ yet, as peso almost 60:$1', 'MANILA, Philippines — Energy Secretary Sharon Garin said the country will log its “most expensive” fuel prices yet by Tuesday, the weekly schedule for price adjustments, amid the Iran war that continues to disrupt fuel exports past its second week.\r\n\r\nMeanwhile, the Philippine peso nearly reached the P60-per-dollar level after weakening to a new record low on Monday, with the Bangko Sentral ng Pilipinas (BSP) reportedly intervening to temper further losses.\r\n\r\nIn a press conference on Monday, Garin noted that the latest increases taking effect on Tuesday include “two of the highest jumps” in fuel prices.\r\n\r\nNO LETUP Vehicle owners wait for their turn to fill their tanks at a gasoline station in Paco, Manila, on Monday as they brace for the second week of a steep price increase. —NIÑO JESUS ORBETA\r\nNO LETUP: Vehicle owners wait for their turn to fill their tanks at a gasoline station in Paco, Manila, on Monday as they brace for the second week of a steep price increase. —Photo by Niño Jesus Orbeta | INQUIRER\r\nMANILA, Philippines — Energy Secretary Sharon Garin said the country will log its “most expensive” fuel prices yet by Tuesday, the weekly schedule for price adjustments, amid the Iran war that continues to disrupt fuel exports past its second week.\r\n\r\nMeanwhile, the Philippine peso nearly reached the P60-per-dollar level after weakening to a new record low on Monday, with the Bangko Sentral ng Pilipinas (BSP) reportedly intervening to temper further losses.\r\n\r\nIn a press conference on Monday, Garin noted that the latest increases taking effect on Tuesday include “two of the highest jumps” in fuel prices.\r\n\r\nArticle continues after this advertisement\r\n\r\nREAD: PNP probes sudden closure of gas stations amid price hikes\r\n\r\nDiesel prices have surged anew by P20.40 to P23.90 per liter, from P17.50 to P24.25 the previous Tuesday, March 10. Garin said consumers loading up in Metro Manila will pay from P94 to as high as P115 per liter for diesel.\r\n\r\nGasoline prices have climbed by P12.90 to P16.60 per liter, from P7 to P13.', 3, '2026-03-17 01:41:29', 1),
(5, 'Iran and Gaza conflicts teach Gulf states a hard-power lesson', 'The military campaign that the United States and Israel launched against Iran on 28 February has plunged the region into conflict, triggering retaliatory strikes from Tehran, including against all six states of the Gulf Cooperation Council. Amid frantic efforts to defend their airspaces and populations, these countries are counting the high cost of partnering with the US – a price they were already paying by accepting a compromised role in Donald Trump’s Gaza peace plan. As a result, they are likely to increasingly embrace hard power and diversify security partnerships in the face of worsening regional volatility. \r\n\r\nThe GCC’s worst fears have been realised in the wake of the US–Israel attacks on Iran.\r\n\r\nIn October last year, US President Donald Trump presented his Gaza peace plan as a historic breakthrough. ‘It’s the start of a grand concord and lasting harmony for Israel and all the nations of what will soon be a truly magnificent region,’ he told the Israeli parliament. ‘This is the historic dawn of a new Middle East.’ Instead, it heightened the unease of Saudi Arabia, the United Arab Emirates and other states in the Gulf Cooperation Council (GCC). While the US continues to promote the initiative as a ceasefire blueprint and pathway to reconstruction, reality tells a different story.\r\n\r\nContinuing Israeli air strikes and operations in Gaza and escalating settler violence in the West Bank not only strain the ceasefire but reveal the plan’s underlying weakness. Fundamentally it is an imposed solution that excludes the Palestinians, lacks detailed enforcement mechanisms and fails to address the conflict’s core issues. ‘If we are just resolving what happened in Gaza, the catastrophe [of] the past two years, it’s not enough,’ said Qatari Prime Minister Sheikh Mohammed bin Abdulrahman Al Thani in Doha in December 2025. ‘This conflict is not only about Gaza. It’s about the West Bank. It’s about the rights of the Palestinians for their state.’ \r\n\r\nAfter all, even if the plan were successful, Gaza cannot be isolated from other conflicts in the region. Indeed, Israel’s creeping annexation of the West Bank will give non-state actors in the region yet another reason to target Israel, its allies and vital shipping lanes – already under fire from Iran. Thanks to their dependencies on the US, the GCC states – Saudi Arabia, United Arab Emirates, Qatar, Oman, Bahrain and Kuwait – now find they have little choice but to support Washington’s policies in the region. Yet the consequences, painfully evident in Tehran’s strikes, will compel them to take up hard power in pursuit of security. ', 3, '2026-03-17 01:43:16', 1),
(6, 'COOKERY NC II FINAL EXAMINATION', 'FINAL EXAMIATION WILL BE CONDUCTED ON\r\n\r\nAPRIL 6 - 10, 2026\r\n\r\nPLEASE SETTLE YOUR ACCOUNT BALANCE AT THE REGISTRARS OFFICE\r\n\r\n', 3, '2026-03-17 01:45:00', 1),
(7, 'COOKERY PROJECT DEADLINE', '\r\nPLEASE PASS YOUR PROJECTS BEFORE \r\n\r\nAPRIL 9, 2026 \r\n\r\nNON COMPLIANCE WILL HAVE A FAIL GRADE\r\nNO EXEMPTIONS', 4, '2026-03-17 23:45:10', 1),
(8, 'Test News', 'No news for today', 1, '2026-03-18 00:50:50', 1),
(9, 'News Filler 1', 'News Filler 1 for testing', 1, '2026-03-18 00:51:03', 1),
(10, 'News Filler 2', 'News Fillersxczxcascazxcas', 1, '2026-03-18 00:51:16', 1);

-- --------------------------------------------------------

--
-- Table structure for table `news_read`
--

CREATE TABLE `news_read` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `news_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `news_read`
--

INSERT INTO `news_read` (`id`, `user_id`, `news_id`, `read_at`) VALUES
(11, 1, 10, '2026-03-18 09:07:00'),
(12, 1, 9, '2026-03-18 09:07:03'),
(13, 1, 8, '2026-03-18 09:07:05'),
(14, 1, 7, '2026-03-18 12:44:12'),
(15, 1, 5, '2026-03-18 12:44:19'),
(16, 1, 6, '2026-03-19 00:19:33'),
(17, 1, 4, '2026-03-19 06:29:22');

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `otp` varchar(10) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 10 minute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pdf_progress`
--

CREATE TABLE `pdf_progress` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `page_number` int(11) NOT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pdf_progress`
--

INSERT INTO `pdf_progress` (`id`, `enrollment_id`, `page_number`, `viewed_at`) VALUES
(575, 55, 1, '2026-03-19 07:39:56'),
(576, 55, 2, '2026-03-19 07:39:59'),
(577, 55, 3, '2026-03-19 07:39:59'),
(578, 55, 4, '2026-03-19 07:40:00'),
(579, 55, 5, '2026-03-19 07:40:00'),
(580, 55, 6, '2026-03-19 07:40:01'),
(581, 55, 7, '2026-03-19 07:40:01'),
(582, 55, 8, '2026-03-19 07:40:01'),
(583, 55, 9, '2026-03-19 07:40:01'),
(584, 55, 10, '2026-03-19 07:40:01'),
(585, 55, 11, '2026-03-19 07:40:01'),
(586, 55, 12, '2026-03-19 07:40:01'),
(587, 55, 13, '2026-03-19 07:40:01'),
(588, 55, 14, '2026-03-19 07:40:01'),
(589, 55, 15, '2026-03-19 07:40:02'),
(590, 55, 16, '2026-03-19 07:40:02'),
(591, 55, 17, '2026-03-19 07:40:02'),
(592, 55, 18, '2026-03-19 07:40:02'),
(593, 55, 19, '2026-03-19 07:40:02'),
(594, 55, 20, '2026-03-19 07:40:02'),
(595, 55, 21, '2026-03-19 07:40:02'),
(596, 55, 22, '2026-03-19 07:40:02'),
(597, 55, 23, '2026-03-19 07:40:02'),
(598, 55, 24, '2026-03-19 07:40:03'),
(599, 55, 25, '2026-03-19 07:40:03'),
(600, 55, 26, '2026-03-19 07:40:03'),
(601, 55, 27, '2026-03-19 07:40:03'),
(602, 55, 28, '2026-03-19 07:40:03'),
(603, 55, 29, '2026-03-19 07:40:03'),
(604, 55, 30, '2026-03-19 07:40:03'),
(605, 55, 31, '2026-03-19 07:40:03'),
(606, 55, 32, '2026-03-19 07:40:03'),
(607, 55, 33, '2026-03-19 07:40:04'),
(608, 55, 34, '2026-03-19 07:40:04'),
(609, 55, 35, '2026-03-19 07:40:04'),
(610, 55, 36, '2026-03-19 07:40:05'),
(611, 55, 37, '2026-03-19 07:40:05'),
(612, 55, 38, '2026-03-19 07:40:05'),
(613, 55, 39, '2026-03-19 07:40:05'),
(614, 55, 40, '2026-03-19 07:40:06'),
(615, 55, 41, '2026-03-19 07:40:06'),
(616, 55, 42, '2026-03-19 07:40:06'),
(617, 55, 43, '2026-03-19 07:40:06'),
(618, 55, 44, '2026-03-19 07:40:06'),
(619, 55, 45, '2026-03-19 07:40:06'),
(620, 55, 46, '2026-03-19 07:40:06'),
(621, 55, 47, '2026-03-19 07:40:06'),
(622, 55, 48, '2026-03-19 07:40:06'),
(623, 55, 49, '2026-03-19 07:40:06'),
(624, 55, 50, '2026-03-19 07:40:07'),
(625, 55, 51, '2026-03-19 07:40:07'),
(626, 55, 52, '2026-03-19 07:40:07'),
(627, 55, 53, '2026-03-19 07:40:07'),
(628, 55, 54, '2026-03-19 07:40:07'),
(629, 55, 55, '2026-03-19 07:40:07'),
(630, 55, 56, '2026-03-19 07:40:08'),
(631, 55, 57, '2026-03-19 07:40:08'),
(632, 55, 58, '2026-03-19 07:40:08'),
(633, 55, 59, '2026-03-19 07:40:08'),
(634, 55, 60, '2026-03-19 07:40:08'),
(635, 55, 61, '2026-03-19 07:40:08'),
(636, 55, 62, '2026-03-19 07:40:08'),
(637, 55, 63, '2026-03-19 07:40:08'),
(638, 55, 64, '2026-03-19 07:40:09'),
(639, 55, 65, '2026-03-19 07:40:09'),
(640, 55, 66, '2026-03-19 07:40:09'),
(641, 55, 67, '2026-03-19 07:40:09'),
(642, 55, 68, '2026-03-19 07:40:09'),
(643, 55, 69, '2026-03-19 07:40:09'),
(644, 55, 70, '2026-03-19 07:40:10'),
(645, 55, 71, '2026-03-19 07:40:10'),
(646, 55, 72, '2026-03-19 07:40:10'),
(647, 55, 73, '2026-03-19 07:40:10'),
(648, 55, 74, '2026-03-19 07:40:10'),
(649, 55, 75, '2026-03-19 07:40:10'),
(650, 55, 76, '2026-03-19 07:40:10'),
(651, 55, 77, '2026-03-19 07:40:10'),
(652, 55, 78, '2026-03-19 07:40:11'),
(653, 55, 79, '2026-03-19 07:40:11'),
(654, 55, 80, '2026-03-19 07:40:11'),
(655, 55, 81, '2026-03-19 07:40:11'),
(656, 55, 82, '2026-03-19 07:40:11'),
(657, 55, 83, '2026-03-19 07:40:11'),
(658, 55, 84, '2026-03-19 07:40:11'),
(659, 55, 85, '2026-03-19 07:40:11'),
(660, 55, 86, '2026-03-19 07:40:11'),
(661, 55, 87, '2026-03-19 07:40:12'),
(662, 55, 88, '2026-03-19 07:40:12'),
(663, 55, 89, '2026-03-19 07:40:12'),
(664, 55, 90, '2026-03-19 07:40:12'),
(665, 55, 91, '2026-03-19 07:40:12'),
(666, 55, 92, '2026-03-19 07:40:12'),
(667, 55, 93, '2026-03-19 07:40:12'),
(668, 55, 94, '2026-03-19 07:40:12'),
(669, 55, 95, '2026-03-19 07:40:12');

-- --------------------------------------------------------

--
-- Table structure for table `time_logs`
--

CREATE TABLE `time_logs` (
  `id` int(11) NOT NULL,
  `enrollment_id` int(11) NOT NULL,
  `start_ts` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `end_ts` timestamp NOT NULL DEFAULT current_timestamp(),
  `seconds` int(11) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fname` varchar(100) DEFAULT NULL,
  `lname` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` enum('admin','proponent','user','superadmin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `status` enum('pending','confirmed') DEFAULT 'confirmed',
  `message_notifications` tinyint(1) DEFAULT 1,
  `email_notifications` tinyint(1) DEFAULT 1,
  `departments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fname`, `lname`, `email`, `role`, `created_at`, `updated_at`, `is_verified`, `otp_code`, `otp_expires_at`, `status`, `message_notifications`, `email_notifications`, `departments`) VALUES
(1, 'renz', '$2y$10$s/.EtPCh3JvNPVtKtafDDOAEXQC/ulCI8EXXxRtsM05X0tH.e/7M.', 'renz', 'renz', 'renz@gmail.com', 'superadmin', '2026-03-03 01:10:36', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(3, 'admin', '$2y$10$af/ikZHii0fV18bHP5uFeu1Ts/MqKnZujyLeXjk7u.LGETD49ANA.', 'admin', 'admin', 'admin@gmail.com', 'admin', '2026-03-03 01:24:24', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(4, 'pro', '$2y$10$JxbaFCY4HB8bwFdSL4IWgOSJhYAeEhiBvmDBMd4MsjUFrJE8LgYje', 'pro', 'pro', 'pro@gmail.com', 'proponent', '2026-03-03 01:26:06', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(16, 'kookysaurus', '$2y$10$zxmdYFEuqkUv98.xjIAGT.I61n37VUFQjOAYa3Hso/QaFC6zJ9S2C', 'Kooky Lyann', 'Arabia', 'kookyarabia06@gmail.com', 'user', '2026-03-17 08:26:19', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(20, 'marga', '$2y$10$Rlx1T3J.GG6GIKeIgXgNEepNnLwD8oiWgL0W7a1UzAukJzejwW.Ca', 'Margarette', 'Duazo', 'duazomargarette@gmail.com', 'user', '2026-03-18 08:20:04', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(24, 'rentsu', '$2y$10$zSFVUNXUOUpX9QCOtgHad.5yPi3bl6gUv/d0Q7Vxie6K7vyQ7Ic2q', 'Renz', 'Mendiola', 'ramendiola418@gmail.com', 'user', '2026-03-18 08:55:22', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(26, 'test', '$2y$10$nmHSI9RBIDEIT9ux2bGt5eqpCqnAcXH8TdExz.SpCK.fxaZsyYuTK', 'G', 'Plankton', 'gplankton1@gmail.com', 'user', '2026-03-18 13:16:24', NULL, 0, NULL, NULL, 'pending', 1, 1, NULL),
(27, 'user', '$2y$10$BR0e7a/wDJpphj9rqvc2LO5tPF9RAmpGE0N07MyCPDbYYFfcz2aXy', 'user', 'user', 'user@test.com', 'user', '2026-03-19 07:35:07', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assessments`
--
ALTER TABLE `assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_assessments_course` (`course_id`);

--
-- Indexes for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `idx_answers_attempt` (`attempt_id`);

--
-- Indexes for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempts_assessment` (`assessment_id`),
  ADD KEY `idx_attempts_user` (`user_id`);

--
-- Indexes for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_id` (`assessment_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proponent_id` (`proponent_id`);

--
-- Indexes for table `edit`
--
ALTER TABLE `edit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proponent_id` (`proponent_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_user_course` (`user_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `news_read`
--
ALTER TABLE `news_read`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_news` (`user_id`,`news_id`),
  ADD KEY `news_id` (`news_id`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pdf_progress`
--
ALTER TABLE `pdf_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_page_view` (`enrollment_id`,`page_number`),
  ADD KEY `idx_pdf_progress_enrollment` (`enrollment_id`);

--
-- Indexes for table `time_logs`
--
ALTER TABLE `time_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enrollment_id` (`enrollment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assessments`
--
ALTER TABLE `assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `edit`
--
ALTER TABLE `edit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `news_read`
--
ALTER TABLE `news_read`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pdf_progress`
--
ALTER TABLE `pdf_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=670;

--
-- AUTO_INCREMENT for table `time_logs`
--
ALTER TABLE `time_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  ADD CONSTRAINT `assessment_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `assessment_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  ADD CONSTRAINT `assessment_attempts_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assessment_attempts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD CONSTRAINT `assessment_questions_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`proponent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `edit`
--
ALTER TABLE `edit`
  ADD CONSTRAINT `edit_ibfk_1` FOREIGN KEY (`proponent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `news_read`
--
ALTER TABLE `news_read`
  ADD CONSTRAINT `news_read_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `news_read_ibfk_2` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pdf_progress`
--
ALTER TABLE `pdf_progress`
  ADD CONSTRAINT `pdf_progress_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `time_logs`
--
ALTER TABLE `time_logs`
  ADD CONSTRAINT `time_logs_ibfk_1` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
