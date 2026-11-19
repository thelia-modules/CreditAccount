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
use ApiPlatform\Metadata\GetCollection;
use CreditAccount\Api\State\CreditMovementProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * One line of the journal, newest first, thirty a page (a hundred at most). Read only.
 *
 * Front: the lines of the customer the token belongs to. The route names no customer and no filter can name one.
 * Admin: the lines of the customer named in the route, with who wrote each, under the customer read right.
 * Amounts are decimal strings with two decimals: a credit is positive, a debit negative.
 */
#[ApiResource(
    shortName: 'CreditMovement',
    operations: [
        new GetCollection(
            uriTemplate: '/front/account/credit/movements',
            security: 'is_granted("ROLE_CUSTOMER")',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
            provider: CreditMovementProvider::class,
        ),
        new GetCollection(
            uriTemplate: '/admin/credit_account/customers/{customerId}/movements',
            uriVariables: ['customerId'],
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
            provider: CreditMovementProvider::class,
        ),
    ],
    paginationItemsPerPage: 30,
    paginationMaximumItemsPerPage: 100,
    paginationClientItemsPerPage: true,
)]
final class CreditAccountMovement
{
    public const GROUP_FRONT_READ = 'front:credit_movement:read';
    public const GROUP_ADMIN_READ = 'admin:credit_movement:read';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_FRONT_READ, self::GROUP_ADMIN_READ])]
    public int $id = 0;

    #[Groups([self::GROUP_ADMIN_READ])]
    public int $customerId = 0;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_ADMIN_READ])]
    public string $kind = '';

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_ADMIN_READ])]
    public string $amount = '0.00';

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_ADMIN_READ])]
    public string $reason = '';

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_ADMIN_READ])]
    public ?int $orderId = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_ADMIN_READ])]
    public ?\DateTimeImmutable $expiresAt = null;

    #[Groups([self::GROUP_FRONT_READ, self::GROUP_ADMIN_READ])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $authorType = '';

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?int $adminId = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $authorName = '';
}
