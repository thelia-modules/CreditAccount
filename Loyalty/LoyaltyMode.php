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

/**
 * How an order earns credit. A single tier is a grid of one row with no upper bound.
 */
enum LoyaltyMode: string
{
    case None = 'none';

    /** The amount of the row of credit_loyalty_tier the eligible amount falls in. */
    case Grid = 'grid';

    /** A percentage of the eligible amount. */
    case Percentage = 'percentage';
}
