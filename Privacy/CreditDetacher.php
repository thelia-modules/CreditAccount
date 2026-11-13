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

namespace CreditAccount\Privacy;

use CreditAccount\Model\Map\CreditMovementTableMap;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

/**
 * Cuts the journal from a customer: their movements stay, with their amount and their reason, attached to
 * nobody, and their account (the cached balance and the lock row) goes. Asked twice, the second does nothing.
 */
final readonly class CreditDetacher
{
    public function detach(int $customerId, ?ConnectionInterface $connection = null): void
    {
        $connection ??= Propel::getWriteConnection(CreditMovementTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            // Waits for a movement being written for this customer, and keeps the next ones out.
            $lock = $connection->prepare('SELECT `id` FROM `credit_account` WHERE `customer_id` = :customer FOR UPDATE');
            $lock->bindValue(':customer', $customerId, \PDO::PARAM_INT);
            $lock->execute();

            $detach = $connection->prepare('UPDATE `credit_movement` SET `customer_id` = NULL WHERE `customer_id` = :customer');
            $detach->bindValue(':customer', $customerId, \PDO::PARAM_INT);
            $detach->execute();

            $forget = $connection->prepare('DELETE FROM `credit_account` WHERE `customer_id` = :customer');
            $forget->bindValue(':customer', $customerId, \PDO::PARAM_INT);
            $forget->execute();

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}
