-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 02, 2025 at 05:58 PM
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
-- Database: `escape_room_lab`
--

-- --------------------------------------------------------

--
-- Table structure for table `puzzles`
--

CREATE TABLE `puzzles` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `position_top` int(11) NOT NULL,
  `position_left` int(11) NOT NULL,
  `options` text NOT NULL,
  `correct_answer` varchar(20) NOT NULL,
  `max_attempts` int(11) DEFAULT 2,
  `order_num` int(11) NOT NULL DEFAULT 1,
  `hint` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `puzzles`
--

INSERT INTO `puzzles` (`id`, `room_id`, `title`, `description`, `emoji`, `position_top`, `position_left`, `options`, `correct_answer`, `max_attempts`, `order_num`, `hint`) VALUES
(1, 1, 'Chemische Test', 'Je ziet een reeks gekleurde vloeistoffen. Welke vloeistof geeft een groene kleur aan een vlam?', '🧪', 40, 20, '{\"A\":\"Rood (Lithiumchloride)\",\"B\":\"Groen (Koperchloride)\",\"C\":\"Paars (Kaliumchloride)\",\"D\":\"Geel (Natriumchloride)\"}', 'B', 2, 1, NULL),
(2, 1, 'Materiaalonderzoek', 'Op het computerscherm zie je een analyse van metalen. Welke vloeistof kan aluminium verzwakken en smelten bij kamertemperatuur?', '💻', 35, 65, '{\"A\":\"Water\",\"B\":\"Alcohol\",\"C\":\"Gallium\",\"D\":\"Azijnzuur\"}', 'C', 2, 2, NULL),
(3, 2, 'Chemische Reactie', 'Op een post-it bij de kluis staat: \"Element dat heftig reageert met water\". Welk element is dit?', '🔒', 60, 25, '{\"A\":\"Natrium (Na)\",\"B\":\"Zuurstof (O)\",\"C\":\"Helium (He)\",\"D\":\"IJzer (Fe)\"}', 'A', 2, 1, NULL),
(4, 2, 'Labwaarden', 'Het controlepaneel vraagt om de exacte temperatuur waarop water kookt op zeeniveau.', '🎛️', 30, 70, '{\"A\":\"0°C\",\"B\":\"100°C\",\"C\":\"50°C\",\"D\":\"200°C\"}', 'B', 2, 2, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `room_style` varchar(20) NOT NULL,
  `order_num` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `name`, `description`, `room_style`, `order_num`) VALUES
(1, 'Laboratorium', 'Een hightech laboratorium met diverse chemische stoffen en apparatuur. Ontdek de wetenschappelijke geheimen.', 'modern-lab', 1),
(2, 'Controleruimte', 'De centrale controleruimte met computersystemen. De uitgang is vergrendeld met wetenschappelijke codes.', 'control-room', 2);

-- --------------------------------------------------------

--
-- Table structure for table `solved_puzzles`
--

CREATE TABLE `solved_puzzles` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `puzzle_id` int(11) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `solved` tinyint(1) DEFAULT 0,
  `solved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `solved_puzzles`
--

INSERT INTO `solved_puzzles` (`id`, `team_id`, `puzzle_id`, `attempts`, `solved`, `solved_at`) VALUES
(1, 2, 1, 1, 1, '2025-05-31 18:18:19'),
(2, 2, 2, 1, 1, '2025-05-31 18:18:22'),
(3, 2, 3, 1, 1, '2025-05-31 18:18:23'),
(4, 2, 4, 1, 1, '2025-05-31 18:18:25');

-- --------------------------------------------------------

--
-- Table structure for table `teams`
--

CREATE TABLE `teams` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `start_time` int(11) DEFAULT NULL,
  `current_room` int(11) DEFAULT NULL,
  `escape_time` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teams`
--

INSERT INTO `teams` (`id`, `name`, `created_by`, `start_time`, `current_room`, `escape_time`, `created_at`) VALUES
(2, 'test123', 2, 1748715497, 2, 8, '2025-05-24 15:32:44');

-- --------------------------------------------------------

--
-- Table structure for table `team_members`
--

CREATE TABLE `team_members` (
  `id` int(11) NOT NULL,
  `team_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_captain` tinyint(1) DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `team_members`
--

INSERT INTO `team_members` (`id`, `team_id`, `user_id`, `is_captain`, `joined_at`) VALUES
(1, 2, 2, 1, '2025-05-24 15:32:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `created_at`) VALUES
(1, 'admin', 'admin123', 'admin@voorbeeld.nl', 'admin', '2025-05-24 15:20:23'),
(2, 'test1', 'test123', 'test@gmail.com', 'user', '2025-05-24 15:28:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `puzzles`
--
ALTER TABLE `puzzles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `solved_puzzles`
--
ALTER TABLE `solved_puzzles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_solve` (`team_id`,`puzzle_id`);

--
-- Indexes for table `teams`
--
ALTER TABLE `teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `team_members`
--
ALTER TABLE `team_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`team_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `puzzles`
--
ALTER TABLE `puzzles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `solved_puzzles`
--
ALTER TABLE `solved_puzzles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teams`
--
ALTER TABLE `teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `team_members`
--
ALTER TABLE `team_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `teams`
--
ALTER TABLE `teams`
  ADD CONSTRAINT `teams_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `team_members`
--
ALTER TABLE `team_members`
  ADD CONSTRAINT `team_members_ibfk_1` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `team_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
