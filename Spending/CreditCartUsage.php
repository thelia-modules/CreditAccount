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

namespace CreditAccount\Spending;

use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Model\CreditCartUsage as CreditCartUsageRow;
use CreditAccount\Model\CreditCartUsageQuery;
use Thelia\Model\Cart;

/**
 * What the buyer chose to spend on a cart. Kept in the database, not in the session: the front API has none.
 * The amount is checked against the balance here, and again under lock when the order is placed; it is never
 * cut down to fit.
 */
final readonly class CreditCartUsage
{
    public function __construct(private CreditBalance $balance)
    {
    }

    /**
     * @throws InsufficientCreditException the balance does not cover the amount
     */
    public function set(Cart $cart, CreditAmount $amount): void
    {
        $customerId = $cart->getCustomerId();

        if (null === $customerId) {
            throw new \InvalidArgumentException('Only a signed-in buyer spends credit.');
        }

        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('The credit spent on a cart is above zero; withdraw it instead.');
        }

        $balance = $this->balance->of((int) $customerId);

        if ($amount->isGreaterThan($balance)) {
            throw new InsufficientCreditException($balance, $amount);
        }

        $usage = CreditCartUsageQuery::create()->findPk($cart->getId()) ?? (new CreditCartUsageRow())->setCartId($cart->getId());
        $usage->setAmount($amount->toDecimal())->save();
    }

    public function withdraw(Cart $cart): void
    {
        CreditCartUsageQuery::create()->filterByCartId($cart->getId())->delete();
    }

    public function amountOn(Cart $cart): CreditAmount
    {
        $usage = CreditCartUsageQuery::create()->findPk($cart->getId());

        return null === $usage ? CreditAmount::zero() : CreditAmount::ofDecimal((string) $usage->getAmount());
    }
}
