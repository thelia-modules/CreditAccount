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

namespace CreditAccount\DependencyInjection;

use CreditAccount\Api\Resource\CreditAccountBalance;
use CreditAccount\Api\Resource\CreditAccountMovement;
use CreditAccount\Api\Resource\CreditAdjustment;
use CreditAccount\CreditAccount;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Thelia\Core\Security\Resource\AdminResources;

/**
 * Tells the admin API which right guards each resource of the module: reading a balance or a journal answers to
 * the customer right, writing a movement to the dedicated manual credit right. Without this an administrator that
 * is not a superadministrator is refused everywhere.
 */
final class AdminApiResourcesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $resources = $container->hasParameter('thelia.api.admin_resources') ? $container->getParameter('thelia.api.admin_resources') : [];

        $container->setParameter('thelia.api.admin_resources', array_merge((array) $resources, [
            CreditAccountBalance::class => AdminResources::CUSTOMER,
            CreditAccountMovement::class => AdminResources::CUSTOMER,
            CreditAdjustment::class => CreditAccount::MANUAL_CREDIT_RESOURCE,
        ]));
    }
}
