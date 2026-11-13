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

use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\Map\CreditMovementTableMap;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Mailer\Exception\EmailNotSentException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\CustomerQuery;

/**
 * Expires the credit that reached its date unspent, and warns the customers whose credit is about to.
 *
 * A run handles a limited number of customers, the ones whose oldest expiry comes first: a customer is
 * handled when something was expired or a warning left for them. Running again continues where the last run
 * stopped, and running again after the last one does nothing.
 */
final readonly class CreditExpirer
{
    public const WARNING_MESSAGE = 'credit_account_expiration_warning';

    public function __construct(
        private CreditLedger $ledger,
        private CreditLots $lots,
        private CreditExpiry $expiry,
        private MailerFactory $mailer,
    ) {
    }

    public function run(\DateTimeImmutable $now, int $limit): ExpirationReport
    {
        $handled = 0;
        $expired = 0;
        $amount = CreditAmount::zero();
        $sent = 0;
        $failed = 0;

        if (!$this->expiry->isEnabled() || $limit < 1) {
            return new ExpirationReport(0, 0, $amount, 0, 0);
        }

        $warningDays = $this->expiry->warningDays();
        $horizon = $warningDays > 0 ? $now->modify(\sprintf('+%d days', $warningDays)) : $now;

        foreach ($this->candidates($now, $horizon) as $customerId) {
            if ($handled >= $limit) {
                break;
            }

            $expiredForThem = $this->expireFor($customerId, $now);
            $warning = $warningDays > 0 ? $this->warn($customerId, $now, $horizon, $warningDays) : null;

            $expired += \count($expiredForThem);

            foreach ($expiredForThem as $taken) {
                $amount = $amount->plus($taken);
            }

            $sent += true === $warning ? 1 : 0;
            $failed += false === $warning ? 1 : 0;

            if ([] !== $expiredForThem || true === $warning) {
                ++$handled;
            }
        }

        return new ExpirationReport($handled, $expired, $amount, $sent, $failed);
    }

    /**
     * @return list<int> customers with a credit expired or soon to be, and a balance
     */
    private function candidates(\DateTimeImmutable $now, \DateTimeImmutable $horizon): array
    {
        $statement = Propel::getReadConnection(CreditMovementTableMap::DATABASE_NAME)->prepare(
            'SELECT `movement`.`customer_id`
             FROM `credit_movement` `movement`
             INNER JOIN `credit_account` `account` ON `account`.`customer_id` = `movement`.`customer_id`
             WHERE `movement`.`amount` > 0 AND `movement`.`expires_at` IS NOT NULL AND `account`.`amount` > 0
               AND (`movement`.`expires_at` <= :now OR (`movement`.`warned_at` IS NULL AND `movement`.`expires_at` <= :horizon))
             GROUP BY `movement`.`customer_id`
             ORDER BY MIN(`movement`.`expires_at`), `movement`.`customer_id`'
        );
        $statement->bindValue(':now', $now->format('Y-m-d H:i:s'));
        $statement->bindValue(':horizon', $horizon->format('Y-m-d H:i:s'));
        $statement->execute();

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Takes what is left of every credit past its date, each in a movement of its own. The account stays locked
     * from the reading of the lots to the last movement: a spending cannot slip in between.
     *
     * @return list<CreditAmount> what each expiration took
     */
    private function expireFor(int $customerId, \DateTimeImmutable $now): array
    {
        $connection = Propel::getWriteConnection(CreditMovementTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->lockTheAccountOf($customerId, $connection);

            $taken = [];

            foreach ($this->lots->unspentOf($customerId, $connection) as $lot) {
                if (null === $lot->expiresAt || $lot->expiresAt > $now) {
                    continue;
                }

                $movement = $this->ledger->record(
                    $customerId,
                    $lot->remaining->negated(),
                    MovementKind::Expiration,
                    Author::system('expiration'),
                    \sprintf('Credit of %s expired unspent (movement #%d)', $lot->expiresAt->format('Y-m-d'), $lot->movementId),
                    capAtBalance: true,
                    connection: $connection,
                );

                if (null !== $movement) {
                    $taken[] = CreditAmount::ofDecimal((string) $movement->getAmount())->absolute();
                }
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        return $taken;
    }

    /**
     * One mail for every credit of the customer that expires within the delay and was not announced yet. A
     * credit is marked as announced once the mail is accepted; one the mailer refused is announced next run.
     *
     * @return bool|null null when there was nothing to announce
     */
    private function warn(int $customerId, \DateTimeImmutable $now, \DateTimeImmutable $horizon, int $warningDays): ?bool
    {
        $lots = array_values(array_filter(
            $this->lots->unspentOf($customerId),
            static fn (CreditLot $lot): bool => !$lot->warned && null !== $lot->expiresAt && $lot->expiresAt > $now && $lot->expiresAt <= $horizon,
        ));

        $customer = CustomerQuery::create()->findPk($customerId);

        if ([] === $lots || null === $customer) {
            return null;
        }

        $total = CreditAmount::zero();

        foreach ($lots as $lot) {
            $total = $total->plus($lot->remaining);
        }

        try {
            $this->mailer->sendEmailToCustomerOrFail(self::WARNING_MESSAGE, $customer, [
                'expiring_total' => $total->toDecimal(),
                'expiring_lots' => array_map(
                    static fn (CreditLot $lot): array => ['amount' => $lot->remaining->toDecimal(), 'expires_on' => $lot->expiresAt?->format('Y-m-d')],
                    $lots,
                ),
                'warning_days' => $warningDays,
            ]);
        } catch (EmailNotSentException) {
            return false;
        }

        $this->markAsAnnounced($lots, $now);

        return true;
    }

    /**
     * @param list<CreditLot> $lots
     */
    private function markAsAnnounced(array $lots, \DateTimeImmutable $now): void
    {
        $connection = Propel::getWriteConnection(CreditMovementTableMap::DATABASE_NAME);
        $placeholders = implode(', ', array_fill(0, \count($lots), '?'));

        $statement = $connection->prepare(\sprintf('UPDATE `credit_movement` SET `warned_at` = ? WHERE `id` IN (%s)', $placeholders));
        $statement->execute([$now->format('Y-m-d H:i:s'), ...array_map(static fn (CreditLot $lot): int => $lot->movementId, $lots)]);
    }

    private function lockTheAccountOf(int $customerId, ConnectionInterface $connection): void
    {
        $lock = $connection->prepare('SELECT `id` FROM `credit_account` WHERE `customer_id` = :customer FOR UPDATE');
        $lock->bindValue(':customer', $customerId, \PDO::PARAM_INT);
        $lock->execute();
    }
}
