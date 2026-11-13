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
use Thelia\Module\BalanceChargingPaymentModuleInterface;

/**
 * A provider that charges only what the credit leaves, the way a module adapted to the
 * payment in several parts does. It confirms nothing by itself: the test does.
 */
final class CreditTestBalanceProvider extends AbstractPaymentModule implements BalanceChargingPaymentModuleInterface
{
    /** @var list<int> the orders the provider was asked to pay */
    public static array $paidOrderIds = [];

    public static function reset(): void
    {
        self::$paidOrderIds = [];
    }

    public function pay(Order $order): ?Response
    {
        self::$paidOrderIds[] = (int) $order->getId();

        return null;
    }

    public function isValidPayment(): bool
    {
        return true;
    }

    public function supportsPaymentRetry(): bool
    {
        return true;
    }
}
