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

use CreditAccount\Model\CreditMovement;
use Thelia\Model\Admin;

/**
 * A credit or a debit an administrator writes by hand. A credit expires like any other credit of the shop.
 */
final readonly class ManualAdjustment
{
    public function __construct(
        private CreditLedger $ledger,
        private CreditExpiry $expiry,
    ) {
    }

    /**
     * @throws InsufficientCreditException a debit the balance does not cover
     */
    public function write(int $customerId, CreditAmount $amount, string $reason, Admin $admin): ?CreditMovement
    {
        return $this->ledger->record(
            $customerId,
            $amount,
            MovementKind::Adjustment,
            Author::admin($admin),
            $reason,
            expiresAt: $amount->isPositive() ? $this->expiry->forACreditWrittenAt(new \DateTimeImmutable()) : null,
        );
    }
}
