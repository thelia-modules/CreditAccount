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

use CreditAccount\Model\CreditMovement;
use CreditAccount\Model\CreditMovementQuery;
use CreditAccount\Model\Map\CreditMovementTableMap;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\AdminLog;

/**
 * The only writer of the journal. Each movement is written under a lock on the account of its customer,
 * with the balance read again in the same transaction: two debits racing for the same balance are
 * served one after the other, and the second sees what the first left.
 *
 * Works without a request (payment return, console): nothing here reads a session.
 */
final readonly class CreditLedger
{
    public const ADMIN_LOG_RESOURCE = 'module.CreditAccount';

    public function __construct(
        private CreditBalance $balance,
        private ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * Writes one movement and returns it.
     *
     * A debit larger than the balance is refused, unless $capAtBalance: the debit is then cut to what is
     * left, and nothing is written when nothing is left (null). A movement tied to an order is written
     * once: asked again, the ledger hands back the movement already there. A spending or its refund is written
     * once per payment part instead, so an order paid again with another part spends again.
     *
     * A connection already inside a transaction (the one of the core while it settles a payment) is written on
     * without beginning, committing or rolling back anything: a refusal is thrown, and the caller rolls back.
     *
     * @throws InsufficientCreditException a debit the balance does not cover
     */
    public function record(
        int $customerId,
        CreditAmount $amount,
        MovementKind $kind,
        Author $author,
        string $reason = '',
        ?int $orderId = null,
        ?\DateTimeInterface $expiresAt = null,
        bool $capAtBalance = false,
        ?ConnectionInterface $connection = null,
        ?int $paymentPartId = null,
    ): ?CreditMovement {
        $reason = trim($reason);

        if ($amount->isZero()) {
            throw new \InvalidArgumentException('A movement moves something.');
        }

        if (MovementKind::Adjustment === $kind && '' === $reason) {
            throw new \InvalidArgumentException('A movement written by hand says why.');
        }

        if ($kind->isTiedToAnOrder() && null === $orderId) {
            throw new \InvalidArgumentException(\sprintf('A movement of kind "%s" belongs to an order.', $kind->value));
        }

        if ($kind->isTiedToAPaymentPart() && null === $paymentPartId) {
            throw new \InvalidArgumentException(\sprintf('A movement of kind "%s" belongs to a payment part.', $kind->value));
        }

        if (null !== $paymentPartId && !$kind->isTiedToAPaymentPart()) {
            throw new \InvalidArgumentException(\sprintf('A movement of kind "%s" does not belong to a payment part.', $kind->value));
        }

        if (null !== $expiresAt && $amount->isNegative()) {
            throw new \InvalidArgumentException('Only a credit expires.');
        }

        $connection ??= Propel::getWriteConnection(CreditMovementTableMap::DATABASE_NAME);
        $ownsTheTransaction = !$connection->inTransaction();

        if ($ownsTheTransaction) {
            $connection->beginTransaction();
        }

        try {
            $this->lockTheAccountOf($customerId, $connection);

            $existing = null !== $paymentPartId
                ? $this->movementOfThePart($paymentPartId, $kind, $connection)
                : (null !== $orderId ? $this->movementOfTheOrder($orderId, $kind, $connection) : null);

            if (null !== $existing) {
                if ($ownsTheTransaction) {
                    $connection->commit();
                }

                return $existing;
            }

            $balance = $this->balance->of($customerId, $connection);

            if ($amount->isNegative() && $amount->absolute()->isGreaterThan($balance)) {
                if (!$capAtBalance) {
                    throw new InsufficientCreditException($balance, $amount->absolute());
                }

                $reason = trim(\sprintf(
                    '%s (capped at the balance: %s of %s)',
                    $reason,
                    $balance->toDecimal(),
                    $amount->absolute()->toDecimal(),
                ));
                $amount = $balance->negated();
            }

            if ($amount->isZero()) {
                if ($ownsTheTransaction) {
                    $connection->commit();
                }

                return null;
            }

            $movement = (new CreditMovement())
                ->setCustomerId($customerId)
                ->setKind($kind->value)
                ->setAmount($amount->toDecimal())
                ->setReason(mb_substr($reason, 0, 255))
                ->setAuthorType($author->type)
                ->setAdminId($author->admin?->getId())
                ->setAuthorName(mb_substr($author->name, 0, 255))
                ->setOrderId($orderId)
                ->setPaymentPartId($paymentPartId)
                ->setExpiresAt($expiresAt);
            $movement->save($connection);

            $this->cacheTheBalance($customerId, $balance->plus($amount), $connection);

            if ($ownsTheTransaction) {
                $connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTheTransaction) {
                $connection->rollBack();
            }

            throw $exception;
        }

        if (null !== $author->admin) {
            $this->logTheGesture($movement, $author);
        }

        return $movement;
    }

    private function lockTheAccountOf(int $customerId, ConnectionInterface $connection): void
    {
        $create = $connection->prepare(
            'INSERT IGNORE INTO `credit_account` (`customer_id`, `amount`, `created_at`, `updated_at`) VALUES (:customer, 0, NOW(), NOW())'
        );
        $create->bindValue(':customer', $customerId, \PDO::PARAM_INT);
        $create->execute();

        $lock = $connection->prepare('SELECT `id` FROM `credit_account` WHERE `customer_id` = :customer FOR UPDATE');
        $lock->bindValue(':customer', $customerId, \PDO::PARAM_INT);
        $lock->execute();

        if (\in_array($lock->fetchColumn(), [false, null], true)) {
            throw new \InvalidArgumentException(\sprintf('No customer has the id %d.', $customerId));
        }
    }

    private function movementOfTheOrder(int $orderId, MovementKind $kind, ConnectionInterface $connection): ?CreditMovement
    {
        return CreditMovementQuery::create()
            ->filterByOrderId($orderId)
            ->filterByKind($kind->value)
            ->findOne($connection);
    }

    private function movementOfThePart(int $paymentPartId, MovementKind $kind, ConnectionInterface $connection): ?CreditMovement
    {
        return CreditMovementQuery::create()
            ->filterByPaymentPartId($paymentPartId)
            ->filterByKind($kind->value)
            ->findOne($connection);
    }

    private function cacheTheBalance(int $customerId, CreditAmount $balance, ConnectionInterface $connection): void
    {
        $update = $connection->prepare('UPDATE `credit_account` SET `amount` = :amount, `updated_at` = NOW() WHERE `customer_id` = :customer');
        $update->bindValue(':amount', $balance->toDecimal());
        $update->bindValue(':customer', $customerId, \PDO::PARAM_INT);
        $update->execute();
    }

    private function logTheGesture(CreditMovement $movement, Author $author): void
    {
        $request = $this->requestStack?->getCurrentRequest();

        AdminLog::append(
            self::ADMIN_LOG_RESOURCE,
            str_starts_with((string) $movement->getAmount(), '-') ? 'debit' : 'credit',
            \sprintf(
                '%s %s on the credit of customer %d: %s',
                str_starts_with((string) $movement->getAmount(), '-') ? 'Debited' : 'Credited',
                CreditAmount::ofDecimal((string) $movement->getAmount())->absolute()->toDecimal(),
                (int) $movement->getCustomerId(),
                $movement->getReason(),
            ),
            $request instanceof Request ? $request : new Request(),
            $author->admin,
            false,
            (int) $movement->getCustomerId(),
        );
    }
}
