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

/**
 * A credit that came in, with what is left of it once the debits have been charged to the oldest expiry first.
 */
final readonly class CreditLot
{
    public function __construct(
        public int $movementId,
        public CreditAmount $original,
        public CreditAmount $remaining,
        public ?\DateTimeImmutable $expiresAt,
        public bool $warned,
    ) {
    }
}
