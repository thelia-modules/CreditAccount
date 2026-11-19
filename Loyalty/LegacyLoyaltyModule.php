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

use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * The Loyalty module of 3.x. While it is active it credits the paid orders itself, through the 3.x event: the
 * earning of this module then stays silent, so that an order never earns twice.
 */
final readonly class LegacyLoyaltyModule
{
    public const CODE = 'Loyalty';

    public function isActive(): bool
    {
        return ModuleQuery::create()->filterByCode(self::CODE)->filterByActivate(BaseModule::IS_ACTIVATED)->exists();
    }
}
