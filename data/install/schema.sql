CREATE TABLE bulk_import (
    id INT AUTO_INCREMENT NOT NULL,
    importer_id INT DEFAULT NULL,
    reader_params LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    processor_params LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    status VARCHAR(190) DEFAULT NULL,
    started DATETIME DEFAULT NULL,
    ended DATETIME DEFAULT NULL,
    INDEX IDX_BD98E8747FCFE58E (importer_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
CREATE TABLE bulk_importer (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(190) DEFAULT NULL,
    reader_name VARCHAR(190) DEFAULT NULL,
    reader_config LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    processor_name VARCHAR(190) DEFAULT NULL,
    processor_config LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE bulk_import ADD CONSTRAINT FK_BD98E8747FCFE58E FOREIGN KEY (importer_id) REFERENCES bulk_importer (id);
