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

use CreditAccount\CreditAccount;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditCartUsageQuery;
use CreditAccount\Model\CreditMovementQuery;
use CreditAccount\Spending\CreditCartUsage;
use CreditAccount\Spending\CreditPaymentPart;
use CreditAccount\Tests\Support\Payment\CreditTestBalanceProvider;
use CreditAccount\Tests\Support\Payment\CreditTestPlainProvider;
use CreditAccount\Tests\Support\Payment\PaysWithCredit;
use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Module\Payment\PaymentCartContext;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Domain\Payment\Exception\InstantPaymentRefusedException;
use Thelia\Domain\Payment\Exception\PaymentCompositionRefusedException;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Thelia\Test\Trait\ForgetsPooledModels;

/**
 * Paying an order with the credit, through the checkout of the core: the amount the buyer chose for the cart settles
 * its part when the order is paid, a provider charges the rest, and a canceled order gives the credit back, once.
 */
final class CreditPaymentTest extends IntegrationTestCase
{
    use ForgetsPooledModels;
    use PaysWithCredit;

    private Module $creditModule;

    private Module $provider;

    private Customer $customer;

    private CreditLedger $ledger;

    private CreditBalance $balance;

    private CreditCartUsage $usage;

    protected function setUp(): void
    {
        parent::setUp();

        if (!ModuleQuery::create()->findOneByCode(CreditAccount::getModuleCode())?->getActivate()) {
            self::markTestSkipped('The CreditAccount module is not active in the test database.');
        }

        $this->balance = new CreditBalance();
        $this->ledger = new CreditLedger($this->balance);
        $this->usage = new CreditCartUsage($this->balance);
        $this->creditModule = $this->creditAsPaymentModule();
        $this->provider = $this->installPaymentModule(CreditTestBalanceProvider::class, 1001);
        $this->turnMandatoryConsentsOff();
        $this->customer = $this->paymentFactory()->customer($this->paymentFactory()->customerTitle());
        CreditAccount::setConfigValue(CreditExpiry::ENABLED, 'false');
    }

    protected function tearDown(): void
    {
        $this->uninstallThePaymentModules();
        self::forgetPooledModels();
        Propel::disableInstancePooling();

        parent::tearDown();
    }

    #[Test]
    public function aCreditThatCoversTheOrderPaysItWithoutAnyProviderAndTheChoiceIsForgotten(): void
    {
        $this->credit('500');
        $cart = $this->cartOf($this->customer, $this->creditModule);
        $this->usage->set($cart, CreditAmount::ofDecimal('500'));

        $order = $this->rereadOrder($this->pay($cart));

        $part = $this->partOf($order, $this->creditModule);
        $total = CreditAmount::ofFloat($order->getTotalAmount());
        self::assertTrue($order->isPaid(false), 'the credit paid the whole order');
        self::assertSame([], CreditTestBalanceProvider::$paidOrderIds, 'no provider was asked');
        self::assertSame(OrderPaymentPartState::SETTLED->value, $part->getState());
        self::assertSame($total->toDecimal(), CreditAmount::ofDecimal((string) $part->getAmount())->toDecimal(), 'the part never exceeds the order');

        $spending = $this->movementOf($part, MovementKind::Spending);
        self::assertNotNull($spending);
        self::assertSame($total->negated()->toDecimal(), CreditAmount::ofDecimal((string) $spending->getAmount())->toDecimal());
        self::assertSame($order->getId(), $spending->getOrderId());
        self::assertSame(Author::CUSTOMER, $spending->getAuthorType());
        self::assertSame(CreditAmount::ofDecimal('500')->minus($total)->toDecimal(), $this->balance->of((int) $this->customer->getId())->toDecimal());
        self::assertSame(0, CreditCartUsageQuery::create()->filterByCartId($cart->getId())->count(), 'the choice is forgotten once the order is paid');
    }

    #[Test]
    public function aCreditSmallerThanTheOrderLeavesTheRestToAProviderThatChargesTheBalance(): void
    {
        $this->credit('100');
        $cart = $this->cartOf($this->customer, $this->provider, '300.000000');
        $this->usage->set($cart, CreditAmount::ofDecimal('50'));

        $order = $this->pay($cart);

        $part = $this->partOf($order, $this->creditModule);
        self::assertSame(OrderPaymentPartState::SETTLED->value, $part->getState());
        self::assertSame('50.000000', $part->getAmount());
        self::assertSame('50.00', $this->balance->of((int) $this->customer->getId())->toDecimal());
        self::assertSame([$order->getId()], CreditTestBalanceProvider::$paidOrderIds, 'the provider was asked for the rest');
        self::assertSame(
            round($order->getTotalAmount() - 50, 2),
            round((float) $this->providerInstance()->getAmountToCharge($this->rereadOrder($order)), 2),
        );
        self::assertFalse($this->rereadOrder($order)->isPaid(false), 'the provider has not confirmed yet');
        self::assertSame(1, CreditCartUsageQuery::create()->filterByCartId($cart->getId())->count(), 'unpaid, the order may be presented again');

        $this->confirmThePayment($order);

        self::assertTrue($this->rereadOrder($order)->isPaid(false));
        self::assertSame(0, CreditCartUsageQuery::create()->filterByCartId($cart->getId())->count(), 'paid, the choice is forgotten');
    }

    #[Test]
    public function theAmountAppliedDoesNotChangeOnceThePartIsSettledAndTheUnpaidOrderIsPresentedAgain(): void
    {
        $this->credit('100');
        $cart = $this->cartOf($this->customer, $this->provider, '300.000000');
        $this->usage->set($cart, CreditAmount::ofDecimal('50'));
        $before = $this->module()->getAmountAppliedToCart($cart);

        $order = $this->pay($cart);
        $again = $this->pay($cart);

        self::assertSame('50.00', $before);
        self::assertSame($before, $this->module()->getAmountAppliedToCart($cart));
        self::assertSame($order->getId(), $again->getId(), 'the same unpaid order is presented to the provider again');
        self::assertSame(1, CreditMovementQuery::create()->filterByKind(MovementKind::Spending->value)->filterByCustomerId($this->customer->getId())->count(), 'the credit is spent once');
    }

    #[Test]
    public function aBalanceThatFellBelowTheChoiceRefusesTheOrderAndSpendsNothing(): void
    {
        $this->credit('100');
        $cart = $this->cartOf($this->customer, $this->provider, '300.000000');
        $this->usage->set($cart, CreditAmount::ofDecimal('50'));
        // Spent elsewhere while the buyer reads the payment step.
        $this->ledger->record((int) $this->customer->getId(), CreditAmount::ofDecimal('-80'), MovementKind::Adjustment, Author::system(), 'Spent elsewhere');

        try {
            $this->pay($cart);
            self::fail('a credit the balance does not cover paid the order');
        } catch (InstantPaymentRefusedException) {
        }

        self::assertSame(0, CreditMovementQuery::create()->filterByKind(MovementKind::Spending->value)->filterByCustomerId($this->customer->getId())->count());
        self::assertSame('20.00', $this->balance->of((int) $this->customer->getId())->toDecimal(), 'never capped, never negative');
        self::assertSame([], CreditTestBalanceProvider::$paidOrderIds, 'the provider is not asked for a payment that was refused');
    }

    #[Test]
    public function aProviderThatCannotChargeABalanceIsRefusedWhenTheCreditLeavesSomethingToPay(): void
    {
        $plain = $this->installPaymentModule(CreditTestPlainProvider::class, 1002);
        $this->credit('100');
        $cart = $this->cartOf($this->customer, $plain, '300.000000');
        $this->usage->set($cart, CreditAmount::ofDecimal('50'));

        $this->expectException(PaymentCompositionRefusedException::class);

        $this->pay($cart);
    }

    #[Test]
    public function cancellingTheOrderGivesWhatThePartSpentBackOnceWithTheExpiryOfACreditWrittenNow(): void
    {
        CreditAccount::setConfigValue(CreditExpiry::ENABLED, 'true');
        CreditAccount::setConfigValue(CreditExpiry::DELAY_IN_MONTHS, '6');
        $this->credit('100');
        $cart = $this->cartOf($this->customer, $this->provider, '300.000000');
        $this->usage->set($cart, CreditAmount::ofDecimal('50'));
        $order = $this->pay($cart);
        $part = $this->partOf($order, $this->creditModule);
        self::assertSame('50.00', $this->balance->of((int) $this->customer->getId())->toDecimal());

        $this->cancel($order);

        self::assertSame(OrderPaymentPartState::RELEASED->value, $this->partOf($order, $this->creditModule)->getState());
        self::assertSame('100.00', $this->balance->of((int) $this->customer->getId())->toDecimal());
        $refund = $this->movementOf($part, MovementKind::SpendingRefund);
        self::assertSame('50.00', CreditAmount::ofDecimal((string) $refund?->getAmount())->toDecimal());
        self::assertSame((new \DateTimeImmutable('+6 months'))->format('Y-m-d'), $refund->getExpiresAt()?->format('Y-m-d'));

        // The core may ask again for a part it already got back (a cancellation replayed, a refunded order cancelled later).
        $this->module()->releasePart($part);
        $this->module()->releasePart($part);

        self::assertSame('100.00', $this->balance->of((int) $this->customer->getId())->toDecimal(), 'nothing is credited twice');
        self::assertSame(1, CreditMovementQuery::create()->filterByPaymentPartId($part->getId())->filterByKind(MovementKind::SpendingRefund->value)->count());
    }

    #[Test]
    public function aPartThatSpentNothingGivesNothingBack(): void
    {
        $this->credit('100');
        $cart = $this->cartOf($this->customer, $this->provider, '300.000000');
        $this->usage->set($cart, CreditAmount::ofDecimal('50'));
        $order = $this->pay($cart);
        $part = $this->partOf($order, $this->creditModule);
        $this->ledger->record((int) $this->customer->getId(), CreditAmount::ofDecimal('10'), MovementKind::Adjustment, Author::system(), 'Welcome');

        // Another part, of another order, that this module never settled.
        $stranger = (new \Thelia\Model\OrderPaymentPart())
            ->setOrderId($this->paymentFactory()->order($this->customer)->getId())
            ->setPaymentModuleId($this->creditModule->getId())
            ->setAmount('50')
            ->setState(OrderPaymentPartState::SETTLED->value)
            ->setPosition(0);
        $stranger->save($this->paymentConnection());
        $this->module()->releasePart($stranger);

        self::assertSame('60.00', $this->balance->of((int) $this->customer->getId())->toDecimal());
    }

    #[Test]
    public function aSpendingIsWrittenOncePerPartSoAnOrderPaidAgainWithAnotherPartSpendsAgain(): void
    {
        $this->credit('100');
        $customerId = (int) $this->customer->getId();
        $order = $this->paymentFactory()->order($this->customer);

        $spend = fn (int $partId): mixed => $this->ledger->record($customerId, CreditAmount::ofDecimal('-30'), MovementKind::Spending, Author::customer(), orderId: (int) $order->getId(), paymentPartId: $partId);
        $giveBack = fn (int $partId): mixed => $this->ledger->record($customerId, CreditAmount::ofDecimal('30'), MovementKind::SpendingRefund, Author::system(), orderId: (int) $order->getId(), paymentPartId: $partId);

        $first = $spend(9001);
        self::assertSame($first?->getId(), $spend(9001)?->getId(), 'the same part spends once');
        $giveBack(9001);
        $giveBack(9001);
        self::assertSame('100.00', $this->balance->of($customerId)->toDecimal());

        $second = $spend(9002);

        self::assertNotSame($first?->getId(), $second?->getId());
        self::assertSame('70.00', $this->balance->of($customerId)->toDecimal(), 'the second part of the same order was spent');
    }

    #[Test]
    public function aRefundGivesTheSpendingBackOnceAndACancellationAfterItGivesNothingMore(): void
    {
        $this->credit('100');
        $cart = $this->cartOf($this->customer, $this->provider, '300.000000');
        $this->usage->set($cart, CreditAmount::ofDecimal('50'));
        $order = $this->pay($cart);
        $part = $this->partOf($order, $this->creditModule);
        $this->confirmThePayment($order);
        self::assertSame('50.00', $this->balance->of((int) $this->customer->getId())->toDecimal());

        $refund = fn () => $this->paymentDispatcher()->dispatch(
            (new OrderEvent($this->rereadOrder($order)))->setStatus((int) OrderStatusQuery::create()->findOneByCode('refunded')->getId()),
            TheliaEvents::ORDER_UPDATE_STATUS,
        );
        $refund();
        $refund();

        self::assertSame(OrderPaymentPartState::SETTLED->value, $this->partOf($order, $this->creditModule)->getState(), 'the core does not release a part at a refund');
        self::assertSame('100.00', $this->balance->of((int) $this->customer->getId())->toDecimal(), 'the spending came back, whole');
        self::assertSame(1, CreditMovementQuery::create()->filterByPaymentPartId($part->getId())->filterByKind(MovementKind::SpendingRefund->value)->count());

        $this->cancel($order);

        self::assertSame(OrderPaymentPartState::RELEASED->value, $this->partOf($order, $this->creditModule)->getState());
        self::assertSame('100.00', $this->balance->of((int) $this->customer->getId())->toDecimal(), 'nothing is credited a second time');
        self::assertSame(1, CreditMovementQuery::create()->filterByPaymentPartId($part->getId())->filterByKind(MovementKind::SpendingRefund->value)->count());
    }

    #[Test]
    public function theCreditIsOfferedToASignedInCustomerHoldingSomeOnly(): void
    {
        $context = $this->getService(PaymentCartContext::class);
        $part = $this->paymentPart();
        $cartOf = fn (?Customer $customer): Cart => $this->cartOf($customer ?? $this->paymentFactory()->customer($this->paymentFactory()->customerTitle()), $this->creditModule);

        $withoutBalance = $cartOf(null);
        self::assertFalse($context->within($withoutBalance, $part->isUsableOnTheCartBeingPaid(...)), 'a customer with nothing to spend');
        self::assertFalse($part->isUsableOnTheCartBeingPaid(), 'no cart is being paid');

        $anonymous = $cartOf(null)->setCustomerId(null);
        self::assertFalse($context->within($anonymous, $part->isUsableOnTheCartBeingPaid(...)), 'a cart with no customer');
        self::assertSame('0', $this->module()->getAmountAppliedToCart($anonymous));

        $this->credit('1');
        $holding = $cartOf($this->customer);
        self::assertTrue($context->within($holding, $part->isUsableOnTheCartBeingPaid(...)));
    }

    protected function paymentFactory(): FixtureFactory
    {
        return $this->createFixtureFactory();
    }

    protected function paymentConnection(): ConnectionInterface
    {
        return $this->getPropelConnection();
    }

    protected function paymentContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    private function credit(string $amount): void
    {
        $this->ledger->record((int) $this->customer->getId(), CreditAmount::ofDecimal($amount), MovementKind::Adjustment, Author::system(), 'Welcome');
    }

    private function movementOf(\Thelia\Model\OrderPaymentPart $part, MovementKind $kind): ?\CreditAccount\Model\CreditMovement
    {
        return CreditMovementQuery::create()->filterByPaymentPartId($part->getId())->filterByKind($kind->value)->findOne();
    }

    private function module(): CreditAccount
    {
        $instance = $this->creditModule->getPaymentModuleInstance(static::getContainer());

        return $instance instanceof CreditAccount ? $instance : throw new \LogicException('The credit module is not the CreditAccount one.');
    }

    private function paymentPart(): CreditPaymentPart
    {
        return new CreditPaymentPart($this->ledger, $this->balance, $this->usage, new CreditExpiry(), $this->getService(PaymentCartContext::class));
    }

    private function providerInstance(): CreditTestBalanceProvider
    {
        $instance = $this->provider->getPaymentModuleInstance(static::getContainer());

        return $instance instanceof CreditTestBalanceProvider ? $instance : throw new \LogicException('The provider is not the test one.');
    }
}
