
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- credit_account
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `credit_account`;

CREATE TABLE `credit_account`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `amount` FLOAT DEFAULT 0,
    `customer_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `FI_credit_account_customer_id` (`customer_id`),
    CONSTRAINT `fk_credit_account_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- credit_account_expiration
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `credit_account_expiration`;

CREATE TABLE `credit_account_expiration`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `credit_account_id` INTEGER,
    `expiration_start` DATETIME,
    `expiration_delay` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `FI_credit_account_expiration_credit_account_id` (`credit_account_id`),
    CONSTRAINT `fk_credit_account_expiration_credit_account_id`
        FOREIGN KEY (`credit_account_id`)
        REFERENCES `credit_account` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- credit_amount_history
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `credit_amount_history`;

CREATE TABLE `credit_amount_history`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `credit_account_id` INTEGER,
    `amount` DOUBLE DEFAULT 0,
    `who` VARCHAR(255) DEFAULT '',
    `order_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `FI_credit_amount_history_credit_account_id` (`credit_account_id`),
    CONSTRAINT `fk_credit_amount_history_credit_account_id`
        FOREIGN KEY (`credit_account_id`)
        REFERENCES `credit_account` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
