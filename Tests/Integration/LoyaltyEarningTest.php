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
use CreditAccount\EventListener\OrderStatusListener;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Loyalty\EarningTrigger;
use CreditAccount\Loyalty\EligibleAmount;
use CreditAccount\Loyalty\LoyaltyCalculator;
use CreditAccount\Loyalty\LoyaltyMode;
use CreditAccount\Loyalty\LoyaltySettings;
use CreditAccount\Model\CreditLoyaltyExcludedCategory;
use CreditAccount\Model\CreditLoyaltyExcludedProduct;
use CreditAccount\Model\CreditLoyaltyTier;
use CreditAccount\Model\CreditMovementQuery;
use CreditAccount\Spending\CreditCartUsage;
use CreditAccount\Spending\CreditPaymentPart;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Domain\Module\Payment\PaymentCartContext;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * An order earns credit once it reaches the status the merchant chose, on its lines tax included less the
 * excluded ones and their share of the discount; canceled, it gives back what it took and what it gave.
 */
final class LoyaltyEarningTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CreditLedger $ledger;

    private CreditBalance $balance;

    private OrderStatusListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->balance = new CreditBalance();
        $this->ledger = new CreditLedger($this->balance);
        $settings = new LoyaltySettings();
        $this->listener = new OrderStatusListener(
            $this->ledger,
            new LoyaltyCalculator($settings, new EligibleAmount()),
            new EarningTrigger($settings),
            new CreditExpiry(),
            new CreditCartUsage($this->balance),
            new CreditPaymentPart($this->ledger, $this->balance, new CreditCartUsage($this->balance), new CreditExpiry(), new PaymentCartContext()),
        );

        CreditAccount::setConfigValue(LoyaltySettings::MODE, LoyaltyMode::Percentage->value);
        CreditAccount::setConfigValue(LoyaltySettings::PERCENTAGE, '5');
        CreditAccount::setConfigValue(LoyaltySettings::TRIGGER_STATUS, OrderStatus::CODE_PAID);
        CreditAccount::setConfigValue(CreditExpiry::ENABLED, 'false');
    }

    public function testAPaidOrderEarnsAPercentageOfItsLinesLessTheirShareOfTheDiscountPostageLeftOut(): void
    {
        $order = $this->order(['60', '40'], discount: '20', postage: '15');

        $this->moveTo($order, OrderStatus::CODE_PAID);

        self::assertSame('4.00', $this->earningOf($order), '5 % of (100 - 20), postage not counted.');
        self::assertSame('4.00', $this->balance->of((int) $order->getCustomerId())->toDecimal());
    }

    public function testTheGridGivesTheAmountOfTheRowTheEligibleAmountFallsIn(): void
    {
        CreditAccount::setConfigValue(LoyaltySettings::MODE, LoyaltyMode::Grid->value);
        $this->tier('0', '50', '2');
        $this->tier('50', null, '5');

        $below = $this->order(['49.99']);
        $atTheBound = $this->order(['50']);
        $above = $this->order(['300']);

        foreach ([$below, $atTheBound, $above] as $order) {
            $this->moveTo($order, OrderStatus::CODE_PAID);
        }

        self::assertSame('2.00', $this->earningOf($below));
        self::assertSame('5.00', $this->earningOf($atTheBound), 'min is included, max is not.');
        self::assertSame('5.00', $this->earningOf($above), 'An empty max has no ceiling: a single tier is a grid of one row.');
    }

    public function testAnExcludedProductOrAProductOfAnExcludedCategoryOrOfOneOfItsChildrenEarnsNothing(): void
    {
        $category = $this->factory->category();
        $child = $this->factory->category(['parent' => $category->getId()]);
        $inTheChild = $this->product();
        $this->factory->productCategory($inTheChild, $child);
        $excluded = $this->product();
        $kept = $this->product();

        (new CreditLoyaltyExcludedCategory())->setCategoryId($category->getId())->save();
        (new CreditLoyaltyExcludedProduct())->setProductId($excluded->getId())->save();

        $order = $this->order([
            ['30', $inTheChild->getRef()],
            ['30', $excluded->getRef()],
            ['40', $kept->getRef()],
        ]);

        $this->moveTo($order, OrderStatus::CODE_PAID);

        self::assertSame('2.00', $this->earningOf($order), '5 % of the 40 left.');
    }

    public function testAnOrderEarnsFromTheChosenStatusOrALaterOneAndOnlyOnce(): void
    {
        CreditAccount::setConfigValue(LoyaltySettings::TRIGGER_STATUS, OrderStatus::CODE_PROCESSING);
        $order = $this->order(['100']);

        $this->moveTo($order, OrderStatus::CODE_PAID);
        self::assertNull($this->earningOf($order), 'Paid comes before the chosen status.');

        $this->moveTo($order, OrderStatus::CODE_SENT);
        $this->moveTo($order, OrderStatus::CODE_SENT);
        self::assertSame('5.00', $this->earningOf($order), 'Sent comes after processing, which the order skipped.');
        self::assertSame(1, CreditMovementQuery::create()->filterByOrderId($order->getId())->count());
    }

    public function testACustomStatusCountsForTheNativeOneItStandsFor(): void
    {
        $shipped = $this->factory->orderStatus(['code' => 'shipped_by_carrier', 'equivalentCode' => OrderStatus::CODE_SENT]);
        $order = $this->order(['100']);

        $order->setStatusId($shipped->getId())->save();
        $this->listener->onOrderStatusUpdate(new OrderEvent($order));

        self::assertSame('5.00', $this->earningOf($order));
    }

    public function testNothingIsEarnedWhileNotPaidNorWithTheLoyaltyOff(): void
    {
        $notPaid = $this->order(['100']);
        $this->moveTo($notPaid, OrderStatus::CODE_NOT_PAID);

        CreditAccount::setConfigValue(LoyaltySettings::MODE, LoyaltyMode::None->value);
        $off = $this->order(['100']);
        $this->moveTo($off, OrderStatus::CODE_PAID);

        self::assertNull($this->earningOf($notPaid));
        self::assertNull($this->earningOf($off));
    }

    public function testACanceledOrderTakesBackWhatItEarnedAndLeavesTheSpendingToItsPaymentPart(): void
    {
        $customer = $this->customer();
        $this->ledger->record((int) $customer->getId(), CreditAmount::ofDecimal('10'), MovementKind::Adjustment, Author::system(), 'Welcome');
        $order = $this->order(['100'], customer: $customer);
        $this->moveTo($order, OrderStatus::CODE_PAID);
        self::assertSame('15.00', $this->balance->of((int) $customer->getId())->toDecimal());

        $this->moveTo($order, OrderStatus::CODE_CANCELED);
        $this->moveTo($order, OrderStatus::CODE_CANCELED);

        self::assertSame('10.00', $this->balance->of((int) $customer->getId())->toDecimal(), 'Back to the balance before the order.');
        self::assertNull($this->amountOf($order, MovementKind::SpendingRefund), 'The credit spent is given back by the payment part, when the core releases it.');
        self::assertSame('-5.00', $this->amountOf($order, MovementKind::EarningReversal), 'A movement of its own, the earning stays.');
        self::assertSame('5.00', $this->earningOf($order));
    }

    public function testTheCreditThatPaidTheOrderIsDeductedFromTheBaseLikeADiscount(): void
    {
        $order = $this->order(['100']);
        $this->creditPart($order, '30', OrderPaymentPartState::SETTLED);

        $this->moveTo($order, OrderStatus::CODE_PAID);

        self::assertSame('3.50', $this->earningOf($order), '5 % of 100 less the 30 paid with credit.');
    }

    public function testTheCreditPaidIsDeductedInProportionOfTheLinesThatAreNotExcluded(): void
    {
        $excluded = $this->product();
        (new CreditLoyaltyExcludedProduct())->setProductId($excluded->getId())->save();
        $order = $this->order([['50', $excluded->getRef()], '50'], discount: '10');
        $this->creditPart($order, '30', OrderPaymentPartState::SETTLED);

        $this->moveTo($order, OrderStatus::CODE_PAID);

        // 100 of goods, 10 of discount and 30 of credit: the 50 eligible keep 60 % of their value.
        self::assertSame('1.50', $this->earningOf($order));
    }

    public function testACreditPartGivenBackOrNotSettledPaidNothing(): void
    {
        $released = $this->order(['100']);
        $this->creditPart($released, '30', OrderPaymentPartState::RELEASED);
        $pending = $this->order(['100']);
        $this->creditPart($pending, '30', OrderPaymentPartState::PENDING);

        $this->moveTo($released, OrderStatus::CODE_PAID);
        $this->moveTo($pending, OrderStatus::CODE_PAID);

        self::assertSame('5.00', $this->earningOf($released));
        self::assertSame('5.00', $this->earningOf($pending));
    }

    public function testThePartOfAnotherPaymentMeanIsNotDeducted(): void
    {
        $order = $this->order(['100']);
        $this->creditPart($order, '30', OrderPaymentPartState::SETTLED, ModuleQuery::create()->filterById(CreditAccount::getModuleId(), Criteria::NOT_EQUAL)->findOne());

        $this->moveTo($order, OrderStatus::CODE_PAID);

        self::assertSame('5.00', $this->earningOf($order));
    }

    public function testPayingAnOrderForgetsTheAmountChosenOnItsCartAndNothingElse(): void
    {
        $customer = $this->customer();
        $this->ledger->record((int) $customer->getId(), CreditAmount::ofDecimal('50'), MovementKind::Adjustment, Author::system(), 'Welcome');
        $cart = $this->factory->cart($customer);
        $other = $this->factory->cart($customer);
        $usage = new CreditCartUsage($this->balance);
        $usage->set($cart, CreditAmount::ofDecimal('10'));
        $usage->set($other, CreditAmount::ofDecimal('20'));
        $order = $this->order(['100'], customer: $customer);
        $order->setCartId($cart->getId())->save();

        $this->moveTo($order, OrderStatus::CODE_NOT_PAID);
        self::assertSame('10.00', $usage->amountOn($cart)->toDecimal(), 'Unpaid, the order may be presented again.');

        $this->moveTo($order, OrderStatus::CODE_PAID);

        self::assertTrue($usage->amountOn($cart)->isZero());
        self::assertSame('20.00', $usage->amountOn($other)->toDecimal());
    }

    public function testTheEarningTakenBackStopsAtTheBalanceAndSaysWhatWasNot(): void
    {
        $order = $this->order(['200']);
        $customerId = (int) $order->getCustomerId();
        $this->moveTo($order, OrderStatus::CODE_PAID);
        $this->ledger->record($customerId, CreditAmount::ofDecimal('-7'), MovementKind::Adjustment, Author::system(), 'Spent elsewhere');

        $this->moveTo($order, OrderStatus::CODE_REFUNDED);

        $reversal = CreditMovementQuery::create()->filterByOrderId($order->getId())->filterByKind(MovementKind::EarningReversal->value)->findOne();
        self::assertNotNull($reversal);
        self::assertSame('-3.00', CreditAmount::ofDecimal((string) $reversal->getAmount())->toDecimal());
        self::assertStringContainsString('capped at the balance: 3.00 of 10.00', $reversal->getReason());
        self::assertSame('0.00', $this->balance->of($customerId)->toDecimal());
    }

    public function testAnEarningExpiresAfterTheConfiguredDelay(): void
    {
        CreditAccount::setConfigValue(CreditExpiry::ENABLED, 'true');
        CreditAccount::setConfigValue(CreditExpiry::DELAY_IN_MONTHS, '6');
        $order = $this->order(['100']);

        $this->moveTo($order, OrderStatus::CODE_PAID);

        $earning = CreditMovementQuery::create()->filterByOrderId($order->getId())->filterByKind(MovementKind::Earning->value)->findOne();
        self::assertNotNull($earning?->getExpiresAt());
        self::assertSame((new \DateTimeImmutable('+6 months'))->format('Y-m-d'), $earning->getExpiresAt()->format('Y-m-d'));
    }

    /**
     * @param list<string|array{0: string, 1: string}> $lines a price tax included, or [price, product ref]
     */
    private function order(array $lines, string $discount = '0', string $postage = '0', ?Customer $customer = null): Order
    {
        $order = $this->factory->order($customer ?? $this->customer(), ['postage' => $postage]);
        $order->setDiscount($discount)->save();

        foreach ($lines as $line) {
            [$price, $ref] = \is_array($line) ? $line : [$line, null];
            $this->factory->orderProduct($order, array_filter(['price' => $price, 'promoPrice' => $price, 'productRef' => $ref]));
        }

        return $order;
    }

    private function creditPart(Order $order, string $amount, OrderPaymentPartState $state, ?Module $module = null): void
    {
        (new OrderPaymentPart())
            ->setOrderId($order->getId())
            ->setPaymentModuleId($module?->getId() ?? CreditAccount::getModuleId())
            ->setAmount($amount)
            ->setState($state->value)
            ->setPosition(0)
            ->save();
    }

    private function moveTo(Order $order, string $statusCode): void
    {
        $status = OrderStatusQuery::create()->findOneByCode($statusCode);
        self::assertNotNull($status);

        $order->setStatusId($status->getId())->save();
        $this->listener->onOrderStatusUpdate(new OrderEvent($order));
    }

    private function earningOf(Order $order): ?string
    {
        return $this->amountOf($order, MovementKind::Earning);
    }

    private function amountOf(Order $order, MovementKind $kind): ?string
    {
        $movement = CreditMovementQuery::create()->filterByOrderId($order->getId())->filterByKind($kind->value)->findOne();

        return null === $movement ? null : CreditAmount::ofDecimal((string) $movement->getAmount())->toDecimal();
    }

    private function tier(string $min, ?string $max, string $amount): void
    {
        (new CreditLoyaltyTier())->setMin($min)->setMax($max)->setAmount($amount)->save();
    }

    private function product(): \Thelia\Model\Product
    {
        return $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }
}
