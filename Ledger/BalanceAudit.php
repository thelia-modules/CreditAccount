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

namespace CreditAccount\Ledger;

use CreditAccount\Model\Map\CreditMovementTableMap;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

/**
 * Compares the balance each account keeps in cache with the sum of its movements, the one that counts.
 */
final readonly class BalanceAudit
{
    /**
     * @return list<array{customerId: int, cached: CreditAmount, summed: CreditAmount}> the accounts whose cache is wrong
     */
    public function gaps(?ConnectionInterface $connection = null): array
    {
        $connection ??= Propel::getReadConnection(CreditMovementTableMap::DATABASE_NAME);

        $statement = $connection->query(
            'SELECT `account`.`customer_id`, `account`.`amount`, COALESCE(`summed`.`total`, 0) AS `total`
             FROM `credit_account` `account`
             LEFT JOIN (SELECT `customer_id`, SUM(`amount`) AS `total` FROM `credit_movement` GROUP BY `customer_id`) `summed`
                 ON `summed`.`customer_id` = `account`.`customer_id`
             WHERE `account`.`amount` <> COALESCE(`summed`.`total`, 0)
             ORDER BY `account`.`customer_id`'
        );

        $gaps = [];

        foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$customerId, $cached, $summed]) {
            $gaps[] = [
                'customerId' => (int) $customerId,
                'cached' => CreditAmount::ofDecimal((string) $cached),
                'summed' => CreditAmount::ofDecimal((string) $summed),
            ];
        }

        return $gaps;
    }

    /**
     * Writes the sum of the movements into the cache of the accounts that disagree with it.
     */
    public function fix(?ConnectionInterface $connection = null): int
    {
        $connection ??= Propel::getWriteConnection(CreditMovementTableMap::DATABASE_NAME);

        return $connection->exec(
            'UPDATE `credit_account` `account`
             LEFT JOIN (SELECT `customer_id`, SUM(`amount`) AS `total` FROM `credit_movement` GROUP BY `customer_id`) `summed`
                 ON `summed`.`customer_id` = `account`.`customer_id`
             SET `account`.`amount` = COALESCE(`summed`.`total`, 0), `account`.`updated_at` = NOW()
             WHERE `account`.`amount` <> COALESCE(`summed`.`total`, 0)'
        );
    }
}
