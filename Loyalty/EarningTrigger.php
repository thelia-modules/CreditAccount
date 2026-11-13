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

use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Whether a status earns: the status the merchant chose, or one further along paid < processing < sent. A custom
 * status counts for the native one it declares as its equivalence; a trigger outside that flow is matched exactly.
 */
final readonly class EarningTrigger
{
    private const FLOW = [
        OrderStatus::CODE_PAID => 1,
        OrderStatus::CODE_PROCESSING => 2,
        OrderStatus::CODE_SENT => 3,
    ];

    public function __construct(private LoyaltySettings $settings)
    {
    }

    public function isReachedBy(OrderStatus $status): bool
    {
        $triggerCode = $this->settings->triggerStatusCode();
        $trigger = OrderStatusQuery::create()->findOneByCode($triggerCode);
        $triggerEffectiveCode = $trigger?->getEffectiveCode() ?? $triggerCode;
        $reached = $status->getEffectiveCode();

        if (!isset(self::FLOW[$triggerEffectiveCode])) {
            return $status->getCode() === $triggerCode;
        }

        return isset(self::FLOW[$reached]) && self::FLOW[$reached] >= self::FLOW[$triggerEffectiveCode];
    }
}
