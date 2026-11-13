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
use CreditAccount\Front\PrivateResponseListener;
use CreditAccount\Hook\Theme\CheckoutPaymentPartsThemeHook;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\LiveComponent\CheckoutCredit;
use CreditAccount\Model\CreditCartUsageQuery;
use CreditAccount\Spending\CreditCartUsage;
use CreditAccount\Tests\Support\Payment\CreditTestBalanceProvider;
use CreditAccount\Tests\Support\Payment\PaysWithCredit;
use PHPUnit\Framework\Attributes\Test;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\LiveResponder;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;
use Twig\Environment;

/**
 * The credit account on the payment step: the line the theme draws for the module shows the balance and lets the
 * signed-in buyer apply an amount of it to the cart of the session, or withdraw it. Nothing is drawn for a visitor,
 * for a zero balance or in the line of another means, and an amount the balance does not cover is refused whole.
 */
final class CheckoutCreditBlockTest extends IntegrationTestCase
{
    use PaysWithCredit;

    private Module $creditModule;

    private Module $provider;

    private Customer $customer;

    private CreditLedger $ledger;

    private LiveResponder $responder;

    protected function setUp(): void
    {
        parent::setUp();

        if (!ModuleQuery::create()->findOneByCode(CreditAccount::getModuleCode())?->getActivate()) {
            self::markTestSkipped('The CreditAccount module is not active in the test database.');
        }

        $this->ledger = new CreditLedger(new CreditBalance());
        $this->creditModule = $this->creditAsPaymentModule();
        $this->provider = $this->installPaymentModule(CreditTestBalanceProvider::class, 1001);
        $this->customer = $this->paymentFactory()->customer($this->paymentFactory()->customerTitle());
        $this->responder = new LiveResponder();
    }

    protected function tearDown(): void
    {
        $this->session()?->clearCustomerUser();
        $this->uninstallThePaymentModules();

        parent::tearDown();
    }

    #[Test]
    public function theLineOfTheModuleShowsTheBalanceTheAppliedAmountAndAFieldSentByTheButton(): void
    {
        $this->credit($this->customer, '1234.50');
        $cart = $this->signInWithCart();
        $this->usage()->set($cart, CreditAmount::ofDecimal('20'));

        $html = html_entity_decode($this->hook()->render('checkout.payment.parts', $this->parameters($this->creditModule)));

        self::assertStringContainsString('data-live-name-value="CreditAccount:CheckoutCredit"', $html);
        self::assertStringContainsString($this->formatted('1234.50'), $this->text($html, 'checkout-credit-balance'), 'the balance is written as the shop writes money');
        self::assertNotSame('1234.50', $this->formatted('1234.50'), 'the test shop must format money');
        self::assertStringContainsString($this->formatted('20'), $this->text($html, 'checkout-credit-applied'));
        self::assertStringContainsString('data-live-action-param="apply"', $html, 'the amount is sent by the form, not on each keystroke');
        self::assertStringContainsString('data-model="norender|amount"', $html, 'typing changes the model in the browser only');
        self::assertStringNotContainsString('data-model="amount"', $html);
        self::assertStringContainsString('data-live-action-param="withdraw"', $html);
        self::assertTrue($this->getService(RequestStack::class)->getMainRequest()?->attributes->get(PrivateResponseListener::ATTRIBUTE), 'a page that carries a balance is never kept by a shared cache');
    }

    #[Test]
    public function theLineOfTheModuleHoldsNoWithdrawalWhileNothingIsApplied(): void
    {
        $this->credit($this->customer, '10');
        $this->signInWithCart();

        $html = $this->hook()->render('checkout.payment.parts', $this->parameters($this->creditModule));

        self::assertStringContainsString('checkout-credit-balance', $html);
        self::assertStringNotContainsString('checkout-credit-applied', $html);
        self::assertStringNotContainsString('data-live-action-param="withdraw"', $html);
    }

    #[Test]
    public function nothingIsDrawnForAZeroBalanceForAVisitorNorInTheLineOfAnotherMeans(): void
    {
        self::assertTrue($this->hook()->supports('checkout.payment.parts'));
        self::assertFalse($this->hook()->supports('checkout.payment.extra'));

        $this->credit($this->customer, '10');
        self::assertSame('', $this->hook()->render('checkout.payment.parts', $this->parameters($this->creditModule)), 'a visitor has no credit');

        $this->signInWithCart();
        self::assertSame('', $this->hook()->render('checkout.payment.parts', $this->parameters($this->provider)), 'the line of another means stays as it is');

        $this->signInWithCart(customer: $this->paymentFactory()->customer($this->paymentFactory()->customerTitle()));
        self::assertSame('', $this->hook()->render('checkout.payment.parts', $this->parameters($this->creditModule)), 'a balance of zero draws nothing');
    }

    #[Test]
    public function anAppliedAmountIsWhatTheModuleAnswersTheCoreAndTheStepReadsThePartsAgain(): void
    {
        $this->credit($this->customer, '50');
        $cart = $this->signInWithCart();
        $component = $this->component();
        $component->amount = '12.5';

        $component->apply();

        self::assertNull($component->error);
        self::assertSame('12.50', $this->module()->getAmountAppliedToCart($cart));
        self::assertSame('', $component->amount, 'the field starts again once the amount is applied');
        self::assertSame('12.50', $component->getApplied());
        self::assertSame([['event' => 'PAYMENT_PARTS_CHANGED', 'data' => [], 'target' => null, 'componentName' => null]], $this->responder->getEventsToEmit());
    }

    #[Test]
    public function anAmountAboveTheBalanceIsRefusedWholeNeverCutDownAndTheChoiceStaysAsItWas(): void
    {
        $this->credit($this->customer, '10');
        $cart = $this->signInWithCart();
        $this->usage()->set($cart, CreditAmount::ofDecimal('4'));
        $component = $this->component();
        $component->amount = '10.01';

        $component->apply();

        self::assertStringContainsString($this->formatted('10'), (string) $component->error);
        self::assertStringContainsString('only holds', (string) $component->error);
        self::assertSame('4.00', $this->module()->getAmountAppliedToCart($cart), 'nothing was written');
        self::assertSame([], $this->responder->getEventsToEmit(), 'a refusal changes nothing the step has to read');
        self::assertSame('10.01', $component->amount, 'what the buyer typed stays in the field');
    }

    #[Test]
    public function aBalanceReadAfterTheBlockWasDrawnIsTheOneTheServerJudgesWith(): void
    {
        $this->credit($this->customer, '10');
        $cart = $this->signInWithCart();
        $component = $this->component();
        $component->amount = '8';
        $this->ledger->record((int) $this->customer->getId(), CreditAmount::ofDecimal('-5'), MovementKind::Adjustment, Author::system(), 'Spent elsewhere');

        $component->apply();

        self::assertNotNull($component->error);
        self::assertSame(0, CreditCartUsageQuery::create()->filterByCartId($cart->getId())->count());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableAmounts(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'zero with decimals' => ['0.00'];
        yield 'negative' => ['-1'];
        yield 'three decimals' => ['1.234'];
        yield 'text' => ['ten'];
        yield 'exponent' => ['1e1'];
        yield 'two separators' => ['1,2,3'];
        yield 'too many digits' => ['99999999999'];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('unusableAmounts')]
    public function anAmountThatIsNotAPositiveDecimalWithTwoDecimalsAtMostIsRefusedAndWritesNothing(string $typed): void
    {
        $this->credit($this->customer, '100');
        $cart = $this->signInWithCart();
        $component = $this->component();
        $component->amount = $typed;

        $component->apply();

        self::assertStringContainsString('above zero', (string) $component->error);
        self::assertSame(0, CreditCartUsageQuery::create()->filterByCartId($cart->getId())->count());
        self::assertSame([], $this->responder->getEventsToEmit());
    }

    #[Test]
    public function aCommaIsReadAsTheDecimalSeparatorAndTheWholeBalanceMayBeApplied(): void
    {
        $this->credit($this->customer, '10.50');
        $cart = $this->signInWithCart();
        $component = $this->component();

        $component->amount = ' 3,25 ';
        $component->apply();
        self::assertSame('3.25', $this->module()->getAmountAppliedToCart($cart));

        $component->amount = '10.50';
        $component->apply();
        self::assertNull($component->error);
        self::assertSame('10.50', $this->module()->getAmountAppliedToCart($cart));
    }

    #[Test]
    public function withdrawingForgetsTheAmountAndTheStepReadsThePartsAgain(): void
    {
        $this->credit($this->customer, '50');
        $cart = $this->signInWithCart();
        $this->usage()->set($cart, CreditAmount::ofDecimal('12'));
        $component = $this->component();

        $component->withdraw();

        self::assertSame('0.00', $this->module()->getAmountAppliedToCart($cart));
        self::assertSame(0, CreditCartUsageQuery::create()->filterByCartId($cart->getId())->count());
        self::assertSame('PAYMENT_PARTS_CHANGED', $this->responder->getEventsToEmit()[0]['event'] ?? null);
        self::assertSame('50.00', (new CreditBalance())->of((int) $this->customer->getId())->toDecimal(), 'withdrawing writes no movement');
    }

    #[Test]
    public function theCartOfAnotherCustomerIsNeverTouchedWhateverTheSessionHolds(): void
    {
        $this->credit($this->customer, '50');
        $other = $this->paymentFactory()->customer($this->paymentFactory()->customerTitle());
        $this->credit($other, '50');
        $theirCart = $this->cartOf($other, $this->provider);
        $this->usage()->set($theirCart, CreditAmount::ofDecimal('7'));
        $this->signInWithCart();
        // A session cart that is not the one of the signed-in customer.
        $this->session()->setSessionCart($theirCart);
        $component = $this->component();
        $component->amount = '20';

        $component->apply();
        $component->withdraw();

        self::assertSame('7.00', $this->module()->getAmountAppliedToCart($theirCart), 'neither applied over nor withdrawn');
        self::assertSame(1, CreditCartUsageQuery::create()->filterByCartId($theirCart->getId())->count());
    }

    #[Test]
    public function aVisitorChangesNothing(): void
    {
        $this->credit($this->customer, '50');
        $cart = $this->cartOf($this->customer, $this->provider);
        $this->usage()->set($cart, CreditAmount::ofDecimal('5'));
        $this->session()->clearCustomerUser();
        $this->session()->setSessionCart($cart);
        $component = $this->component();
        $component->amount = '9';

        $component->apply();
        $component->withdraw();

        self::assertSame('5.00', $this->module()->getAmountAppliedToCart($cart));
        self::assertSame('0', $component->getBalance());
        self::assertSame([], $this->responder->getEventsToEmit());
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

    /**
     * @return array{module: int, amount: string}
     */
    private function parameters(Module $module): array
    {
        return ['module' => (int) $module->getId(), 'amount' => '0'];
    }

    private function signInWithCart(string $price = '100.000000', ?Customer $customer = null): Cart
    {
        $customer ??= $this->customer;
        $cart = $this->cartOf($customer, $this->provider, $price);
        $this->session()->setCustomerUser($customer);
        $this->session()->setSessionCart($cart);

        return $cart;
    }

    private function credit(Customer $customer, string $amount): void
    {
        $this->ledger->record((int) $customer->getId(), CreditAmount::ofDecimal($amount), MovementKind::Adjustment, Author::system(), 'Welcome');
    }

    private function usage(): CreditCartUsage
    {
        return new CreditCartUsage(new CreditBalance());
    }

    private function formatted(string $amount): string
    {
        return $this->getService(FormatService::class)->money($amount, Currency::getDefaultCurrency()->getId());
    }

    private function text(string $html, string $testId): string
    {
        self::assertSame(1, preg_match('/data-testid="'.$testId.'"[^>]*>([^<]*)</', $html, $matches), $testId.' is drawn');

        return trim($matches[1]);
    }

    private function hook(): CheckoutPaymentPartsThemeHook
    {
        return new CheckoutPaymentPartsThemeHook(
            $this->getService(Environment::class),
            $this->getService(SecurityContext::class),
            $this->getService(RequestStack::class),
            new CreditBalance(),
        );
    }

    private function component(): CheckoutCredit
    {
        $component = new CheckoutCredit(
            $this->getService(CartFacade::class),
            $this->getService(SecurityContext::class),
            new CreditBalance(),
            $this->usage(),
            $this->getService(FormatService::class),
            $this->getService(TranslatorInterface::class),
        );
        $component->setLiveResponder($this->responder);

        return $component;
    }

    private function module(): CreditAccount
    {
        $instance = $this->creditModule->getPaymentModuleInstance(static::getContainer());

        return $instance instanceof CreditAccount ? $instance : throw new \LogicException('The credit module is not the CreditAccount one.');
    }

    private function session(): ?Session
    {
        $request = $this->getService(RequestStack::class)->getMainRequest();
        $session = $request?->hasSession() ? $request->getSession() : null;

        return $session instanceof Session ? $session : null;
    }
}
