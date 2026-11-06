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

use CreditAccount\CreditAccount;

/**
 * When a credit written now expires: the delay of the configuration, or never when expiration is off.
 */
final readonly class CreditExpiry
{
    public const ENABLED = 'expiration_enabled';
    public const DELAY_IN_MONTHS = 'expiration_delay';

    public function forACreditWrittenAt(\DateTimeImmutable $writtenAt): ?\DateTimeImmutable
    {
        if ('true' !== (string) CreditAccount::getConfigValue(self::ENABLED, 'false')) {
            return null;
        }

        $months = (int) CreditAccount::getConfigValue(self::DELAY_IN_MONTHS, '18');

        return $months > 0 ? $writtenAt->modify(\sprintf('+%d months', $months)) : null;
    }
}
