CREATE TABLE `bulk_export` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `exporter_id` INT DEFAULT NULL,
    `owner_id` INT DEFAULT NULL,
    `job_id` INT DEFAULT NULL,
    `comment` VARCHAR(190) DEFAULT NULL,
    `params` LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
    `filename` VARCHAR(760) DEFAULT NULL,
    INDEX `IDX_625A30FDB4523DE5` (`exporter_id`),
    INDEX `IDX_625A30FD7E3C61F9` (`owner_id`),
    UNIQUE INDEX `UNIQ_625A30FDBE04EA9` (`job_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE `bulk_exporter` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `owner_id` INT DEFAULT NULL,
    `label` VARCHAR(190) NOT NULL,
    `writer` VARCHAR(190) NOT NULL,
    `config` LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
    INDEX `IDX_6093500B7E3C61F9` (`owner_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE `bulk_shaper` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `owner_id` INT DEFAULT NULL,
    `label` VARCHAR(190) NOT NULL,
    `config` LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
    `created` DATETIME NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    INDEX `IDX_C40AB3ED7E3C61F9` (`owner_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE `bulk_export` ADD CONSTRAINT `FK_625A30FDB4523DE5` FOREIGN KEY (`exporter_id`) REFERENCES `bulk_exporter` (`id`) ON DELETE SET NULL;
ALTER TABLE `bulk_export` ADD CONSTRAINT `FK_625A30FD7E3C61F9` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE SET NULL;
ALTER TABLE `bulk_export` ADD CONSTRAINT `FK_625A30FDBE04EA9` FOREIGN KEY (`job_id`) REFERENCES `job` (`id`) ON DELETE SET NULL;
ALTER TABLE `bulk_exporter` ADD CONSTRAINT `FK_6093500B7E3C61F9` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE SET NULL;
ALTER TABLE `bulk_shaper` ADD CONSTRAINT `FK_C40AB3ED7E3C61F9` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id`) ON DELETE SET NULL;

# Table to index value length without modifying core table.
# Kept in sync via SQL triggers for INSERT/UPDATE, and foreign key for DELETE.
CREATE TABLE `value_data` (
    `id` INT NOT NULL,
    `length` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_value_length` (`length`),
    CONSTRAINT `FK_value_data_value` FOREIGN KEY (`id`) REFERENCES `value` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

# Triggers to keep value_data synchronized with value table.
# IFNULL handles NULL values (length = 0).
CREATE TRIGGER `tr_value_data_insert` AFTER INSERT ON `value`
FOR EACH ROW
INSERT INTO `value_data` (`id`, `length`) VALUES (NEW.`id`, IFNULL(CHAR_LENGTH(NEW.`value`), 0));

CREATE TRIGGER `tr_value_data_update` AFTER UPDATE ON `value`
FOR EACH ROW
UPDATE `value_data` SET `length` = IFNULL(CHAR_LENGTH(NEW.`value`), 0) WHERE `id` = NEW.`id`;
