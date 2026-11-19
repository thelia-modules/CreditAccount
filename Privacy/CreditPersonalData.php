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

namespace CreditAccount\Privacy;

use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Model\CreditMovementQuery;
use Thelia\Domain\Customer\Service\CustomerPersonalDataProviderInterface;
use Thelia\Model\Customer;

/**
 * The balance of a customer is a financial personal data: the export lists it with the movements that make it,
 * and the anonymization detaches them. The core calls this inside its own transaction, so a failure here
 * cancels the whole anonymization.
 */
final readonly class CreditPersonalData implements CustomerPersonalDataProviderInterface
{
    public const SECTION = 'credit_account';

    public function __construct(private CreditBalance $balance, private CreditDetacher $detacher)
    {
    }

    public function getPersonalDataSectionName(): string
    {
        return self::SECTION;
    }

    public function exportPersonalData(Customer $customer): array
    {
        $movements = CreditMovementQuery::create()
            ->filterByCustomerId($customer->getId())
            ->orderById()
            ->find();

        if (0 === $movements->count()) {
            return [];
        }

        $rows = [];

        foreach ($movements as $movement) {
            $rows[] = [
                'date' => $movement->getCreatedAt('Y-m-d H:i:s'),
                'kind' => $movement->getKind(),
                'amount' => (string) $movement->getAmount(),
                'reason' => $movement->getReason(),
                'author' => $movement->getAuthorType(),
                'order_id' => $movement->getOrderId(),
                'expires_at' => $movement->getExpiresAt('Y-m-d H:i:s'),
            ];
        }

        return [
            'balance' => $this->balance->of((int) $customer->getId())->toDecimal(),
            'movements' => $rows,
        ];
    }

    public function anonymizePersonalData(Customer $customer): void
    {
        $this->detacher->detach((int) $customer->getId());
    }
}
