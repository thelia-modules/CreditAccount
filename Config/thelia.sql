
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- credit_account
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `credit_account`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `amount` FLOAT DEFAULT 0,
    `customer_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- credit_amount_history
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `credit_amount_history`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `credit_account_id` INTEGER,
    `amount` FLOAT DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `FI_credit_amount_history_credit_account_id` (`credit_account_id`),
    CONSTRAINT `fk_credit_amount_history_credit_account_id`
        FOREIGN KEY (`credit_account_id`)
        REFERENCES `credit_account` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
