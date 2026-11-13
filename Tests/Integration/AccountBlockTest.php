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

use CreditAccount\BackOffice\CreditJournal;
use CreditAccount\CreditAccount;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\Flexy\CustomerSessionInjector;

/**
 * The balance and the history on the account page of the Flexy theme, through the account.bottom hook point.
 */
final class AccountBlockTest extends WebIntegrationTestCase
{
    private const BLOCK = '[data-testid="credit-account"]';
    private const LINE = '[data-testid="credit-account-line"]';

    private CustomerSessionInjector $injector;

    private FixtureFactory $factory;

    private CreditLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->injector = new CustomerSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
        $this->factory = new FixtureFactory($this->getPropelConnection());
        $this->ledger = new CreditLedger(new CreditBalance());
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        parent::tearDown();
    }

    public function testTheBalanceIsShownOnTheAccountPageAsMoney(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '1234.50', 'Welcome');

        $page = $this->open($customer);

        self::assertCount(1, $page->filter(self::BLOCK));
        $currency = Currency::getDefaultCurrency();
        $expected = $this->formatted('1234.50');

        self::assertSame($expected, trim($page->filter('[data-testid="credit-account-balance"]')->text()));
        self::assertNotSame('1234.50', $expected, 'The test shop must format money, not print the decimal.');
        self::assertStringContainsString((string) $currency->getSymbol(), $expected);
    }

    public function testTheHistoryIsPagedByAQueryParameter(): void
    {
        $customer = $this->customer();

        for ($i = 1; $i <= CreditJournal::PAGE_SIZE + 3; ++$i) {
            $this->credit($customer, '1', 'Gift '.$i);
        }

        $first = $this->open($customer);

        self::assertCount(CreditJournal::PAGE_SIZE, $first->filter(self::LINE));
        self::assertStringContainsString('Gift '.(CreditJournal::PAGE_SIZE + 3), $first->filter(self::BLOCK)->text());
        self::assertStringNotContainsString('Gift 1 ', $first->filter(self::BLOCK)->text().' ');
        self::assertStringContainsString('credit_page=2', (string) $first->filter('[data-testid="credit-account-next"]')->attr('href'));
        self::assertCount(0, $first->filter('[data-testid="credit-account-previous"]'));

        $second = $this->open($customer, ['credit_page' => 2]);

        self::assertCount(3, $second->filter(self::LINE));
        self::assertStringContainsString('Gift 1 ', $second->filter(self::BLOCK)->text().' ');
        self::assertStringNotContainsString('Gift '.(CreditJournal::PAGE_SIZE + 3), $second->filter(self::BLOCK)->text());
        self::assertCount(1, $second->filter('[data-testid="credit-account-previous"]'));
        self::assertCount(0, $second->filter('[data-testid="credit-account-next"]'));
    }

    public function testACustomerNeverSeesTheMovementsOfAnother(): void
    {
        $owner = $this->customer();
        $this->credit($owner, '50', 'Secret gift of the owner');
        $intruder = $this->customer();
        $this->credit($intruder, '2', 'Own gift');

        // The page parameter is the only thing the request controls: no customer id of it picks the movements.
        $page = $this->open($intruder, ['credit_page' => 1, 'customer_id' => $owner->getId(), 'customerId' => $owner->getId()]);

        self::assertStringNotContainsString('Secret gift of the owner', $this->client->getResponse()->getContent());
        self::assertStringContainsString('Own gift', $page->filter(self::BLOCK)->text());
        self::assertSame($this->formatted('2'), trim($page->filter('[data-testid="credit-account-balance"]')->text()));
        self::assertCount(1, $page->filter(self::LINE));
    }

    public function testTheBlockIsShownWithAMessageWhenThereIsNoMovement(): void
    {
        $page = $this->open($this->customer());

        self::assertCount(1, $page->filter(self::BLOCK));
        self::assertCount(1, $page->filter('[data-testid="credit-account-empty"]'));
        self::assertCount(0, $page->filter(self::LINE));
        self::assertSame($this->formatted('0'), trim($page->filter('[data-testid="credit-account-balance"]')->text()));
    }

    public function testThePageIsNeverKeptByASharedCache(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '5', 'Welcome');

        $this->open($customer);
        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');

        self::assertStringNotContainsString('public', $cacheControl);
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
    }

    public function testEachMovementShowsItsKindItsReasonAndASignedAmount(): void
    {
        $customer = $this->customer();
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('10'), MovementKind::Adjustment, Author::system(), 'Gesture');
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('-3.5'), MovementKind::Adjustment, Author::system(), 'Correction');

        $lines = $this->open($customer)->filter(self::LINE);

        self::assertCount(2, $lines);
        // Latest first.
        self::assertStringContainsString('Correction', $lines->eq(0)->text());
        self::assertStringContainsString('Adjustment by the shop', $lines->eq(0)->text());
        self::assertSame($this->formatted('-3.5'), trim($lines->eq(0)->filter('[data-testid="credit-account-line-amount"]')->text()));
        self::assertSame('+'.$this->formatted('10'), trim($lines->eq(1)->filter('[data-testid="credit-account-line-amount"]')->text()));
    }

    public function testTheLabelsAreTranslatedByTheFrontOfficeDomain(): void
    {
        $translator = $this->getService(TranslatorInterface::class);

        self::assertSame('Solde', $translator->trans('Balance', [], CreditAccount::DOMAIN, 'fr_FR'));
    }

    private function formatted(string $amount): string
    {
        return $this->getService(\Thelia\Core\Template\Helper\FormatService::class)->money($amount, Currency::getDefaultCurrency()->getId());
    }

    /**
     * @param array<string, mixed> $query
     */
    private function open(Customer $customer, array $query = []): Crawler
    {
        $this->injector->setCustomer($customer);
        $url = $this->getService(RouterInterface::class)->generate('account_index', $query);
        $page = $this->client->request('GET', $url);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        return $page;
    }

    private function credit(Customer $customer, string $amount, string $reason): void
    {
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal($amount), MovementKind::Adjustment, Author::system(), $reason);
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['email' => 'fo-'.uniqid().'@example.com']);
    }
}
