-- ============================================================
-- Movie Cinema Sale - complete database (fresh install)
-- Database: booking_dtb
--
-- HOW TO USE: phpMyAdmin > Import tab > choose this file > Go.
-- WARNING: this DROPS and recreates the movies, users and bookings
-- tables, so export a backup first if you have bookings you want to keep.
-- Do NOT run it after booking_dtb_update.sql / booking_dtb_update_2.sql,
-- because this file already contains everything those two files add.
--
-- Demo accounts (password for all three: Demo123!)
--   superadmin@moviecinema.demo   Super admin
--   admin@moviecinema.demo        Admin
--   user@moviecinema.demo         User
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `booking_dtb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `booking_dtb`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `movies`;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- movies
-- ids match the Book Now links on movies.php and the files in assets/
-- ------------------------------------------------------------
CREATE TABLE `movies` (
  `id` varchar(20) NOT NULL,
  `title` varchar(150) NOT NULL,
  `poster` varchar(100) NOT NULL,
  `release_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `movies` (`id`, `title`, `poster`, `release_date`) VALUES
('avgd', 'Avengers: Doomsday', 'assets/avgd.jpg', '2026-12-18'),
('avgen', 'Avengers: Endgame (Encore)', 'assets/avgen.jpg', '2026-09-25'),
('cf', 'Clayface', 'assets/cf.jpg', '2026-10-23'),
('chkw', 'Chiikawa the Movie: The Secret of Mermaid Island', 'assets/chkw.jpg', '2026-07-24'),
('dconan', 'Detective Conan: Fallen Angel of the Highway', 'assets/dconan.png', '2026-04-10'),
('f2', 'Fall 2: Deadpoint', 'assets/f2.jpg', '2026-08-07'),
('insid', 'Insidious: Out of the Further', 'assets/insid.jpg', '2026-08-21'),
('rsd', 'Resident Evil', 'assets/rsd.jpg', '2026-09-18'),
('sbnd', 'Spider-Man: Brand New Day', 'assets/sbnd.jpg', '2026-07-31'),
('dnp3', 'Dune: Part Three', 'assets/dnp3.jpg', '2026-12-18');

-- ------------------------------------------------------------
-- users (login, registration, roles)
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('SUPERADMIN','ADMIN','USER') NOT NULL DEFAULT 'USER',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- passwords are bcrypt hashes of: Demo123!
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`) VALUES
(1, 'Demo Superadmin', 'superadmin@moviecinema.demo', '$2y$10$JDg6oO56Tm/N1q4g5xQWDe0wq2YBwoJEu5ETNad6OA7XpF4pvkdce', 'SUPERADMIN'),
(2, 'Demo Admin',      'admin@moviecinema.demo',      '$2y$10$01G28pGQ05WXGy6HQvrkBuE7iDlgh3UhAWEUeNsRqWAMvGbZniJNe', 'ADMIN'),
(3, 'Demo User',       'user@moviecinema.demo',       '$2y$10$iKyPwgZQ1HAyPlizll6pI.bBoT0xNb0ru2bXUE3bF79htiE2QEPua', 'USER');

ALTER TABLE `users` AUTO_INCREMENT = 4;

-- ------------------------------------------------------------
-- bookings
-- user_id is NULL for walk-in bookings entered by staff
-- ------------------------------------------------------------
CREATE TABLE `bookings` (
  `id` varchar(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `movie_id` varchar(20) NOT NULL,
  `movie_title` varchar(150) NOT NULL,
  `show_date` date NOT NULL,
  `showtime` varchar(10) NOT NULL,
  `screen` varchar(20) NOT NULL,
  `seats` varchar(100) NOT NULL,
  `ticket_count` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `email` varchar(80) DEFAULT NULL,
  `mobile` varchar(13) DEFAULT NULL,
  `ticket_type` enum('REGULAR','STUDENT','SENIOR','PWD') NOT NULL DEFAULT 'REGULAR',
  `concession_id` varchar(20) DEFAULT NULL,
  `notes` varchar(200) DEFAULT NULL,
  `price_per_ticket` decimal(10,2) NOT NULL,
  `status` enum('PENDING','CONFIRMED','CHECKED_IN','COMPLETED','CANCELLED','NO_SHOW') NOT NULL DEFAULT 'PENDING',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bookings_user` (`user_id`),
  KEY `fk_bookings_movie` (`movie_id`),
  CONSTRAINT `fk_bookings_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- BK-1001 to BK-1004 belong to the demo User so their dashboard has data.
INSERT INTO `bookings` (`id`, `user_id`, `movie_id`, `movie_title`, `show_date`, `showtime`, `screen`, `seats`, `ticket_count`, `customer_name`, `price_per_ticket`, `status`) VALUES
('BK-1001', 3, 'avgd', 'Avengers: Doomsday', '2026-12-18', '7:00 PM', 'Screen 1', 'A1,A2', 2, 'Kathryn Bernardo', 380.00, 'PENDING'),
('BK-1002', 3, 'avgd', 'Avengers: Doomsday', '2026-12-18', '9:00 PM', 'Screen 2', 'C2,C3,C4', 3, 'Daniel Padilla', 450.00, 'CONFIRMED'),
('BK-1003', 3, 'avgen', 'Avengers: Endgame (Encore)', '2026-12-19', '3:00 PM', 'Screen 3', 'B3,B4', 2, 'Liza Soberano', 350.00, 'CONFIRMED'),
('BK-1004', 3, 'cf', 'Clayface', '2026-12-19', '5:00 PM', 'Screen 4', 'D3', 1, 'Enrique Gil', 380.00, 'PENDING'),
('BK-1005', NULL, 'chkw', 'Chiikawa the Movie: The Secret of Mermaid Island', '2026-12-20', '3:00 PM', 'Screen 1', 'A1,A2,A3,A4', 4, 'Maria Clara Reyes', 320.00, 'CONFIRMED'),
('BK-1006', NULL, 'dconan', 'Detective Conan: Fallen Angel of the Highway', '2026-12-20', '5:00 PM', 'Screen 2', 'B2,B3', 2, 'Jose Miguel Santos', 320.00, 'PENDING'),
('BK-1007', NULL, 'f2', 'Fall 2: Deadpoint', '2026-12-21', '7:00 PM', 'Screen 3', 'C4,C5', 2, 'Angelica Cruz', 350.00, 'PENDING'),
('BK-1008', NULL, 'insid', 'Insidious: Out of the Further', '2026-12-21', '9:00 PM', 'Screen 4', 'D1,D2,D3', 3, 'Mark Anthony Dela Cruz', 380.00, 'CONFIRMED'),
('BK-1009', NULL, 'rsd', 'Resident Evil', '2026-12-22', '9:00 PM', 'Screen 1', 'B1,B2', 2, 'Carla Mendoza', 380.00, 'PENDING'),
('BK-1010', NULL, 'sbnd', 'Spider-Man: Brand New Day', '2026-12-22', '3:00 PM', 'Screen 2', 'A3,A4,A5', 3, 'Paolo Villanueva', 450.00, 'CONFIRMED'),
('BK-1011', NULL, 'dnp3', 'Dune: Part Three', '2026-12-23', '5:00 PM', 'Screen 3', 'C1,C2', 2, 'Bea Alonzo', 420.00, 'PENDING'),
('BK-1012', NULL, 'avgd', 'Avengers: Doomsday', '2026-12-23', '7:00 PM', 'Screen 4', 'D4,D5', 2, 'John Lloyd Garcia', 450.00, 'CONFIRMED'),
('BK-1013', NULL, 'sbnd', 'Spider-Man: Brand New Day', '2026-12-24', '3:00 PM', 'Screen 1', 'C3', 1, 'Andrea Ramos', 450.00, 'PENDING'),
('BK-1014', NULL, 'chkw', 'Chiikawa the Movie: The Secret of Mermaid Island', '2026-12-24', '5:00 PM', 'Screen 2', 'A1,A2,A3', 3, 'Patricia Navarro', 320.00, 'PENDING'),
('BK-1015', NULL, 'cf', 'Clayface', '2026-12-25', '7:00 PM', 'Screen 3', 'B4,B5', 2, 'Ramon Aquino', 380.00, 'CONFIRMED'),
('BK-1016', NULL, 'dnp3', 'Dune: Part Three', '2026-12-26', '9:00 PM', 'Screen 4', 'C1,C2,C3', 3, 'Christine Domingo', 420.00, 'CONFIRMED'),
('BK-1017', NULL, 'avgen', 'Avengers: Endgame (Encore)', '2026-12-27', '3:00 PM', 'Screen 1', 'D2,D3', 2, 'Miguel Torres', 350.00, 'CANCELLED'),
('BK-1018', NULL, 'dconan', 'Detective Conan: Fallen Angel of the Highway', '2026-12-27', '5:00 PM', 'Screen 2', 'B1,B2,B3,B4', 4, 'Jasmine Flores', 320.00, 'PENDING'),
('BK-1019', NULL, 'insid', 'Insidious: Out of the Further', '2026-12-28', '7:00 PM', 'Screen 3', 'A4,A5', 2, 'Gabriel Lim', 380.00, 'PENDING'),
('BK-1020', NULL, 'rsd', 'Resident Evil', '2026-12-29', '9:00 PM', 'Screen 4', 'D1,D2', 2, 'Isabella Castillo', 380.00, 'CONFIRMED');

SELECT * FROM users;
SELECT * FROM movies;
SELECT * FROM bookings;