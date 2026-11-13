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

/**
 * Why a balance moved. The ledger writes a kind tied to an order at most once per order.
 */
enum MovementKind: string
{
    /** Credit an order earned through the loyalty grid. */
    case Earning = 'earning';

    /** The earning of an order taken back when it is canceled or refunded. */
    case EarningReversal = 'earning_reversal';

    /** Credit the buyer spent on an order. */
    case Spending = 'spending';

    /** A spending given back when its order is canceled or refunded. */
    case SpendingRefund = 'spending_refund';

    /** A credit or a debit an administrator wrote by hand, with its reason. */
    case Adjustment = 'adjustment';

    /** Credit that reached its expiry date without being spent. */
    case Expiration = 'expiration';

    /** A movement of the CreditAccount 3.x history, copied as it was. */
    case Imported = 'imported';

    /** What closed the gap between a 3.x balance and the sum of its history. */
    case BalanceCarry = 'balance_carry';

    public function isTiedToAnOrder(): bool
    {
        return match ($this) {
            self::Earning, self::EarningReversal, self::Spending, self::SpendingRefund => true,
            default => false,
        };
    }

    /**
     * A spending and its refund belong to one part of the payment of an order, which is what they are written
     * once by: an order may be paid again, with another part.
     */
    public function isTiedToAPaymentPart(): bool
    {
        return self::Spending === $this || self::SpendingRefund === $this;
    }
}
