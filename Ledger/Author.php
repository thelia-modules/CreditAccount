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

namespace CreditAccount\Ledger;

use Thelia\Model\Admin;

/**
 * Who wrote a movement. The name of an administrator is copied, so the journal still says who once the
 * account is gone.
 */
final readonly class Author
{
    public const ADMIN = 'admin';
    public const CUSTOMER = 'customer';
    public const SYSTEM = 'system';

    private function __construct(
        public string $type,
        public ?Admin $admin,
        public string $name,
    ) {
    }

    public static function admin(Admin $admin): self
    {
        if ($admin->isNew()) {
            throw new \InvalidArgumentException('The administrator who writes a movement must exist.');
        }

        return new self(self::ADMIN, $admin, trim($admin->getFirstname().' '.$admin->getLastname()) ?: (string) $admin->getLogin());
    }

    public static function customer(): self
    {
        return new self(self::CUSTOMER, null, '');
    }

    public static function system(string $name = ''): self
    {
        return new self(self::SYSTEM, null, $name);
    }
}
