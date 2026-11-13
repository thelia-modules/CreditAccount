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

enum TierProblem
{
    /** A row misses its minimum or its amount, holds something that is not an amount, or ends where it starts. */
    case InvalidRow;

    /** A tier with no maximum goes on for ever: no tier can come after it. */
    case OnlyTheLastIsOpen;

    /** Two tiers cover the same amounts. */
    case Overlap;
}
