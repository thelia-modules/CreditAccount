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

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use CreditAccount\Api\Resource\CreditCartSpending;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Spending\CreditCartUsage;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Chooses or withdraws the amount to spend on a cart. Nothing is written in the journal: the amount is kept with
 * the cart, checked against the balance read now, and spent when the order is paid.
 *
 * @implements ProcessorInterface<CreditCartSpending, CreditCartSpending|null>
 */
final readonly class CreditCartSpendingProcessor implements ProcessorInterface
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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CreditCartSpending
    {
        $cart = $this->carts->cartOfTheCustomer($uriVariables);

        if ($operation instanceof DeleteOperationInterface) {
            $this->usage->withdraw($cart);

            return null;
        }

        try {
            $this->usage->set($cart, CreditAmount::ofDecimal((string) $data->amount));
        } catch (InsufficientCreditException $exception) {
            throw new UnprocessableEntityHttpException(\sprintf('The amount of %s is refused: the balance is %s.', $exception->requested->toDecimal(), $exception->balance->toDecimal()), $exception);
        }

        $resource = new CreditCartSpending();
        $resource->cartId = (int) $cart->getId();
        $resource->amount = $this->usage->amountOn($cart)->toDecimal();
        $resource->balance = $this->balance->of((int) $cart->getCustomerId())->toDecimal();

        return $resource;
    }
}
