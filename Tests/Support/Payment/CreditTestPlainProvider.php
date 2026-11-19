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

namespace CreditAccount\Tests\Support\Payment;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;

/**
 * A provider that charges the total of the order and knows nothing of the payment in several
 * parts: the core refuses to combine it with the credit when it leaves something to pay.
 */
final class CreditTestPlainProvider extends AbstractPaymentModule
{
    public function pay(Order $order): ?Response
    {
        return null;
    }

    public function isValidPayment(): bool
    {
        return true;
    }
}
