-- Analytics Schema for MU Tracker
-- This file contains the SQL statements to create analytics tables
-- that are used by the analytics.php functions

-- Table for character history tracking
CREATE TABLE IF NOT EXISTS `character_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `character_id` int(11) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 0,
  `resets` int(11) NOT NULL DEFAULT 0,
  `grand_resets` int(11) NOT NULL DEFAULT 0,
  `location` varchar(100) DEFAULT 'Unknown',
  `status` enum('Unknown','Online','Offline','Error') NOT NULL DEFAULT 'Unknown',
  `day_of_month` tinyint(2) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL,
  `status_timestamp` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ch_character` (`character_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `character_history_ibfk_1` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_character_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for daily progress tracking
CREATE TABLE IF NOT EXISTS `daily_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `character_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `starting_level` int(11) NOT NULL DEFAULT 0,
  `ending_level` int(11) NOT NULL DEFAULT 0,
  `starting_resets` int(11) NOT NULL DEFAULT 0,
  `ending_resets` int(11) NOT NULL DEFAULT 0,
  `starting_grand_resets` int(11) NOT NULL DEFAULT 0,
  `ending_grand_resets` int(11) NOT NULL DEFAULT 0,
  `levels_gained` int(11) NOT NULL DEFAULT 0,
  `resets_gained` int(11) NOT NULL DEFAULT 0,
  `grand_resets_gained` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_character_date` (`character_id`,`date`),
  KEY `idx_daily_char_date` (`character_id`,`date`),
  KEY `fk_daily_progress_user` (`user_id`),
  CONSTRAINT `fk_daily_progress_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_daily_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for hourly analytics
CREATE TABLE IF NOT EXISTS `hourly_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `character_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `hour` tinyint(2) NOT NULL,
  `level_start` int(11) NOT NULL DEFAULT 0,
  `level_end` int(11) NOT NULL DEFAULT 0,
  `resets_start` int(11) NOT NULL DEFAULT 0,
  `resets_end` int(11) NOT NULL DEFAULT 0,
  `grand_resets_start` int(11) NOT NULL DEFAULT 0,
  `grand_resets_end` int(11) NOT NULL DEFAULT 0,
  `levels_gained` int(11) NOT NULL DEFAULT 0,
  `resets_gained` int(11) NOT NULL DEFAULT 0,
  `grand_resets_gained` int(11) NOT NULL DEFAULT 0,
  `status_changes` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_char_date_hour` (`character_id`,`date`,`hour`),
  KEY `idx_hourly_char_date` (`character_id`,`date`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_hourly_analytics_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hourly_analytics_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for level milestones tracking
CREATE TABLE IF NOT EXISTS `level_milestones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `character_id` int(11) NOT NULL,
  `milestone_type` enum('level','reset','grand_reset') NOT NULL,
  `old_value` int(11) NOT NULL DEFAULT 0,
  `new_value` int(11) NOT NULL DEFAULT 0,
  `achieved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_character_id` (`character_id`),
  KEY `fk_level_milestones_user` (`user_id`),
  CONSTRAINT `fk_level_milestones_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_level_milestones_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for period analytics (weekly/monthly)
CREATE TABLE IF NOT EXISTS `period_analytics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `character_id` int(11) NOT NULL,
  `period_type` enum('week','month') NOT NULL,
  `year` int(4) NOT NULL,
  `period_number` int(4) NOT NULL,
  `levels_gained` int(11) NOT NULL DEFAULT 0,
  `resets_gained` int(11) NOT NULL DEFAULT 0,
  `grand_resets_gained` int(11) NOT NULL DEFAULT 0,
  `active_days` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_character_period` (`character_id`,`period_type`,`year`,`period_number`),
  KEY `idx_period_char` (`character_id`),
  KEY `fk_period_analytics_user` (`user_id`),
  CONSTRAINT `fk_period_analytics_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_period_analytics_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
