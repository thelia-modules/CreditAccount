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
 * A debit larger than the balance read in the transaction that would write it.
 */
final class InsufficientCreditException extends \DomainException
{
    public function __construct(
        public readonly CreditAmount $balance,
        public readonly CreditAmount $requested,
    ) {
        parent::__construct(\sprintf('The balance (%s) does not cover %s.', $balance->toDecimal(), $requested->toDecimal()));
    }
}
