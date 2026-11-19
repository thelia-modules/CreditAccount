# CreditAccount 4.0.0: the balance becomes the sum of a journal of movements.
# The tables of 3.x (credit_amount_history, credit_account_expiration) are kept: the module copies their
# rows into credit_movement right after this file (CreditAccount::update()), and never writes them again.

SET FOREIGN_KEY_CHECKS = 0;

UPDATE `credit_account` SET `amount` = 0 WHERE `amount` IS NULL;
ALTER TABLE `credit_account` MODIFY `amount` DECIMAL(16,6) DEFAULT 0 NOT NULL;
-- ---------------------------------------------------------------------
-- credit_movement
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `credit_movement`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_id` INTEGER,
    `kind` VARCHAR(32) NOT NULL,
    `amount` DECIMAL(16,6) NOT NULL,
    `reason` VARCHAR(255) DEFAULT '' NOT NULL,
    `author_type` VARCHAR(16) NOT NULL,
    `admin_id` INTEGER,
    `author_name` VARCHAR(255) DEFAULT '' NOT NULL,
    `order_id` INTEGER,
    `payment_part_id` INTEGER,
    `expires_at` DATETIME,
    `warned_at` DATETIME,
    `created_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_credit_movement_customer_id` (`customer_id`),
    INDEX `idx_credit_movement_expires_at` (`expires_at`),
    INDEX `fi_credit_movement_admin_id` (`admin_id`),
    INDEX `fi_credit_movement_order_id` (`order_id`),
    INDEX `idx_credit_movement_payment_part_id` (`payment_part_id`),
    CONSTRAINT `fk_credit_movement_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_credit_movement_admin_id`
        FOREIGN KEY (`admin_id`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_credit_movement_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- credit_cart_usage
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `credit_cart_usage`
(
    `cart_id` INTEGER NOT NULL,
    `amount` DECIMAL(16,6) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`cart_id`),
    CONSTRAINT `fk_credit_cart_usage_cart_id`
        FOREIGN KEY (`cart_id`)
        REFERENCES `cart` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- credit_loyalty_tier
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `credit_loyalty_tier`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `min` DECIMAL(16,6) NOT NULL,
    `max` DECIMAL(16,6),
    `amount` DECIMAL(16,6) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- credit_loyalty_excluded_product
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `credit_loyalty_excluded_product`
(
    `product_id` INTEGER NOT NULL,
    PRIMARY KEY (`product_id`),
    CONSTRAINT `fk_credit_loyalty_excluded_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- credit_loyalty_excluded_category
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `credit_loyalty_excluded_category`
(
    `category_id` INTEGER NOT NULL,
    PRIMARY KEY (`category_id`),
    CONSTRAINT `fk_credit_loyalty_excluded_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

# The right that guards the manual credit API, apart from the customer rights. A profile has to find it in the
# back office before it can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES ('module.creditaccount.manual-credit', NOW(), NOW());

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`)
    SELECT `id`, 'en_US', 'Credit account: manual credit and debit' FROM `resource` WHERE `code` = 'module.creditaccount.manual-credit';

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`)
    SELECT `id`, 'fr_FR', 'Compte crédit : crédit et débit manuels' FROM `resource` WHERE `code` = 'module.creditaccount.manual-credit';

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
