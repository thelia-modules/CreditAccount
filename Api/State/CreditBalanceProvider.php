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
use CreditAccount\Api\Resource\CreditAccountBalance;
use CreditAccount\Ledger\CreditBalance;

/**
 * @implements ProviderInterface<CreditAccountBalance>
 */
final readonly class CreditBalanceProvider implements ProviderInterface
{
    public function __construct(
        private CreditCustomerResolver $customers,
        private CreditBalance $balance,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CreditAccountBalance
    {
        $customerId = $this->customers->customerId($uriVariables);

        $resource = new CreditAccountBalance();
        $resource->customerId = $customerId;
        $resource->balance = $this->balance->of($customerId)->toDecimal();

        return $resource;
    }
}
