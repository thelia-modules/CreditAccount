<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CreditAccount\Migration;

use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\MovementKind;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Copies the history of CreditAccount 3.x into the journal, once, when a shop updates to 4.0.
 *
 * Each row of credit_amount_history becomes an imported movement, rounded to the cent. When the balance a
 * 3.x account showed differs from the sum of its history, a carry movement closes the gap, dated from the
 * update: the customer keeps exactly the amount they saw. The single expiry date of a 3.x account lands on
 * its credits, so the balance still expires when it would have. The 3.x tables are read, never written.
 */
final readonly class LegacyLedgerImport
{
    public const CARRY_REASON = 'Balance carried over from CreditAccount 3';
    public const IMPORT_REASON = 'CreditAccount 3 history';

    public function import(ConnectionInterface $connection): LegacyImportReport
    {
        if (!$this->tableExists('credit_amount_history', $connection) || $this->alreadyImported($connection)) {
            return new LegacyImportReport(0, 0, 0);
        }

        $connection->beginTransaction();

        try {
            $imported = $this->importTheHistory($connection);
            $carried = $this->carryTheGaps($connection);
            $expiring = $this->tableExists('credit_account_expiration', $connection) ? $this->carryTheExpiryDates($connection) : 0;
            $this->keepOneAccountPerCustomer($connection);
            $this->cacheEveryBalance($connection);

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return new LegacyImportReport($imported, $carried, $expiring);
    }

    private function importTheHistory(ConnectionInterface $connection): int
    {
        return $connection->exec(\sprintf(
            "INSERT INTO `credit_movement` (`customer_id`, `kind`, `amount`, `reason`, `author_type`, `author_name`, `order_id`, `created_at`)
             SELECT `account`.`customer_id`, '%s', ROUND(`history`.`amount`, 2), '%s', '%s', LEFT(COALESCE(`history`.`who`, ''), 255),
                    (SELECT `placed`.`id` FROM `order` `placed` WHERE `placed`.`id` = `history`.`order_id`),
                    COALESCE(`history`.`created_at`, NOW())
             FROM `credit_amount_history` `history`
             JOIN `credit_account` `account` ON `account`.`id` = `history`.`credit_account_id`
             WHERE ROUND(`history`.`amount`, 2) <> 0
             ORDER BY `history`.`id`",
            MovementKind::Imported->value,
            self::IMPORT_REASON,
            Author::SYSTEM,
        ));
    }

    /**
     * The balance a customer saw is the one of their first account: 3.x read it with a findOne.
     */
    private function carryTheGaps(ConnectionInterface $connection): int
    {
        return $connection->exec(\sprintf(
            "INSERT INTO `credit_movement` (`customer_id`, `kind`, `amount`, `reason`, `author_type`, `author_name`, `created_at`)
             SELECT `shown`.`customer_id`, '%s', ROUND(`shown`.`amount`, 2) - COALESCE(`summed`.`total`, 0), '%s', '%s', '', NOW()
             FROM `credit_account` `shown`
             LEFT JOIN (
                 SELECT `customer_id`, SUM(`amount`) AS `total` FROM `credit_movement` GROUP BY `customer_id`
             ) `summed` ON `summed`.`customer_id` = `shown`.`customer_id`
             WHERE `shown`.`id` = (SELECT MIN(`first`.`id`) FROM `credit_account` `first` WHERE `first`.`customer_id` = `shown`.`customer_id`)
               AND ROUND(`shown`.`amount`, 2) - COALESCE(`summed`.`total`, 0) <> 0",
            MovementKind::BalanceCarry->value,
            self::CARRY_REASON,
            Author::SYSTEM,
        ));
    }

    /**
     * 3.x emptied the whole balance at one date per account: that date becomes the expiry of its credits.
     */
    private function carryTheExpiryDates(ConnectionInterface $connection): int
    {
        return $connection->exec(\sprintf(
            "UPDATE `credit_movement` `movement`
             JOIN (
                 SELECT `account`.`customer_id`, MIN(DATE_ADD(`expiration`.`expiration_start`, INTERVAL `expiration`.`expiration_delay` MONTH)) AS `expires_at`
                 FROM `credit_account_expiration` `expiration`
                 JOIN `credit_account` `account` ON `account`.`id` = `expiration`.`credit_account_id`
                 WHERE `expiration`.`expiration_start` IS NOT NULL AND `expiration`.`expiration_delay` > 0
                 GROUP BY `account`.`customer_id`
             ) `legacy` ON `legacy`.`customer_id` = `movement`.`customer_id`
             SET `movement`.`expires_at` = `legacy`.`expires_at`
             WHERE `movement`.`amount` > 0 AND `movement`.`expires_at` IS NULL AND `movement`.`kind` IN ('%s', '%s')",
            MovementKind::Imported->value,
            MovementKind::BalanceCarry->value,
        ));
    }

    private function keepOneAccountPerCustomer(ConnectionInterface $connection): void
    {
        $connection->exec(
            'DELETE `extra` FROM `credit_account` `extra`
             JOIN `credit_account` `first` ON `first`.`customer_id` = `extra`.`customer_id` AND `first`.`id` < `extra`.`id`'
        );
    }

    private function cacheEveryBalance(ConnectionInterface $connection): void
    {
        $connection->exec(
            'UPDATE `credit_account` `account`
             SET `account`.`amount` = (SELECT COALESCE(SUM(`movement`.`amount`), 0) FROM `credit_movement` `movement` WHERE `movement`.`customer_id` = `account`.`customer_id`)'
        );
    }

    private function alreadyImported(ConnectionInterface $connection): bool
    {
        $statement = $connection->prepare('SELECT COUNT(*) FROM `credit_movement` WHERE `kind` IN (:imported, :carry)');
        $statement->bindValue(':imported', MovementKind::Imported->value);
        $statement->bindValue(':carry', MovementKind::BalanceCarry->value);
        $statement->execute();

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * Asked by a query rather than by the schema, so a temporary table answers too.
     */
    private function tableExists(string $table, ConnectionInterface $connection): bool
    {
        try {
            $connection->query(\sprintf('SELECT 1 FROM `%s` LIMIT 0', $table));

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
