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

namespace CreditAccount\Loyalty;

use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Model\CreditLoyaltyTier;
use CreditAccount\Model\CreditLoyaltyTierQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Order;

/**
 * What an order earns. Grid: the amount of the row whose [min, max[ holds the eligible amount, max left empty
 * meaning no ceiling; when rows overlap, the one with the highest min wins. Percentage: rounded to the cent.
 */
final readonly class LoyaltyCalculator
{
    public function __construct(
        private LoyaltySettings $settings,
        private EligibleAmount $eligibleAmount,
    ) {
    }

    public function earningOf(Order $order): CreditAmount
    {
        $mode = $this->settings->mode();

        if (LoyaltyMode::None === $mode) {
            return CreditAmount::zero();
        }

        $eligible = $this->eligibleAmount->of($order);

        if (!$eligible->isPositive()) {
            return CreditAmount::zero();
        }

        return match ($mode) {
            LoyaltyMode::Grid => $this->fromTheGrid($eligible),
            LoyaltyMode::Percentage => CreditAmount::ofCents((int) round($eligible->cents * (float) $this->settings->percentage() / 100)),
        };
    }

    private function fromTheGrid(CreditAmount $eligible): CreditAmount
    {
        $tier = CreditLoyaltyTierQuery::create()
            ->filterByMin($eligible->toDecimal(), Criteria::LESS_EQUAL)
            ->where('(credit_loyalty_tier.max IS NULL OR credit_loyalty_tier.max > ?)', $eligible->toDecimal())
            ->orderByMin(Criteria::DESC)
            ->findOne();

        if (!$tier instanceof CreditLoyaltyTier) {
            return CreditAmount::zero();
        }

        $amount = CreditAmount::ofDecimal((string) $tier->getAmount());

        return $amount->isPositive() ? $amount : CreditAmount::zero();
    }
}
