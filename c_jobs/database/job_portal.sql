-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 08, 2026 at 02:59 PM
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
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`id`, `user_id`, `first_name`, `last_name`, `phone`, `address`, `profile_picture`, `bio`, `created_at`, `updated_at`) VALUES
(1, 1, 'Marc Ahron', 'Cerdena', '09123456789', 'asdsadsa', NULL, 'sadsadsadsd', '2026-09-08 10:32:10', '2026-09-08 12:55:49');

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
(1, 1, 1, 1, 'Pwede po ba ako mag apply work?', 'reviewed', '2026-09-08 11:25:55', '2026-09-08 11:26:26'),
(2, 2, 1, 1, 'Hire nyo na ako please need ko work', 'shortlisted', '2026-09-08 12:16:27', '2026-09-08 12:17:30'),
(3, 3, 1, 1, 'Please Hire me', 'reviewed', '2026-09-08 12:51:57', '2026-09-08 12:52:17');

-- --------------------------------------------------------

--
-- Table structure for table `employers`
--

CREATE TABLE `employers` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_description` text DEFAULT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employers`
--

INSERT INTO `employers` (`id`, `user_id`, `company_name`, `company_description`, `company_logo`, `contact_person`, `contact_number`, `address`, `created_at`, `updated_at`) VALUES
(1, 2, 'Company 123', NULL, NULL, NULL, NULL, NULL, '2026-09-08 10:48:49', '2026-09-08 10:48:49');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(10) UNSIGNED NOT NULL,
  `employer_id` int(10) UNSIGNED NOT NULL,
  `job_title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `requirements` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `employment_type` enum('Full-time','Part-time','Contract','Internship','Freelance') NOT NULL DEFAULT 'Full-time',
  `salary_min` decimal(12,2) DEFAULT NULL,
  `salary_max` decimal(12,2) DEFAULT NULL,
  `is_remote` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','approved','rejected','closed') NOT NULL DEFAULT 'pending',
  `application_deadline` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `employer_id`, `job_title`, `description`, `requirements`, `location`, `employment_type`, `salary_min`, `salary_max`, `is_remote`, `status`, `application_deadline`, `created_at`, `updated_at`) VALUES
(1, 1, 'Taga Linis ng Pwet ni Mak Eron', 'Wala lang taga punas lang ng Tae nya tapos ipapakain kay Bump.', 'Basta may kamay', 'Valenzuela', 'Full-time', 10000.00, 100000.00, 0, 'approved', '2026-09-08', '2026-09-08 11:03:46', '2026-09-08 11:17:00'),
(2, 1, 'Junior PHP Develper', '**Job Description:**\r\n\r\nWe are looking for a motivated and detail-oriented Junior PHP Developer to join our development team. The ideal candidate should have basic knowledge of PHP, web development, and databases, and be willing to learn and improve their programming skills.\r\n\r\n**Responsibilities:**\r\n\r\n* Develop and maintain web applications using PHP\r\n* Write clean and organized PHP code\r\n* Assist in developing new website features and functionalities\r\n* Work with MySQL databases and perform basic database operations\r\n* Troubleshoot and fix basic bugs and coding issues\r\n* Test applications to ensure they work properly\r\n* Collaborate with senior developers and other team members\r\n* Learn and apply new technologies and development practices\r\n\r\n**Requirements:**\r\n\r\n* Basic knowledge of PHP and web development\r\n* Familiarity with HTML, CSS, and JavaScript\r\n* Basic knowledge of MySQL or other databases\r\n* Understanding of basic programming concepts\r\n* Willingness to learn and receive feedback\r\n* Good problem-solving and communication skills\r\n* Fresh graduates and entry-level applicants are welcome\r\n\r\n**Preferred Skills:**\r\n\r\n* Experience with Laravel is a plus\r\n* Familiarity with Git/GitHub is a plus\r\n* Basic knowledge of REST APIs is a plus\r\n\r\n**Work Setup:** Remote / Work From Home\r\n**Employment Type:** Full-time\r\n**Experience Level:** Junior / Entry Level', '**Requirements:**\r\n\r\n* Bachelor’s degree in Information Technology, Computer Science, or a related field\r\n* Basic to intermediate knowledge of PHP\r\n* Familiarity with HTML, CSS, and JavaScript\r\n* Basic knowledge of MySQL and database management\r\n* Understanding of object-oriented programming (OOP) is an advantage\r\n* Familiarity with Laravel or other PHP frameworks is a plus\r\n* Basic knowledge of Git/GitHub is an advantage\r\n* Good problem-solving and analytical skills\r\n* Strong attention to detail\r\n* Willingness to learn new technologies and improve programming skills\r\n* Ability to work independently and as part of a team\r\n* Good communication and time-management skills\r\n* Fresh graduates and applicants with limited professional experience are welcome to apply', 'Makati', 'Full-time', 20000.00, 50000.00, 0, 'approved', '2026-09-30', '2026-09-08 12:14:13', '2026-09-08 12:15:09'),
(3, 1, 'Senior PHP Developer', 'Basta Senior Developer', '5 years expriences', 'Caloocan', 'Full-time', 50000.00, 100000.00, 0, 'approved', '2026-09-30', '2026-09-08 12:50:01', '2026-09-08 12:50:46');

-- --------------------------------------------------------

--
-- Table structure for table `resumes`
--

CREATE TABLE `resumes` (
  `id` int(10) UNSIGNED NOT NULL,
  `applicant_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resumes`
--

INSERT INTO `resumes` (`id`, `applicant_id`, `file_name`, `file_path`, `file_type`, `uploaded_at`) VALUES
(1, 1, 'Resume_Gilbert Aaron Adane.pdf', 'uploads/resumes/resume_1_1788866734_bd844a21.pdf', 'application/pdf', '2026-09-08 11:25:34');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'cerdenamarcahron@gmail.com', '$2y$10$nj4KGtCNEMs.UQoYT5gFu.XPJ3KqS0Om7z6gCVUuI8XIrvQs3v0Z.', 'applicant', 1, '2026-09-08 10:32:10', '2026-09-08 10:32:10'),
(2, 'gilbertaaronadane26@gmail.com', '$2y$10$B4.yOED8GiHwq02255gEG.nwyKzL6HVoWmakB0jtIsUBMnIHjn/sS', 'employer', 1, '2026-09-08 10:48:49', '2026-09-08 10:48:49'),
(3, 'admin@jobportal.com', '$2y$10$uetves5puBqq3BgoDP6K1ehvlx.yN4CfTMnezjLLDJBeSciG8sURK', 'admin', 1, '2026-09-08 11:15:32', '2026-09-08 11:15:32');

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
-- Indexes for table `employers`
--
ALTER TABLE `employers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_employer_user` (`user_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jobs_status` (`status`),
  ADD KEY `idx_jobs_employer` (`employer_id`),
  ADD KEY `idx_jobs_title` (`job_title`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employers`
--
ALTER TABLE `employers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `resumes`
--
ALTER TABLE `resumes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `fk_applicants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_applications_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_applications_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_applications_resume` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employers`
--
ALTER TABLE `employers`
  ADD CONSTRAINT `fk_employers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `fk_jobs_employer` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resumes`
--
ALTER TABLE `resumes`
  ADD CONSTRAINT `fk_resumes_applicant` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
