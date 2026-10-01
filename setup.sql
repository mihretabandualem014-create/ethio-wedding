-- ============================================================
-- Wedding Website — Database Setup
-- Run this in cPanel > phpMyAdmin on your wedding database.
-- ============================================================

CREATE TABLE IF NOT EXISTS `wishes` (
  `id`      INT          NOT NULL AUTO_INCREMENT,
  `name`    VARCHAR(255) NOT NULL,
  `message` TEXT         NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `photos` (
  `id`       INT          NOT NULL AUTO_INCREMENT,
  `filename` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guest_photos` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `filename`    VARCHAR(255) NOT NULL,
  `uploaded_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
