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

use CreditAccount\CreditAccount;
use Thelia\Model\OrderStatus;

/**
 * What the merchant chose for the loyalty: the mode, the percentage, and the status from which an order earns.
 */
final readonly class LoyaltySettings
{
    public const MODE = 'loyalty_mode';
    public const PERCENTAGE = 'loyalty_percentage';
    public const TRIGGER_STATUS = 'loyalty_trigger_status';

    public function mode(): LoyaltyMode
    {
        return LoyaltyMode::tryFrom((string) CreditAccount::getConfigValue(self::MODE, LoyaltyMode::None->value)) ?? LoyaltyMode::None;
    }

    /**
     * The percentage as a decimal string ("2.5"), "0" when none is set.
     */
    public function percentage(): string
    {
        $percentage = trim((string) CreditAccount::getConfigValue(self::PERCENTAGE, '0'));

        return is_numeric($percentage) && (float) $percentage > 0.0 ? $percentage : '0';
    }

    /**
     * The code of the status (one of OrderStatus::CODE_*, or a custom one) from which an order earns.
     */
    public function triggerStatusCode(): string
    {
        $code = trim((string) CreditAccount::getConfigValue(self::TRIGGER_STATUS, OrderStatus::CODE_PAID));

        return '' === $code ? OrderStatus::CODE_PAID : $code;
    }
}
