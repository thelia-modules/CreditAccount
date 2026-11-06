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

namespace CreditAccount\Tests\Integration;

use CreditAccount\EventListener\SpendingListener;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditCartUsageQuery;
use CreditAccount\Model\CreditMovementQuery;
use CreditAccount\Spending\CreditCartUsage;
use CreditAccount\Spending\CreditDiscountLines;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Translation\IdentityTranslator;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Cart\Discount\CartDiscountLines;
use Thelia\Domain\Cart\Service\CartDiscountReconciler;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Order\Service\OrderDiscountLineRecorder;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\CartTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderDiscountLineQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The buyer chooses an amount of credit for a cart; the cart lists it as a line of its discount, and placing the
 * order charges it in the transaction of the order, under a lock on the account.
 *
 * The container is built with the services of the active modules only: the core services under test are
 * rebuilt from the container's own, with the provider and the listener of the module.
 */
final class CreditSpendingTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CreditLedger $ledger;

    private CreditBalance $balance;

    private CreditCartUsage $usage;

    private CreditDiscountLines $provider;

    private CartDiscountReconciler $reconciler;

    private OrderFacade $orderFacade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->balance = new CreditBalance();
        $this->ledger = new CreditLedger($this->balance);
        $this->usage = new CreditCartUsage($this->balance);
        $this->provider = new CreditDiscountLines($this->usage, new IdentityTranslator());

        $lines = new CartDiscountLines([$this->provider]);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(TheliaEvents::ORDER_DISCOUNT_LINE_RECORD, (new SpendingListener($this->ledger))->onDiscountLineRecord(...));

        $this->reconciler = self::rebuilt($this->getService(CartDiscountReconciler::class), ['cartDiscountLines' => $lines]);
        $this->orderFacade = self::rebuilt(
            $this->getService(OrderFacade::class),
            ['orderDiscountLineRecorder' => new OrderDiscountLineRecorder($lines, $dispatcher)],
        );
    }

    public function testTheAmountChosenIsRefusedAboveTheBalanceNeverCutDown(): void
    {
        [$customer, $cart] = $this->cartOfABuyerHolding('10');

        try {
            $this->usage->set($cart, CreditAmount::ofDecimal('10.01'));
            self::fail('An amount above the balance was kept.');
        } catch (InsufficientCreditException $refusal) {
            self::assertSame('10.00', $refusal->balance->toDecimal());
        }

        self::assertTrue($this->usage->amountOn($cart)->isZero(), 'Nothing kept, not even the balance.');

        $this->usage->set($cart, CreditAmount::ofDecimal('10'));
        self::assertSame('10.00', $this->usage->amountOn($cart)->toDecimal());

        $this->expectException(\InvalidArgumentException::class);
        $this->usage->set($cart, CreditAmount::zero());
    }

    public function testTheCreditChosenIsALineOfTheCartDiscountUntilItIsWithdrawn(): void
    {
        [, $cart] = $this->cartOfABuyerHolding('10');
        $this->usage->set($cart, CreditAmount::ofDecimal('4'));

        $lines = [...$this->provider->discountLinesFor($cart)];

        self::assertCount(1, $lines);
        self::assertSame(CreditDiscountLines::KEY, $lines[0]->key);
        self::assertSame(4.0, $lines[0]->taxedAmount);

        $this->usage->withdraw($cart);

        self::assertSame([], [...$this->provider->discountLinesFor($cart)]);
    }

    public function testPlacingTheOrderChargesTheLineUnderTheOrderAndForgetsTheChoice(): void
    {
        [$customer, $cart] = $this->cartOfABuyerHolding('10');
        $this->usage->set($cart, CreditAmount::ofDecimal('4'));
        $this->reconciler->reconcile($cart, $customer, true);

        $order = $this->placeOrder($this->reloaded($cart), $customer);

        $spending = CreditMovementQuery::create()->filterByOrderId($order->getId())->filterByKind(MovementKind::Spending->value)->findOne();
        self::assertNotNull($spending);
        self::assertSame('-4.00', CreditAmount::ofDecimal((string) $spending->getAmount())->toDecimal());
        self::assertSame('6.00', $this->balance->of((int) $customer->getId())->toDecimal());
        self::assertSame(0, CreditCartUsageQuery::create()->filterByCartId($cart->getId())->count(), 'The choice is spent with the cart.');

        $line = OrderDiscountLineQuery::create()->filterByOrderId($order->getId())->findOne();
        self::assertSame(CreditDiscountLines::KEY, $line?->getLineKey());
        self::assertSame(4.0, (float) $line->getAmount());
    }

    public function testABalanceThatMovedSinceTheChoiceRefusesTheOrder(): void
    {
        [$customer, $cart] = $this->cartOfABuyerHolding('10');
        $this->usage->set($cart, CreditAmount::ofDecimal('8'));
        $this->reconciler->reconcile($cart, $customer, true);

        $this->ledger->record((int) $customer->getId(), CreditAmount::ofDecimal('-5'), MovementKind::Adjustment, Author::system(), 'Spent on another order');

        // What the rollback undoes is not visible here, inside the transaction the test case wraps around each test.
        $this->expectException(InsufficientCreditException::class);

        $this->placeOrder($this->reloaded($cart), $customer);
    }

    /**
     * @return array{0: Customer, 1: Cart}
     */
    private function cartOfABuyerHolding(string $balance): array
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->ledger->record((int) $customer->getId(), CreditAmount::ofDecimal($balance), MovementKind::Adjustment, Author::system(), 'Welcome');

        $cart = $this->factory->cart($customer);
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency(), ['basePrice' => 100.0, 'baseQuantity' => 10]);
        $this->factory->cartItem($cart, $product, null, ['price' => '100.000000', 'promoPrice' => '100.000000']);

        return [$customer, $cart];
    }

    private function placeOrder(Cart $cart, Customer $customer): Order
    {
        $deliveryModule = ModuleQuery::create()->findOneByCode('CustomDelivery');
        $paymentModule = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($deliveryModule);
        self::assertNotNull($paymentModule);

        $country = $this->factory->country();

        $sessionOrder = (new Order())
            ->setDeliveryOrderAddressId($this->factory->orderAddress($country)->getId())
            ->setInvoiceOrderAddressId($this->factory->orderAddress($country)->getId())
            ->setStatusId(OrderStatusQuery::getNotPaidStatus()->getId())
            ->setDeliveryModuleId($deliveryModule->getId())
            ->setPaymentModuleId($paymentModule->getId())
            ->setPostage('0')
            ->setPostageTax('0');

        return $this->orderFacade->createOrder(
            $this->getService(EventDispatcherInterface::class),
            $sessionOrder,
            $this->factory->currency(),
            $this->factory->lang(),
            $cart,
            $customer,
            useOrderDefinedAddresses: true,
            recordConsentAnswers: false,
        );
    }

    private function reloaded(Cart $cart): Cart
    {
        CartTableMap::clearInstancePool();

        $reloaded = CartQuery::create()->findPk($cart->getId());
        self::assertInstanceOf(Cart::class, $reloaded);

        return $reloaded;
    }

    /**
     * The same service, built with the collaborators of the container's one but those given.
     *
     * @template T of object
     *
     * @param T                    $real
     * @param array<string, mixed> $replaced
     *
     * @return T
     */
    private static function rebuilt(object $real, array $replaced): object
    {
        $collaborators = (fn (): array => get_object_vars($this))->call($real);

        return new ($real::class)(...array_merge($collaborators, $replaced));
    }
}
