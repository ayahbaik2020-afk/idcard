CREATE TABLE `plant_display_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plant_name` varchar(255) NOT NULL,
  `on_duty_photo_url` varchar(255) DEFAULT NULL,
  `on_duty_name` varchar(255) DEFAULT NULL,
  `on_duty_position` varchar(255) DEFAULT NULL,
  `on_duty_plant` varchar(255) DEFAULT NULL,
  `safety_video_url` varchar(255) DEFAULT NULL,
  `plant_information` text,
  `running_text` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plant_display_settings_plant_name_unique` (`plant_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
