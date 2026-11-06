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
 * The balance of a customer, summed from their movements in one query.
 */
final readonly class CreditBalance
{
    public function of(int $customerId, ?ConnectionInterface $connection = null): CreditAmount
    {
        $connection ??= Propel::getReadConnection(CreditMovementTableMap::DATABASE_NAME);

        $statement = $connection->prepare('SELECT COALESCE(SUM(`amount`), 0) FROM `credit_movement` WHERE `customer_id` = :customer');
        $statement->bindValue(':customer', $customerId, \PDO::PARAM_INT);
        $statement->execute();

        return CreditAmount::ofDecimal((string) $statement->fetchColumn());
    }
}
