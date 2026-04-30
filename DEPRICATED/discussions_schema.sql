-- MySQL schema for discussions table
CREATE TABLE IF NOT EXISTS `discussions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `contact_id` VARCHAR(64) NOT NULL,
  `author` VARCHAR(128) NOT NULL,
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `entry_text` TEXT NOT NULL,
  `linked_opportunity_id` VARCHAR(64),
  `visibility` VARCHAR(32) DEFAULT 'private',
  INDEX (`contact_id`),
  INDEX (`linked_opportunity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
