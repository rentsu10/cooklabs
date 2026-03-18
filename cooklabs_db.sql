-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 16, 2026 at 06:11 AM
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
(2, 2, 'Training Regulation Post Test', '', NULL, NULL, 75, 4, 0, 1, '2026-03-12 14:01:52', '2026-03-13 07:42:10');

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
(21, 6, 5, 'A', 0, 0, '2026-03-15 13:01:52'),
(22, 6, 8, 'A', 0, 0, '2026-03-15 13:01:54'),
(23, 6, 6, 'A', 0, 0, '2026-03-15 13:01:56'),
(24, 6, 7, 'A', 1, 1, '2026-03-15 13:01:57'),
(33, 9, 6, 'D', 1, 1, '2026-03-15 13:26:31'),
(34, 9, 8, 'C', 1, 1, '2026-03-15 13:26:32'),
(35, 9, 7, 'A', 1, 1, '2026-03-15 13:26:35'),
(36, 9, 5, 'D', 1, 1, '2026-03-15 13:26:36');

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
(6, 2, 5, 1, 25.00, 0, 1, 'completed', '2026-03-15 13:01:51', '2026-03-15 13:01:59', 0),
(9, 2, 5, 1, 100.00, 0, 4, 'completed', '2026-03-15 13:26:29', '2026-03-15 13:26:38', 0);

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
(8, 2, 'Who is Kooky', 'Scammer', 'Kookycoin', 'Momi Oni Top Fan 1000%', 'Kokey', 'C', 1, 4, '2026-03-13 07:42:10');

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
(1, 'course 1', 'course 1course 1', '3f4f85413b5ff3e8.jpg', 1, NULL, 0, NULL, '2026-03-03 13:34:48', NULL, '2026-04-04', 1, 'course 1course 1course 1course 1course 1course 1course 1course 1course 1course 1course 1course 1', NULL),
(2, 'Training Regulations', 'Training RegulationsTraining Regulations', 'b3d2a09b4be0d731.png', 1, '029ef61120c5415b.pdf', 95, NULL, '2026-03-10 16:27:40', NULL, NULL, 1, 'Training RegulationsTraining RegulationsTraining Regulations', NULL);

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
(7, 5, 2, '2026-03-15 12:14:00', '2026-03-15 12:14:43', NULL, 100.00, 95, 0, NULL, 'completed', 1),
(8, 5, 1, '2026-03-15 13:27:36', NULL, NULL, 0.00, 0, 0, NULL, 'ongoing', 0),
(12, 8, 2, '2026-03-16 05:08:51', NULL, NULL, 0.00, 0, 0, NULL, 'ongoing', 0);

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
(2, 'Bruno', 'We dont talk about bruno!', 1, '2026-03-13 00:26:14', 1);

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
(287, 7, 1, '2026-03-15 12:14:02'),
(288, 7, 2, '2026-03-15 12:14:08'),
(289, 7, 3, '2026-03-15 12:14:08'),
(290, 7, 4, '2026-03-15 12:14:08'),
(291, 7, 5, '2026-03-15 12:14:10'),
(292, 7, 6, '2026-03-15 12:14:10'),
(293, 7, 7, '2026-03-15 12:14:11'),
(294, 7, 8, '2026-03-15 12:14:11'),
(295, 7, 9, '2026-03-15 12:14:11'),
(296, 7, 10, '2026-03-15 12:14:11'),
(297, 7, 11, '2026-03-15 12:14:11'),
(298, 7, 12, '2026-03-15 12:14:12'),
(299, 7, 13, '2026-03-15 12:14:12'),
(300, 7, 14, '2026-03-15 12:14:12'),
(301, 7, 15, '2026-03-15 12:14:12'),
(302, 7, 16, '2026-03-15 12:14:13'),
(303, 7, 17, '2026-03-15 12:14:14'),
(304, 7, 18, '2026-03-15 12:14:14'),
(305, 7, 19, '2026-03-15 12:14:14'),
(306, 7, 20, '2026-03-15 12:14:15'),
(307, 7, 21, '2026-03-15 12:14:16'),
(308, 7, 22, '2026-03-15 12:14:16'),
(309, 7, 23, '2026-03-15 12:14:16'),
(310, 7, 24, '2026-03-15 12:14:18'),
(311, 7, 25, '2026-03-15 12:14:18'),
(312, 7, 26, '2026-03-15 12:14:18'),
(313, 7, 27, '2026-03-15 12:14:19'),
(314, 7, 28, '2026-03-15 12:14:19'),
(315, 7, 29, '2026-03-15 12:14:19'),
(316, 7, 30, '2026-03-15 12:14:20'),
(317, 7, 31, '2026-03-15 12:14:20'),
(318, 7, 32, '2026-03-15 12:14:20'),
(319, 7, 33, '2026-03-15 12:14:21'),
(320, 7, 34, '2026-03-15 12:14:21'),
(321, 7, 35, '2026-03-15 12:14:21'),
(322, 7, 36, '2026-03-15 12:14:22'),
(323, 7, 37, '2026-03-15 12:14:22'),
(324, 7, 38, '2026-03-15 12:14:22'),
(325, 7, 39, '2026-03-15 12:14:23'),
(326, 7, 40, '2026-03-15 12:14:23'),
(327, 7, 41, '2026-03-15 12:14:24'),
(328, 7, 42, '2026-03-15 12:14:24'),
(329, 7, 43, '2026-03-15 12:14:25'),
(330, 7, 44, '2026-03-15 12:14:25'),
(331, 7, 45, '2026-03-15 12:14:25'),
(332, 7, 46, '2026-03-15 12:14:26'),
(333, 7, 47, '2026-03-15 12:14:26'),
(334, 7, 48, '2026-03-15 12:14:26'),
(335, 7, 49, '2026-03-15 12:14:26'),
(336, 7, 50, '2026-03-15 12:14:27'),
(337, 7, 51, '2026-03-15 12:14:27'),
(338, 7, 52, '2026-03-15 12:14:27'),
(339, 7, 53, '2026-03-15 12:14:27'),
(340, 7, 54, '2026-03-15 12:14:28'),
(341, 7, 55, '2026-03-15 12:14:28'),
(342, 7, 56, '2026-03-15 12:14:28'),
(343, 7, 57, '2026-03-15 12:14:28'),
(344, 7, 58, '2026-03-15 12:14:28'),
(345, 7, 59, '2026-03-15 12:14:28'),
(346, 7, 60, '2026-03-15 12:14:28'),
(347, 7, 61, '2026-03-15 12:14:28'),
(348, 7, 62, '2026-03-15 12:14:29'),
(349, 7, 63, '2026-03-15 12:14:29'),
(350, 7, 64, '2026-03-15 12:14:29'),
(351, 7, 65, '2026-03-15 12:14:29'),
(352, 7, 66, '2026-03-15 12:14:29'),
(353, 7, 67, '2026-03-15 12:14:29'),
(354, 7, 68, '2026-03-15 12:14:30'),
(355, 7, 69, '2026-03-15 12:14:30'),
(356, 7, 70, '2026-03-15 12:14:30'),
(357, 7, 71, '2026-03-15 12:14:31'),
(358, 7, 72, '2026-03-15 12:14:31'),
(359, 7, 73, '2026-03-15 12:14:31'),
(360, 7, 74, '2026-03-15 12:14:32'),
(361, 7, 75, '2026-03-15 12:14:32'),
(362, 7, 76, '2026-03-15 12:14:32'),
(363, 7, 77, '2026-03-15 12:14:32'),
(364, 7, 78, '2026-03-15 12:14:32'),
(365, 7, 79, '2026-03-15 12:14:32'),
(366, 7, 80, '2026-03-15 12:14:33'),
(367, 7, 81, '2026-03-15 12:14:33'),
(368, 7, 82, '2026-03-15 12:14:33'),
(369, 7, 83, '2026-03-15 12:14:33'),
(370, 7, 84, '2026-03-15 12:14:33'),
(371, 7, 85, '2026-03-15 12:14:34'),
(372, 7, 86, '2026-03-15 12:14:34'),
(373, 7, 87, '2026-03-15 12:14:34'),
(374, 7, 88, '2026-03-15 12:14:34'),
(375, 7, 89, '2026-03-15 12:14:34'),
(376, 7, 90, '2026-03-15 12:14:34'),
(377, 7, 91, '2026-03-15 12:14:34'),
(378, 7, 92, '2026-03-15 12:14:35'),
(379, 7, 93, '2026-03-15 12:14:35'),
(380, 7, 94, '2026-03-15 12:14:35'),
(381, 7, 95, '2026-03-15 12:14:35');

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
(5, 'user', '$2y$10$29jblhF5syUZLGnRwqAojuyLtDwBfd50taDvKxawDzyLR80tr.Ju2', 'user', 'user', 'user@gmail.com', 'user', '2026-03-03 01:29:03', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(8, 'Plankton', '$2y$10$Hi9O.28IdqO0nfNtkUGDBOBnOeBJ/O8GR315G50iuhpKry.LiR7ji', 'G', 'Plankton', 'gplankton1@gmail.com', 'user', '2026-03-10 17:09:45', NULL, 0, NULL, NULL, 'pending', 1, 1, NULL),
(9, 'user1', '$2y$10$4Dms.7H/rCAfTQBAsosWtOWP6dpFUDi453Gck1kOZfSAHgVm5weKu', 'user1', 'user1', 'user1@mail.asd', 'user', '2026-03-12 05:18:56', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(10, 'user2', '$2y$10$9bfjRN38U30AAgrhUtGt3.C2M2Z4cCAN0dIEqyhEXMYMZBAxV6pv6', 'user2', 'user2', 'user2@mail.com', 'user', '2026-03-12 05:19:25', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(11, 'pro1', '$2y$10$vZwgA8nWece1SwixQE65M.KW.2tnQPHoNwXEaJLDORYeNribz3gey', 'pro1', 'pro1', 'pro1@gmail.com', 'proponent', '2026-03-15 14:22:58', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(12, 'user3', '$2y$10$6LWhChbY7jFCjsE3rZ2JE.jWWPcywy3pd0vEuItRV7JkYMyc5.vKm', 'user3', 'user3', 'user3@gmail.com', 'user', '2026-03-15 14:43:25', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(13, 'user4', '$2y$10$SqKZ36Zs3ddpqAnUGs7YZ.dRuz2ZUujn2sIZT8MAG7/x2urMk4xiW', 'user4', 'user4', 'user4@gmail.com', 'user', '2026-03-15 14:43:45', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(14, 'user5', '$2y$10$EVfgd28at9VpGxzw7tZFIOpdn5/zzhgCQLv5GVaTAJSgl9zMLMLS6', 'user5', 'user5', 'user5@mai.com', 'user', '2026-03-15 14:44:04', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL),
(15, 'user6', '$2y$10$6gILeckEIddXcu51C9kwtOzNAos.PPBQfYRsda73230tt/TnmbvSi', 'user6', 'user6', 'user6@mail.com', 'user', '2026-03-15 14:44:21', NULL, 0, NULL, NULL, 'confirmed', 1, 1, NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `assessment_answers`
--
ALTER TABLE `assessment_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `assessment_attempts`
--
ALTER TABLE `assessment_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `edit`
--
ALTER TABLE `edit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pdf_progress`
--
ALTER TABLE `pdf_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=385;

--
-- AUTO_INCREMENT for table `time_logs`
--
ALTER TABLE `time_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
