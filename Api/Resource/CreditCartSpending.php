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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use CreditAccount\Api\State\CreditCartSpendingProcessor;
use CreditAccount\Api\State\CreditCartSpendingProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * How much of their credit the signed-in customer chose to spend on one of their carts. Choosing writes no movement:
 * the credit is spent when the order is paid, and refused then if the balance no longer covers it.
 *
 * The route names the cart, and a cart that is not the customer's is a 404, as if it did not exist. An amount above
 * the balance read now is a 422, never cut down to fit. Amounts are decimal strings with two decimals.
 */
#[ApiResource(
    shortName: 'CreditCartSpending',
    operations: [
        new Get(
            uriTemplate: '/front/account/credit/carts/{cartId}/spending',
            uriVariables: ['cartId'],
            security: 'is_granted("ROLE_CUSTOMER")',
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
            provider: CreditCartSpendingProvider::class,
        ),
        new Put(
            uriTemplate: '/front/account/credit/carts/{cartId}/spending',
            uriVariables: ['cartId'],
            security: 'is_granted("ROLE_CUSTOMER")',
            read: false,
            normalizationContext: ['groups' => [self::GROUP_FRONT_READ]],
            denormalizationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            validationContext: ['groups' => [self::GROUP_FRONT_WRITE]],
            processor: CreditCartSpendingProcessor::class,
        ),
        new Delete(
            uriTemplate: '/front/account/credit/carts/{cartId}/spending',
            uriVariables: ['cartId'],
            security: 'is_granted("ROLE_CUSTOMER")',
            read: false,
            processor: CreditCartSpendingProcessor::class,
        ),
    ],
)]
final class CreditCartSpending
{
    public const GROUP_FRONT_READ = 'front:credit_cart_spending:read';
    public const GROUP_FRONT_WRITE = 'front:credit_cart_spending:write';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_FRONT_READ])]
    public int $cartId = 0;

    #[Assert\NotBlank(groups: [self::GROUP_FRONT_WRITE])]
    #[Assert\Regex(pattern: '/^(?!0+(\.0{1,2})?$)\d{1,10}(\.\d{1,2})?$/', message: 'The amount is a decimal above zero with at most two decimals, such as "12.50" or "3".', groups: [self::GROUP_FRONT_WRITE])]
    #[Groups([self::GROUP_FRONT_READ, self::GROUP_FRONT_WRITE])]
    public ?string $amount = null;

    /**
     * What the customer holds now: the amount chosen never exceeds it when it is chosen.
     */
    #[Groups([self::GROUP_FRONT_READ])]
    public string $balance = '0.00';
}
