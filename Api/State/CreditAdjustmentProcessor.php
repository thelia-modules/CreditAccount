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
use ApiPlatform\State\ProcessorInterface;
use CreditAccount\Api\Resource\CreditAdjustment;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Ledger\MovementKind;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Model\Admin;

/**
 * Writes the manual movement through the ledger, the only writer of the journal: the ledger refuses a debit the
 * balance does not cover and logs the gesture in admin_log. The administrator is the one of the token.
 *
 * @implements ProcessorInterface<CreditAdjustment, CreditAdjustment>
 */
final readonly class CreditAdjustmentProcessor implements ProcessorInterface
{
    public function __construct(
        private CreditCustomerResolver $customers,
        private CreditLedger $ledger,
        private CreditBalance $balance,
        private CreditExpiry $expiry,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CreditAdjustment
    {
        $admin = $this->tokenStorage->getToken()?->getUser();

        if (!$admin instanceof Admin) {
            throw new AccessDeniedHttpException('An administrator must be authenticated.');
        }

        $customerId = $this->customers->customerId($uriVariables);
        $amount = CreditAmount::ofDecimal((string) $data->amount);

        try {
            $movement = $this->ledger->record(
                $customerId,
                $amount,
                MovementKind::Adjustment,
                Author::admin($admin),
                (string) $data->reason,
                expiresAt: $amount->isPositive() ? $this->expiry->forACreditWrittenAt(new \DateTimeImmutable()) : null,
            );
        } catch (InsufficientCreditException $exception) {
            throw new UnprocessableEntityHttpException(\sprintf('The debit of %s is refused: the balance is %s.', $exception->requested->toDecimal(), $exception->balance->toDecimal()), $exception);
        }

        $resource = new CreditAdjustment();
        $resource->id = (int) $movement?->getId();
        $resource->customerId = $customerId;
        $resource->amount = $amount->toDecimal();
        $resource->reason = $movement?->getReason();
        $resource->expiresAt = null === $movement?->getExpiresAt() ? null : \DateTimeImmutable::createFromInterface($movement->getExpiresAt());
        $resource->createdAt = null === $movement?->getCreatedAt() ? null : \DateTimeImmutable::createFromInterface($movement->getCreatedAt());
        $resource->balance = $this->balance->of($customerId)->toDecimal();

        return $resource;
    }
}
