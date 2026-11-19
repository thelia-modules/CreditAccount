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
use ApiPlatform\Metadata\Post;
use CreditAccount\Api\State\CreditAdjustmentProcessor;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A credit or a debit an administrator writes by hand, under the dedicated manual credit right
 * (module.creditaccount.manual-credit), which the customer read right does not include.
 *
 * `amount` is a decimal string with at most two decimals, signed: "12.50" credits, "-3" debits. A JSON number is
 * refused. `reason` is required. A debit larger than the balance is refused with a 422, never cut to the balance.
 * A credit expires like any other credit of the shop. The answer is the movement written, with the balance after it.
 */
#[ApiResource(
    shortName: 'CreditAdjustment',
    operations: [
        new Post(
            uriTemplate: '/admin/credit_account/customers/{customerId}/adjustments',
            uriVariables: ['customerId'],
            read: false,
            normalizationContext: ['groups' => [self::GROUP_ADMIN_READ]],
            denormalizationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
            validationContext: ['groups' => [self::GROUP_ADMIN_WRITE]],
            processor: CreditAdjustmentProcessor::class,
        ),
    ],
)]
final class CreditAdjustment
{
    public const GROUP_ADMIN_READ = 'admin:credit_adjustment:read';
    public const GROUP_ADMIN_WRITE = 'admin:credit_adjustment:write';

    #[ApiProperty(identifier: true)]
    #[Groups([self::GROUP_ADMIN_READ])]
    public int $id = 0;

    #[Groups([self::GROUP_ADMIN_READ])]
    public int $customerId = 0;

    #[Assert\NotBlank(groups: [self::GROUP_ADMIN_WRITE])]
    #[Assert\Regex(pattern: '/^-?(?!0+(\.0{1,2})?$)\d{1,10}(\.\d{1,2})?$/', message: 'The amount is a decimal other than zero with at most two decimals, such as "12.50" or "-3".', groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $amount = null;

    #[Assert\NotBlank(message: 'A manual movement says why.', normalizer: 'trim', groups: [self::GROUP_ADMIN_WRITE])]
    #[Assert\Length(max: 255, groups: [self::GROUP_ADMIN_WRITE])]
    #[Groups([self::GROUP_ADMIN_READ, self::GROUP_ADMIN_WRITE])]
    public ?string $reason = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTimeImmutable $expiresAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups([self::GROUP_ADMIN_READ])]
    public string $balance = '0.00';
}
