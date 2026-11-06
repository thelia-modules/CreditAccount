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

use CreditAccount\CreditAccount;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Domain\Cart\Discount\CartDiscountLine;
use Thelia\Domain\Cart\Discount\CartDiscountLineProviderInterface;
use Thelia\Model\Cart;

/**
 * The credit the buyer chose to spend, as a line of the cart discount: the amount as chosen, never cut down.
 */
final readonly class CreditDiscountLines implements CartDiscountLineProviderInterface
{
    public const KEY = 'CreditAccount.balance';

    public function __construct(
        private CreditCartUsage $usage,
        private TranslatorInterface $translator,
    ) {
    }

    public function discountLinesFor(Cart $cart): iterable
    {
        if (null === $cart->getId() || null === $cart->getCustomerId()) {
            return;
        }

        $amount = $this->usage->amountOn($cart);

        if ($amount->isPositive()) {
            yield new CartDiscountLine(self::KEY, $this->translator->trans('Credit account', [], CreditAccount::DOMAIN), $amount->toFloat());
        }
    }
}
