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

namespace CreditAccount\EventListener;

use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Loyalty\EarningTrigger;
use CreditAccount\Loyalty\LoyaltyCalculator;
use CreditAccount\Model\CreditMovementQuery;
use CreditAccount\Spending\CreditCartUsage;
use CreditAccount\Spending\CreditPaymentPart;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Order;

/**
 * Moves the balance when an order changes status, after the core committed the change (priority 64, below the
 * core's 128): a refused transition throws before and moves nothing. Every movement is written once per order.
 *
 * - The order reaches the status the merchant chose, or a later one: it earns.
 * - The order is refunded: the credit its payment parts spent is given back, by the write the release of a part
 *   does (the core releases the parts of a canceled order itself, before this listener, and leaves those of a
 *   refunded one settled): a later release of the same part credits nothing again.
 * - The order is canceled or refunded: what it earned is taken back, down to what the balance still holds (the
 *   balance never goes below zero, the reason says what was not).
 * - The order is paid: the amount the buyer chose to spend on its cart is forgotten.
 */
final readonly class OrderStatusListener
{
    private const AUTHOR = 'Order status';

    public function __construct(
        private CreditLedger $ledger,
        private LoyaltyCalculator $calculator,
        private EarningTrigger $trigger,
        private CreditExpiry $expiry,
        private CreditCartUsage $usage,
        private CreditPaymentPart $paymentPart,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 64)]
    public function onOrderStatusUpdate(OrderEvent $event): void
    {
        $order = $event->getOrder();
        $status = $order->getOrderStatus();

        if (null === $order->getCustomerId()) {
            return;
        }

        if ($status->isRefunded()) {
            $this->paymentPart->releaseThePartsOf($order);
        }

        if ($status->isCancelled() || $status->isRefunded()) {
            $this->takeBackTheEarning($order);

            return;
        }

        if ($status->isPaid(false) && null !== $order->getCartId()) {
            $this->usage->withdrawFromCartId((int) $order->getCartId());
        }

        if ($this->trigger->isReachedBy($status)) {
            $this->earn($order);
        }
    }

    private function earn(Order $order): void
    {
        if ($this->hasMovement($order, MovementKind::Earning)) {
            return;
        }

        $earning = $this->calculator->earningOf($order);

        if (!$earning->isPositive()) {
            return;
        }

        $this->ledger->record(
            (int) $order->getCustomerId(),
            $earning,
            MovementKind::Earning,
            Author::system(self::AUTHOR),
            \sprintf('Order %s', $order->getRef()),
            (int) $order->getId(),
            $this->expiry->forACreditWrittenAt(new \DateTimeImmutable()),
        );
    }

    private function takeBackTheEarning(Order $order): void
    {
        $earning = $this->movement($order, MovementKind::Earning);

        if (null === $earning || $this->hasMovement($order, MovementKind::EarningReversal)) {
            return;
        }

        $this->ledger->record(
            (int) $order->getCustomerId(),
            $earning->negated(),
            MovementKind::EarningReversal,
            Author::system(self::AUTHOR),
            \sprintf('Order %s canceled', $order->getRef()),
            (int) $order->getId(),
            capAtBalance: true,
        );
    }

    private function movement(Order $order, MovementKind $kind): ?CreditAmount
    {
        $movement = CreditMovementQuery::create()->filterByOrderId($order->getId())->filterByKind($kind->value)->findOne();

        return null === $movement ? null : CreditAmount::ofDecimal((string) $movement->getAmount());
    }

    private function hasMovement(Order $order, MovementKind $kind): bool
    {
        return CreditMovementQuery::create()->filterByOrderId($order->getId())->filterByKind($kind->value)->exists();
    }
}
