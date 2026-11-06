-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2026 at 03:57 PM
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
-- Database: `job_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `religion` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `education` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`id`, `user_id`, `first_name`, `last_name`, `phone`, `address`, `latitude`, `longitude`, `religion`, `profile_picture`, `bio`, `created_at`, `updated_at`, `education`) VALUES
(1, 1, 'Marc Ahron', 'Cerdena', '09123456789', 'Phase 5, Brgy. 176, Bagong Silang, Caloocan City', 14.76973680, 121.05290340, 'Roman Catholic', NULL, 'sadsadsadsd', '2026-09-08 10:32:10', '2026-10-05 07:53:49', 'Bachelor of Science in Information Technology'),
(9, 20, 'Gilbert Aaron', 'Adane', '09123456789', NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-23 13:21:13', '2026-10-23 13:21:13', NULL),
(10, 21, 'Aaron', 'Adane', '09123456789', NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-06 14:06:54', '2026-11-06 14:06:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `applicant_education`
--

CREATE TABLE `applicant_education` (
  `id` int(10) UNSIGNED NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `educational_attainment` enum('Elementary','Junior High School','Senior High School','Vocational','College Undergraduate','College Graduate','Postgraduate') NOT NULL,
  `degree_program` varchar(255) DEFAULT NULL,
  `school_name` varchar(255) DEFAULT NULL,
  `graduation_year` year(4) DEFAULT NULL,
  `current_year_level` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applicant_education`
--

INSERT INTO `applicant_education` (`id`, `applicant_id`, `educational_attainment`, `degree_program`, `school_name`, `graduation_year`, `current_year_level`, `created_at`, `updated_at`) VALUES
(1, 10, 'Senior High School', 'STEM', 'Our Lady of Fatima University', '2024', 'Test', '2026-11-06 14:11:45', '2026-11-06 14:11:45'),
(2, 1, 'College Graduate', 'BSIT', 'Global Reciprocal Colleges', '2026', 'Test', '2026-11-06 14:24:44', '2026-11-06 14:24:44');

-- --------------------------------------------------------

--
-- Table structure for table `applicant_tesda_qualifications`
--

CREATE TABLE `applicant_tesda_qualifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `qualification_name` varchar(255) NOT NULL,
  `nc_level` enum('NC I','NC II','NC III','NC IV','No NC') NOT NULL DEFAULT 'No NC',
  `skills_acquired` text DEFAULT NULL,
  `training_center` varchar(255) DEFAULT NULL,
  `year_completed` year(4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `resume_id` int(10) UNSIGNED DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `status` enum('pending','reviewed','shortlisted','rejected','hired') NOT NULL DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `job_id`, `applicant_id`, `resume_id`, `cover_letter`, `status`, `applied_at`, `updated_at`) VALUES
(23, 23, 1, NULL, 'Test', 'rejected', '2026-10-06 04:21:58', '2026-11-06 14:53:35');

-- --------------------------------------------------------

--
-- Table structure for table `application_matches`
--

CREATE TABLE `application_matches` (
  `id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `match_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `skills_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `experience_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `education_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tesda_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `matched_skills` text DEFAULT NULL,
  `missing_skills` text DEFAULT NULL,
  `qualification_explanation` text DEFAULT NULL,
  `matched_qualifications` text DEFAULT NULL,
  `missing_qualifications` text DEFAULT NULL,
  `skill_gap_feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `application_matches`
--

INSERT INTO `application_matches` (`id`, `application_id`, `match_score`, `skills_score`, `experience_score`, `education_score`, `tesda_score`, `matched_skills`, `missing_skills`, `qualification_explanation`, `matched_qualifications`, `missing_qualifications`, `skill_gap_feedback`, `created_at`, `updated_at`) VALUES
(15, 23, 87.43, 87.43, 0.00, 0.00, 0.00, 'Customer Service, Good Communication Skills, Sales Experience, Cash Handling, POS Operation, Teamwork, Basic Computer Skills, Inventory Management, Time Management', 'Problem Solving', NULL, NULL, NULL, NULL, '2026-10-06 04:21:58', '2026-10-06 04:21:58');

-- --------------------------------------------------------

--
-- Table structure for table `application_selected_skills`
--

CREATE TABLE `application_selected_skills` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `skill_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employers`
--

CREATE TABLE `employers` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `employer_type` enum('private','lgu') NOT NULL DEFAULT 'private',
  `company_description` text DEFAULT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `caloocan_area` enum('north','south') DEFAULT NULL,
  `verification_status` enum('unverified','pending','approved','rejected') DEFAULT 'unverified',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employers`
--

INSERT INTO `employers` (`id`, `user_id`, `company_name`, `employer_type`, `company_description`, `company_logo`, `contact_person`, `contact_number`, `address`, `barangay`, `latitude`, `longitude`, `caloocan_area`, `verification_status`, `created_at`, `updated_at`) VALUES
(4, 12, 'Company1234', 'private', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'unverified', '2026-09-12 07:30:22', '2026-09-12 07:30:22'),
(5, 13, 'Company 123', 'private', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'unverified', '2026-09-12 08:07:31', '2026-09-12 08:07:31'),
(6, 15, 'Company 456', 'private', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'unverified', '2026-09-19 07:07:11', '2026-09-19 07:07:11'),
(7, 16, 'company 567', 'private', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'unverified', '2026-09-19 07:16:15', '2026-09-19 07:16:15'),
(8, 17, 'company 789', 'private', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'unverified', '2026-09-19 07:57:19', '2026-09-19 07:57:19'),
(9, 18, 'Test Company', 'private', 'This is a test profile.', NULL, 'Marc Ahron Cerdena', '09123456789', '123 Sampaguita Street, Barangay 176, Bagong Silang, North Caloocan City, 1428 Metro Manila', 'Barangay 176', 14.77714210, 121.04305430, 'north', 'approved', '2026-09-25 09:44:40', '2026-10-05 09:40:50'),
(10, 19, 'Pando Jewelry', 'private', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'unverified', '2026-09-25 13:02:27', '2026-09-25 13:02:27');

-- --------------------------------------------------------

--
-- Table structure for table `employer_verifications`
--

CREATE TABLE `employer_verifications` (
  `id` int(11) NOT NULL,
  `employer_id` int(10) UNSIGNED NOT NULL,
  `government_id` varchar(255) DEFAULT NULL,
  `barangay_certificate` varchar(255) DEFAULT NULL,
  `business_permit` varchar(255) DEFAULT NULL,
  `detected_address` text DEFAULT NULL,
  `document_status` enum('unverified','pending','processing','passed','needs_review','failed') DEFAULT 'unverified',
  `location_status` enum('unverified','pending','passed','failed') DEFAULT 'unverified',
  `admin_status` enum('unverified','pending','approved','rejected') DEFAULT 'unverified',
  `admin_notes` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employer_verifications`
--

INSERT INTO `employer_verifications` (`id`, `employer_id`, `government_id`, `barangay_certificate`, `business_permit`, `detected_address`, `document_status`, `location_status`, `admin_status`, `admin_notes`, `submitted_at`, `created_at`, `updated_at`) VALUES
(1, 9, '9bcea44afd31e1edfc81dc76fe5bab6b.pdf', '8160d1e1860713b167e7494e95e9405d.pdf', 'bc7bb9d20f34379624dcafdb891675ce.pdf', NULL, 'passed', 'passed', 'approved', 'The business is approved', '2026-10-05 16:04:07', '2026-09-25 12:34:09', '2026-10-05 09:40:50');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(10) UNSIGNED NOT NULL,
  `employer_id` int(10) UNSIGNED NOT NULL,
  `job_title` varchar(255) NOT NULL,
  `job_description` text DEFAULT NULL,
  `description` text NOT NULL,
  `requirements` text DEFAULT NULL,
  `educational_requirement` varchar(100) DEFAULT NULL,
  `required_degree` varchar(255) DEFAULT NULL,
  `required_skills` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `caloocan_area` enum('north','south') DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longtitude` decimal(11,8) DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Contract','Internship','Freelance') NOT NULL DEFAULT 'Full-time',
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `is_remote` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected','closed') NOT NULL DEFAULT 'pending',
  `application_deadline` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tesda_qualification` varchar(255) DEFAULT NULL,
  `required_nc_level` varchar(20) DEFAULT NULL,
  `job_source` enum('private_employer','lgu','job_fair') NOT NULL DEFAULT 'private_employer',
  `source_name` varchar(255) DEFAULT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `degree_classification` varchar(255) DEFAULT NULL,
  `lgu_job_category` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `employer_id`, `job_title`, `job_description`, `description`, `requirements`, `educational_requirement`, `required_degree`, `required_skills`, `location`, `address`, `barangay`, `caloocan_area`, `latitude`, `longtitude`, `employment_type`, `salary_min`, `salary_max`, `is_remote`, `status`, `application_deadline`, `created_at`, `updated_at`, `tesda_qualification`, `required_nc_level`, `job_source`, `source_name`, `source_url`, `degree_classification`, `lgu_job_category`) VALUES
(23, 9, 'Sales Associate', 'Applicant must have good communication skills and should be willing to...', '', 'Customer Service\r\nGood Communication Skills\r\nSales Experience\r\nCash Handling\r\nPOS Operation\r\nTeamwork\r\nBasic Computer Skills\r\nInventory Management\r\nProblem Solving\r\nTime Management', NULL, NULL, NULL, 'Barangay 176, NORTH Caloocan', '123 Sampaguita Street, Barangay 176, Bagong Silang, North Caloocan City, 1428 Metro Manila', 'Barangay 176', 'north', 14.77714210, 121.04305430, 'Full-time', 20000.00, 25000.00, 0, 'approved', '2026-10-29', '2026-10-06 04:20:46', '2026-10-06 04:21:05', NULL, NULL, 'private_employer', NULL, NULL, NULL, NULL),
(24, 9, 'Test', 'Test', 'Test', 'Tes', 'College Graduate', 'BSIT', 'Test', 'Brgy. Barangay 176, North Caloocan', '123 Sampaguita Street, Barangay 176, Bagong Silang, North Caloocan City, 1428 Metro Manila', 'Barangay 176', 'north', 14.77714210, 121.04305430, 'Full-time', 100000.00, 200000.00, 0, 'approved', '2026-11-30', '2026-11-06 14:20:13', '2026-11-06 14:20:47', NULL, NULL, 'private_employer', NULL, NULL, 'Test', 'Test'),
(25, 9, 'TEST JOBS', 'TEST', 'TEST', 'TEST', 'College Graduate', 'BSIT', 'Test1, Test2, Test3', 'Brgy. Barangay 176, North Caloocan', '123 Sampaguita Street, Barangay 176, Bagong Silang, North Caloocan City, 1428 Metro Manila', 'Barangay 176', 'north', 14.77714210, 121.04305430, 'Full-time', 10000.00, 20000.00, 0, 'approved', '2026-11-30', '2026-11-06 14:40:50', '2026-11-06 14:41:00', NULL, NULL, 'private_employer', NULL, NULL, 'TEST', 'Test');

-- --------------------------------------------------------

--
-- Table structure for table `login_otps`
--

CREATE TABLE `login_otps` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_otps`
--

INSERT INTO `login_otps` (`id`, `user_id`, `otp_hash`, `expires_at`, `attempts`, `is_used`, `created_at`) VALUES
(1, 1, '$2y$10$CSYCkKNMActzcGn7UcbzIek/BJ5KUUFILez0IglwVajvXhdM8jBZa', '2026-09-26 00:48:34', 0, 1, '2026-09-25 16:43:34'),
(2, 18, '$2y$10$syRCcslRecWvjpajjMpi9ePfy9Uy32MDH3WvrRQlX5jwHQ04Mcumy', '2026-09-26 00:49:13', 0, 1, '2026-09-25 16:44:13'),
(3, 1, '$2y$10$QqVJfd.0.2VycwQuLypY6OQGNXJ7YC8k6rJ5Zsf/pN4yel3hzTkwW', '2026-09-26 00:51:41', 0, 1, '2026-09-25 16:46:41'),
(4, 1, '$2y$10$.zyjUDLMuXlgji2kDs4MYeJDjIb0MCP.SZWGOVYfuxmBOzGSSxm2S', '2026-10-05 15:52:00', 0, 1, '2026-10-05 07:47:00'),
(5, 18, '$2y$10$v.8DP/i6gP8bO7mmBB9HQ.JkuiY2kdtT/6VXP3Ahe5W5PKoWz0GL.', '2026-10-05 16:00:13', 0, 1, '2026-10-05 07:55:13'),
(6, 1, '$2y$10$AdWb0.VoiTvYNRLHEIi6f.Gd4V15mtwG1J7705aR//b0shM/twoLm', '2026-10-05 17:54:00', 0, 1, '2026-10-05 09:49:00'),
(7, 18, '$2y$10$71INC4O6mMQWMmT5mzBfq.MYd7pldx6BkB7KFCyi0FRAlaKLugtOy', '2026-10-05 18:49:38', 0, 1, '2026-10-05 10:44:38'),
(8, 1, '$2y$10$b4oG9iKMiUpdemE4c/GtyeACjrEUT8gFlfvV4FHTUBIE6o4995IUS', '2026-10-05 18:58:20', 0, 1, '2026-10-05 10:53:20'),
(9, 1, '$2y$10$e3UZ5NxOXzTv9kmJOxMim.voCY/UUDubILGE6a38zmq/3PTyPuAOO', '2026-10-06 12:21:55', 0, 1, '2026-10-06 04:16:55'),
(10, 18, '$2y$10$qW921Tf9VvzSygZsxVQCSO022XAhQhJEGSBFHQ9YaSJUyPPjCQztO', '2026-10-06 12:23:34', 0, 1, '2026-10-06 04:18:34'),
(11, 1, '$2y$10$7zEeoNEpjMqCbafnhiMh2uNXZdIGel3amAGvWtGbmBbFBMG8fmGT6', '2026-10-09 16:07:19', 0, 1, '2026-10-09 08:02:19'),
(12, 18, '$2y$10$jeC0vbsaaM7qX75Cwzm9PeCfCf.9KrsLotGoMjlRD.w/bwAAXbw9y', '2026-10-13 18:30:51', 0, 1, '2026-10-13 10:25:51'),
(13, 18, '$2y$10$2mnxNC6uVWhA60TFOT7cyeB/FQfEGxxvX9DIphKn1JjXsbOKcDB0W', '2026-10-16 12:44:05', 0, 1, '2026-10-16 04:39:05'),
(14, 1, '$2y$10$Ca8Regq9/mQltRiBnwr6G.klMvVw927PrxscqiBT/TNka26Db/MZe', '2026-10-16 13:13:00', 0, 1, '2026-10-16 05:08:00'),
(15, 18, '$2y$10$n2qZXLFITQyfRsawMN/JgOFAWW8TaDtPRMXD/khuePO.r8dq/xQnm', '2026-10-16 14:35:00', 0, 1, '2026-10-16 06:30:00'),
(16, 18, '$2y$10$k6QWOJZ8.3XZOCqrnUB3t.cfkwN6al9zJSIndA8p3U64CXVCJjMV2', '2026-10-16 15:00:19', 0, 1, '2026-10-16 06:55:19'),
(17, 18, '$2y$10$LOsxeOA0dlWja1xZAB/MOObbIy0bToLv69Qpl1lZhwgh9pML0YN5O', '2026-10-21 10:27:22', 0, 1, '2026-10-21 02:22:22'),
(18, 18, '$2y$10$B1SxGRwb6VD6nTlrj3tz0ujl1mKKxW3QQf50AYsYdAbd8KifXsWda', '2026-10-23 20:16:53', 2, 1, '2026-10-23 12:11:53'),
(19, 20, '$2y$10$ksclf3L/mvZByMha6FLKEeOJzlWecTYXxuSU6RYpRfkowafUPagtO', '2026-10-23 21:26:25', 0, 1, '2026-10-23 13:21:25'),
(20, 18, '$2y$10$iho2QnY5GmvDu4LTkj.t1eu0YMynID3.3VoUzzXXQ/nGHWWo85EzC', '2026-11-06 21:43:59', 0, 1, '2026-11-06 13:38:59'),
(21, 21, '$2y$10$eO79LkicGv5X0zjOzyD8ieBN.P9xCEuFeWJfujV73afX7zz3v14.y', '2026-11-06 22:12:06', 0, 1, '2026-11-06 14:07:06'),
(22, 18, '$2y$10$TVST6jjbNm8uldnEv.tB1eioA4YPMS2qFOGa9SmjHDBoclYgxPB2G', '2026-11-06 22:23:57', 0, 1, '2026-11-06 14:18:57'),
(23, 1, '$2y$10$AXKpNNk3bGTORekffTqBkO9YQ0lJfiipXWv9YIi5oqE18JyAxPfbS', '2026-11-06 22:28:59', 0, 1, '2026-11-06 14:23:59'),
(24, 21, '$2y$10$WauHuA37IG0Gbc.hURU0/.52is.W9CdeeFxbPifGdbGTe2711olhS', '2026-11-06 22:30:50', 0, 1, '2026-11-06 14:25:50'),
(25, 1, '$2y$10$QENsH6V4WriB/j4ywOCD0uTn88k7aK5BxQ7thhlFBiXjt3qe48asW', '2026-11-06 22:33:37', 0, 1, '2026-11-06 14:28:37');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_id`, `is_read`, `created_at`) VALUES
(1, 18, 'Job Posting Approved', 'Your job posting \"Test\" has been approved and is now visible to applicants.', 'job_status', 24, 1, '2026-11-06 14:20:47'),
(2, 18, 'Job Posting Approved', 'Your job posting \"TEST JOBS\" has been approved and is now visible to applicants.', 'job_status', 25, 1, '2026-11-06 14:41:00');

-- --------------------------------------------------------

--
-- Table structure for table `resumes`
--

CREATE TABLE `resumes` (
  `id` int(10) UNSIGNED NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `extracted_text` longtext DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resumes`
--

INSERT INTO `resumes` (`id`, `applicant_id`, `file_name`, `file_path`, `extracted_text`, `file_type`, `uploaded_at`) VALUES
(23, 1, 'Caloocan_City_Resume_Template (2).pdf', 'uploads/resumes/resume_1_1793976367_530a7eb0.pdf', 'CITY OF CALOOCAN\nCALOOCAN JOB PORTAL - APPLICANT RESUME TEMPLATE\nRESUME / BIO-DATA\nInstructions: Fill in the information below, save or print this form as a PDF, then upload it to your Caloocan Job Portal account. For resume\nmatching, use a text-based PDF instead of a scanned image.\nPERSONAL INFORMATION\nFull Name\nCurrent Address\nBarangay\nMobile Number\nEmail Address\nDate of Birth\nCAREER OBJECTIVE\nEDUCATIONAL BACKGROUND\nLevel	School / Institution	Course / Strand	Year Graduated\nCollege / Vocational\nSenior High School\nJunior High School\nElementary\n\nCITY OF CALOOCAN\nCALOOCAN JOB PORTAL - APPLICANT RESUME TEMPLATE\nWORK EXPERIENCE\nCompany / Employer	Position	Inclusive Dates\nSKILLS AND COMPETENCIES\n1.\n2.\n3.\n4.\n5.\nTRAININGS / SEMINARS / CERTIFICATIONS\nTraining / Certification	Organization	Date\nCHARACTER REFERENCES\nName	Position / Relationship	Contact Number\nDECLARATION\nI hereby certify that the information provided in this resume is true and correct to the best of my knowledge.\n_______________________________	____________________\nApplicant Signature	Date\nReminder: Upload a text-based PDF so the Caloocan Job Portal can extract your resume information for job matching.', 'application/pdf', '2026-11-06 14:46:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','employer','applicant') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `is_active`, `created_at`, `updated_at`, `reset_token`, `reset_token_expires_at`) VALUES
(1, 'cerdenamarcahron@gmail.com', '$2y$10$x53JIADre./we9FVmNCGseDHsvBCAg1CVxa77ioaMfGcvExLYLR/2', 'applicant', 1, '2026-09-08 10:32:10', '2026-09-12 07:35:14', NULL, NULL),
(3, 'admin@jobportal.com', '$2y$10$uetves5puBqq3BgoDP6K1ehvlx.yN4CfTMnezjLLDJBeSciG8sURK', 'admin', 1, '2026-09-08 11:15:32', '2026-09-08 11:15:32', NULL, NULL),
(12, 'reneboyandoc028@gmail.com', '$2y$10$KkMNA6/ULKklewc9QBgpzezzJN1pwnj/YHb2tSQyro1tqI7Mn6r3u', 'employer', 1, '2026-09-12 07:30:22', '2026-09-12 07:30:22', NULL, NULL),
(13, 'marccruz@gmail.com', '$2y$10$Sq2nAnO.S9c2dN4g1tQdse.yUv.VF2kFv91Kd7y6hlU1v.It0.G.G', 'employer', 1, '2026-09-12 08:07:31', '2026-09-12 08:07:31', NULL, NULL),
(15, 'canalesangelica122@gmail.com', '$2y$10$mKVTuc.V78U6oj1Q1N8qCOG5MBlS7juS7xy6KPknAy3URBxnvtHMy', 'employer', 1, '2026-09-19 07:07:11', '2026-09-19 07:07:11', NULL, NULL),
(16, 'magleo@gmail.com', '$2y$10$hbA/nnn2/v0zS0xF3.7n8.RYZTnaSa9yZRTw9oVYWqbKdK.bZ3Pey', 'employer', 1, '2026-09-19 07:16:15', '2026-09-19 07:16:15', NULL, NULL),
(17, 'gravoso@gmail.com', '$2y$10$GM6WdnozNJWmnDkqnsGbfeWU9y19bExzTZR0skXsu2Neh4SSZ8x2a', 'employer', 1, '2026-09-19 07:57:19', '2026-09-19 07:57:19', NULL, NULL),
(18, 'gilbertaaronadane26@gmail.com', '$2y$10$OUoirwcRyG0p1hJLep9XAu6xGLKMmOy1yJocSXmAKNLmMUVj/FCF6', 'employer', 1, '2026-09-25 09:44:40', '2026-09-25 09:44:40', NULL, NULL),
(19, 'rhondaanna.it@gmail.com', '$2y$10$AN1SbZJsqGlIQcrLvrdt8.TouaAe1LTGV9ggCd0CrE2MuxuIlviKK', 'employer', 1, '2026-09-25 13:02:27', '2026-09-25 13:02:27', NULL, NULL),
(20, 'gilbertaaronadane0426@gmail.com', '$2y$10$VvUNy8ORLqtcb/GZKE2M8OA9TyW/obupQYunerUfCDD7NQ2qBQNQu', 'applicant', 1, '2026-10-23 13:21:13', '2026-10-23 13:21:13', NULL, NULL),
(21, 'gilbertaarona@gmail.com', '$2y$10$4pnLsmpuPN4wdQGrVU21nu5ZLp0yASp0pgHV8hEFBiOD./h0L7Rei', 'applicant', 1, '2026-11-06 14:06:54', '2026-11-06 14:06:54', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_applicant_user` (`user_id`);

--
-- Indexes for table `applicant_education`
--
ALTER TABLE `applicant_education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_education_applicant_id` (`applicant_id`);

--
-- Indexes for table `applicant_tesda_qualifications`
--
ALTER TABLE `applicant_tesda_qualifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tesda_applicant` (`applicant_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_job_applicant` (`job_id`,`applicant_id`),
  ADD KEY `fk_applications_resume` (`resume_id`),
  ADD KEY `idx_applications_job` (`job_id`),
  ADD KEY `idx_applications_applicant` (`applicant_id`),
  ADD KEY `idx_applications_status` (`status`);

--
-- Indexes for table `application_matches`
--
ALTER TABLE `application_matches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_application_match` (`application_id`);

--
-- Indexes for table `application_selected_skills`
--
ALTER TABLE `application_selected_skills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_application_id` (`application_id`);

--
-- Indexes for table `employers`
--
ALTER TABLE `employers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employer_user` (`user_id`);

--
-- Indexes for table `employer_verifications`
--
ALTER TABLE `employer_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_employer_verification` (`employer_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jobs_status` (`status`),
  ADD KEY `idx_jobs_employer` (`employer_id`),
  ADD KEY `idx_jobs_title` (`job_title`);

--
-- Indexes for table `login_otps`
--
ALTER TABLE `login_otps`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_user` (`user_id`);

--
-- Indexes for table `resumes`
--
ALTER TABLE `resumes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resumes_applicant` (`applicant_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `applicant_education`
--
ALTER TABLE `applicant_education`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `applicant_tesda_qualifications`
--
ALTER TABLE `applicant_tesda_qualifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `application_matches`
--
ALTER TABLE `application_matches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `application_selected_skills`
--
ALTER TABLE `application_selected_skills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employers`
--
ALTER TABLE `employers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employer_verifications`
--
ALTER TABLE `employer_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `login_otps`
--
ALTER TABLE `login_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `resumes`
--
ALTER TABLE `resumes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `fk_applicants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applicant_education`
--
ALTER TABLE `applicant_education`
  ADD CONSTRAINT `fk_education_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `applicant_tesda_qualifications`
--
ALTER TABLE `applicant_tesda_qualifications`
  ADD CONSTRAINT `fk_tesda_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_applications_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_applications_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_applications_resume` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `application_matches`
--
ALTER TABLE `application_matches`
  ADD CONSTRAINT `fk_application_matches_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employers`
--
ALTER TABLE `employers`
  ADD CONSTRAINT `fk_employers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employer_verifications`
--
ALTER TABLE `employer_verifications`
  ADD CONSTRAINT `fk_employer_verification` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `fk_jobs_employer` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `resumes`
--
ALTER TABLE `resumes`
  ADD CONSTRAINT `fk_resumes_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
