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

namespace CreditAccount\Expiration;

use CreditAccount\Ledger\CreditAmount;

final readonly class ExpirationReport
{
    public function __construct(
        public int $customersHandled,
        public int $movementsExpired,
        public CreditAmount $amountExpired,
        public int $warningsSent,
        public int $warningsFailed,
    ) {
    }
}
