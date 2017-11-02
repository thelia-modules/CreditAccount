
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

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

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
