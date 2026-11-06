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
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditCartUsageQuery;
use CreditAccount\Spending\CreditDiscountLines;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Order\OrderDiscountLineEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Charges the credit line of an order being placed, in the transaction of the order: the account is locked, the
 * balance read again, and a balance that no longer covers the line refuses the whole order.
 */
final readonly class SpendingListener
{
    public function __construct(private CreditLedger $ledger)
    {
    }

    /**
     * @throws InsufficientCreditException the balance moved since the buyer chose the amount
     */
    #[AsEventListener(event: TheliaEvents::ORDER_DISCOUNT_LINE_RECORD)]
    public function onDiscountLineRecord(OrderDiscountLineEvent $event): void
    {
        $line = $event->getLine();

        if (CreditDiscountLines::KEY !== $line->getLineKey()) {
            return;
        }

        $order = $event->getOrder();
        $connection = $event->getConnection();

        $this->ledger->record(
            (int) $order->getCustomerId(),
            CreditAmount::ofDecimal((string) $line->getAmount())->negated(),
            MovementKind::Spending,
            Author::customer(),
            orderId: (int) $order->getId(),
            connection: $connection,
        );

        CreditCartUsageQuery::create()->filterByCartId($order->getCartId())->delete($connection);
    }
}
