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

namespace CreditAccount\Expiration;

use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Model\Map\CreditMovementTableMap;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

/**
 * Charges every debit of a customer to their credits, the one that expires first being spent first (a credit
 * that never expires, last, then the oldest). What no debit reached is what an expiry may take.
 *
 * A past expiration is a debit like any other: it consumes the lot it took, so a lot already expired has
 * nothing left and asking again finds nothing to expire.
 */
final readonly class CreditLots
{
    /**
     * @return list<CreditLot> the credits with something left, in the order they will be spent
     */
    public function unspentOf(int $customerId, ?ConnectionInterface $connection = null): array
    {
        $connection ??= Propel::getReadConnection(CreditMovementTableMap::DATABASE_NAME);

        $debits = $connection->prepare('SELECT COALESCE(SUM(-`amount`), 0) FROM `credit_movement` WHERE `customer_id` = :customer AND `amount` < 0');
        $debits->bindValue(':customer', $customerId, \PDO::PARAM_INT);
        $debits->execute();
        $toCharge = CreditAmount::ofDecimal((string) $debits->fetchColumn());

        $credits = $connection->prepare(
            'SELECT `id`, `amount`, `expires_at`, `warned_at` FROM `credit_movement`
             WHERE `customer_id` = :customer AND `amount` > 0
             ORDER BY `expires_at` IS NULL, `expires_at`, `id`'
        );
        $credits->bindValue(':customer', $customerId, \PDO::PARAM_INT);
        $credits->execute();

        $lots = [];

        foreach ($credits->fetchAll(\PDO::FETCH_NUM) as [$id, $amount, $expiresAt, $warnedAt]) {
            $original = CreditAmount::ofDecimal((string) $amount);
            $charged = CreditAmount::min($original, $toCharge);
            $toCharge = $toCharge->minus($charged);
            $remaining = $original->minus($charged);

            if ($remaining->isZero()) {
                continue;
            }

            $lots[] = new CreditLot(
                (int) $id,
                $original,
                $remaining,
                null === $expiresAt ? null : new \DateTimeImmutable((string) $expiresAt),
                null !== $warnedAt,
            );
        }

        return $lots;
    }
}
