-- General log table example for history log:
CREATE TABLE `general_log` (
    `id` INT NOT NULL,
    `user_id` INT NULL DEFAULT NULL,
    `parent_id` INT NOT NULL,
    `correlation_id` VARCHAR(36) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL COMMENT 'uuidv4',
    `type` ENUM('user','item') CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
    `ip` VARCHAR(30) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
    `changes` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `timestamp` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB;
