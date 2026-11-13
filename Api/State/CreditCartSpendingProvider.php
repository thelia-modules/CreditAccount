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

namespace CreditAccount\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use CreditAccount\Api\Resource\CreditCartSpending;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Spending\CreditCartUsage;

/**
 * @implements ProviderInterface<CreditCartSpending>
 */
final readonly class CreditCartSpendingProvider implements ProviderInterface
{
    public function __construct(
        private CreditCartResolver $carts,
        private CreditCartUsage $usage,
        private CreditBalance $balance,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CreditCartSpending
    {
        $cart = $this->carts->cartOfTheCustomer($uriVariables);

        $resource = new CreditCartSpending();
        $resource->cartId = (int) $cart->getId();
        $resource->amount = $this->usage->amountOn($cart)->toDecimal();
        $resource->balance = $this->balance->of((int) $cart->getCustomerId())->toDecimal();

        return $resource;
    }
}
