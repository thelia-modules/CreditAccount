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

namespace CreditAccount\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use CreditAccount\Api\State\CreditBalanceProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * The balance of a customer, summed from the journal in one query. Read only.
 *
 * Front: the balance of the customer the token belongs to, and nobody else's: the route names no customer.
 * Admin: the balance of the customer named in the route, under the customer read right.
 */
#[ApiResource(
    shortName: 'CreditBalance',
    operations: [
        new Get(
            uriTemplate: '/front/account/credit/balance',
            security: 'is_granted("ROLE_CUSTOMER")',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
            provider: CreditBalanceProvider::class,
        ),
        new Get(
            uriTemplate: '/admin/credit_account/customers/{customerId}/balance',
            uriVariables: ['customerId'],
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
            provider: CreditBalanceProvider::class,
        ),
    ],
)]
final class CreditAccountBalance
{
    public const GROUP_FRONT_READ = 'front:credit_balance:read';
    public const GROUP_ADMIN_READ = 'admin:credit_balance:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_ADMIN_READ])]
    public int $customerId = 0;

    /**
     * A decimal string with two decimals, never a float.
     */
    #[Groups([self::GROUP_FRONT_READ, self::GROUP_ADMIN_READ])]
    public string $balance = '0.00';
}
