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

use CreditAccount\CreditAccount;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\Service\CheckoutPaymentService;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\ConsentQuery;
use Thelia\Model\CountryArea;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderPaymentPartTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Model\OrderPostage;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\FixtureFactory;

/**
 * Places and pays orders the way the checkout does (CheckoutPaymentService, MODULE_PAY not
 * stubbed), with the credit account as a payment module beside test providers. Everything is
 * written in the test transaction.
 *
 * The test database may still hold the module as a `classic` one, as a shop does before its
 * upgrade: the row is turned into a payment module here, the way the core does on a refresh.
 */
trait PaysWithCredit
{
    /** @var list<class-string<BaseModule>> */
    private array $installedPaymentModuleClasses = [];

    /** @var list<array{0: string, 1: callable}> */
    private array $registeredListeners = [];

    abstract protected function paymentFactory(): FixtureFactory;

    abstract protected function paymentConnection(): ConnectionInterface;

    abstract protected function paymentContainer(): ContainerInterface;

    private function creditAsPaymentModule(int $position = 1000): Module
    {
        $module = ModuleQuery::create()->filterByCode(CreditAccount::getModuleCode())->findOne($this->paymentConnection())
            ?? throw new \RuntimeException('The CreditAccount module is not installed in the test database.');
        $module->setType(BaseModule::PAYMENT_MODULE_TYPE)->setPosition($position)->save($this->paymentConnection());

        return $module;
    }

    /**
     * @param class-string<BaseModule> $moduleClass
     */
    private function installPaymentModule(string $moduleClass, int $position): Module
    {
        $module = (new Module())
            ->setCode($moduleClass::getModuleCode())
            ->setFullNamespace($moduleClass)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_ACTIVATED);
        $module->save($this->paymentConnection());
        // The position is given on insertion, at the bottom: the one asked for is written after.
        $module->setPosition($position)->save($this->paymentConnection());

        $instance = new $moduleClass();
        $instance->setContainer($this->paymentContainer());
        $this->paymentContainer()->set('module.'.$moduleClass::getModuleCode(), $instance);
        $this->installedPaymentModuleClasses[] = $moduleClass;

        return $module;
    }

    private function uninstallThePaymentModules(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->paymentDispatcher()->removeListener($eventName, $listener);
        }
        $this->registeredListeners = [];

        foreach ($this->installedPaymentModuleClasses as $moduleClass) {
            $this->paymentContainer()->set('module.'.$moduleClass::getModuleCode(), null);
        }
        $this->installedPaymentModuleClasses = [];

        CreditTestBalanceProvider::reset();
        $this->paymentContainer()->get(ConsentProvider::class)->forgetCache();
    }

    private function turnMandatoryConsentsOff(): void
    {
        foreach (ConsentQuery::create()->filterByMandatory(1)->find($this->paymentConnection()) as $consent) {
            $consent->setActive(0)->save($this->paymentConnection());
        }

        $this->paymentContainer()->get(ConsentProvider::class)->forgetCache();
    }

    /**
     * A cart of the customer holding one product at the given price, free postage, ready to be
     * paid with the given module.
     */
    private function cartOf(Customer $customer, Module $paymentModule, string $price = '100.000000'): Cart
    {
        $factory = $this->paymentFactory();
        $connection = $this->paymentConnection();
        $country = $factory->country();
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery')
            ?? throw new \RuntimeException('No delivery module installed in the test database.');

        $area = (new Area())->setName('Credit payment test area '.uniqid());
        $area->save($connection);
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($connection);
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($deliveryModule->getId())->save($connection);

        if ([] === $this->registeredListeners) {
            $this->listen(TheliaEvents::MODULE_DELIVERY_GET_POSTAGE, static function (DeliveryPostageEvent $event): void {
                $event->setValidModule(true);
                $event->setPostage(new OrderPostage(0.0, 0.0, 'VAT'));
                $event->stopPropagation();
            });
            $this->listen(TheliaEvents::MODULE_PAYMENT_IS_VALID, static function (IsValidPaymentEvent $event): void {
                $event->setValidModule(true);
                $event->stopPropagation();
            });
        }

        $factory->address($customer, $country);
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency(), ['baseQuantity' => 100]);

        $cart = $factory->cart($customer);
        $factory->cartItem($cart, $product, overrides: ['price' => $price, 'promoPrice' => $price]);

        $cart
            ->setAddressDeliveryId($factory->cartAddress(null, $country)->getId())
            ->setAddressInvoiceId($factory->cartAddress(null, $country)->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->setPostage('0')
            ->setPostageTax('0')
            ->save($connection);

        return $cart;
    }

    private function pay(Cart $cart): Order
    {
        $cart = CartQuery::create()->findPk($cart->getId(), $this->paymentConnection())
            ?? throw new \RuntimeException('The cart went away.');

        return $this->paymentContainer()->get(CheckoutPaymentService::class)->payAndReturnOutcome(
            new OrderPaymentRequest(
                $cart,
                (int) $cart->getAddressDeliveryId(),
                (int) $cart->getAddressInvoiceId(),
                (int) $cart->getDeliveryModuleId(),
                (int) $cart->getPaymentModuleId(),
                $this->paymentFactory()->currency(),
                $this->paymentFactory()->lang(),
            ),
        )->placedOrder;
    }

    private function confirmThePayment(Order $order): void
    {
        $this->paymentDispatcher()->dispatch(
            (new OrderEvent($this->rereadOrder($order)))->setStatus((int) OrderStatusQuery::getPaidStatus()->getId()),
            TheliaEvents::ORDER_UPDATE_STATUS,
        );
    }

    private function cancel(Order $order): void
    {
        $this->rereadOrder($order)->setCancelled($this->paymentDispatcher());
    }

    private function rereadOrder(Order $order): Order
    {
        return OrderQuery::create()->findPk($order->getId(), $this->paymentConnection())
            ?? throw new \RuntimeException('The order went away.');
    }

    /**
     * @return list<OrderPaymentPart> the parts of the order, in the order they are settled
     */
    private function partsOf(Order $order): array
    {
        OrderPaymentPartTableMap::clearInstancePool();

        return iterator_to_array(
            OrderPaymentPartQuery::create()->filterByOrderId($order->getId())->orderByPosition()->find($this->paymentConnection()),
            false,
        );
    }

    private function partOf(Order $order, Module $module): OrderPaymentPart
    {
        foreach ($this->partsOf($order) as $part) {
            if ((int) $part->getPaymentModuleId() === (int) $module->getId()) {
                return $part;
            }
        }

        throw new \RuntimeException(\sprintf('Order %s has no part paid by module %s.', $order->getRef(), $module->getCode()));
    }

    private function listen(string $eventName, callable $listener): void
    {
        $this->paymentDispatcher()->addListener($eventName, $listener, 255);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    private function paymentDispatcher(): EventDispatcherInterface
    {
        return $this->paymentContainer()->get('event_dispatcher');
    }
}
