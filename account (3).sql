-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 01, 2025 at 06:29 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `account`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `acc_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `acc_name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `acc_role` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`acc_id`, `acc_name`, `password`, `acc_role`) VALUES
('991', 'try try', '$2y$10$snIHO3uk2I2V9e7jCR3Vy.e7AOI8mB7MnM/UG1FWWwYT/Qb8aAtmq', 'student'),
('a10', 'Eye Almond AI', '$2y$10$0iKCC2rbzHeLOabxOYtlIuj.lVFWysEr2wY/GTIcg2.nld/1xIk6C', 'student'),
('ac-1011', 'Student Testing', '$2y$10$RdvCfqKbUs9YV1Y1Zg9hzOOmChpUk8638cxFC7lut9HgAhcq8y6JG', 'student'),
('ac-1011_1', 'Student Testing', '$2y$10$NS5IAoffYEMjsDVmZrKYaOMBjjx9qZmClCUH0l5CjpvKaKpYICuLK', 'student'),
('ac-130', 'Testing, aaaaaaaaaaaa', '$2y$10$MyD7UeYOtSdcZeI0CTFXAedEDsOlPEEdRnYYlAIA9AK4kJgSwUKoa', 'student'),
('ac130', '01 Student', '$2y$10$se1qm6T9Qk3N155XzeSGzuxecWn7x.g6SdxMVD4nFC9mnuGBa1dr6', 'student'),
('deadeye', 'John Marston', '$2y$10$DdL1SZxaMaP3OWBn6uZ5tewIWq7AFKL30Przl4V8y6vlETDwem58a', 'student'),
('potoooooooo', 'Potato', '$2y$10$HsXOuiVBxiEtHd1WLGEew.XdLiIuCBrmQupGXF3optNPFSFENm.IS', 'admin'),
('qwerty', 'sdfa fds', '$2y$10$VF8q21l5345u4JRY2DenJelOJGV9PEDYfFX613ktCLPsiql4ADVA6', 'student'),
('SIL', 'In Love Still', '$2y$10$Oi/9XMM6xoXqRvUtgmepuep7gmHrEZ/q5OlXorp4TI3xEMGHpEXre', 'student');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `acc_id` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `avatar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `acc_id`, `password_hash`, `name`, `role`, `created_at`, `avatar`) VALUES
(2, 'Alyson', '$2y$10$MV1fvqMdyiQ3m34B0h3vaeP6Q86nlRLvCVp7tmKBbbalITzQsdNCq', 'ALY', 'admin', '2025-11-28 09:10:42', NULL),
(3, 'ac137', '1010', 'fda approved', 'admin', '2025-11-28 14:27:21', 'uploads/avatars/avatar_692dcbc2d54962.15747300.png'),
(4, '951', '1010', 'Carlotta Montelli', 'super_admin', '2025-12-01 14:39:50', NULL),
(6, 'potoooooooo', '', 'Potato', 'admin', '2025-12-01 17:22:51', 'uploads/avatars/avatar_692dceeb25e548.13048906.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`acc_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `acc_id` (`acc_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
