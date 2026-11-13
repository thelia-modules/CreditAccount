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

use CreditAccount\BackOffice\CreditConfiguration;
use CreditAccount\BackOffice\CreditJournal;
use CreditAccount\CreditAccount;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditLoyaltyExcludedCategoryQuery;
use CreditAccount\Model\CreditLoyaltyExcludedProductQuery;
use CreditAccount\Model\CreditLoyaltyTierQuery;
use CreditAccount\Model\CreditMovementQuery;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\OrderStatus;
use Thelia\Test\FixtureFactory;
use Thelia\Test\WebIntegrationTestCase;
use Thelia\Tests\Support\BackOffice\AdminSessionInjector;

/**
 * The back-office of the module in the Twig administration: the configuration page, the customer sheet and the
 * order. Every gesture is replayed without the right, without a CSRF token and with a field the form does not have.
 */
final class BackOfficeScreensTest extends WebIntegrationTestCase
{
    private const CONFIGURATION_PAGE = '/admin/module/CreditAccount';
    private const CONFIGURATION_FORM = 'creditaccount_configuration';
    private const ADJUSTMENT_FORM = 'creditaccount_adjustment';

    private AdminSessionInjector $injector;

    private FixtureFactory $factory;

    private CreditLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        if ('default-twig' !== ConfigQuery::read('active-admin-template')) {
            self::fail('The Twig back-office must be the active admin template of the test shop.');
        }

        $this->injector = new AdminSessionInjector();
        $this->getService(EventDispatcherInterface::class)->addSubscriber($this->injector);
        $this->factory = new FixtureFactory($this->getPropelConnection());
        $this->ledger = new CreditLedger(new CreditBalance());
    }

    protected function tearDown(): void
    {
        if (isset($this->injector)) {
            $this->injector->clear();
        }

        ConfigQuery::resetCache();
        parent::tearDown();
    }

    public function testTheConfigurationPageShowsTheFormToAnAuthorizedAdministrator(): void
    {
        $crawler = $this->open($this->factory->admin(), self::CONFIGURATION_PAGE);

        self::assertCount(1, $crawler->filter('[data-testid="creditaccount-config-form"]'));
        self::assertCount(1, $crawler->filter('select[name="'.self::CONFIGURATION_FORM.'[loyalty_mode]"]'));
        self::assertCount(1, $crawler->filter('input[name="'.self::CONFIGURATION_FORM.'[_token]"]'));
        self::assertCount(1, $crawler->filter('script[src$="configuration.js"]'), 'The script is published by module_asset().');
        self::assertFileExists(THELIA_WEB_DIR.ltrim((string) parse_url((string) $crawler->filter('script[src$="configuration.js"]')->attr('src'), \PHP_URL_PATH), '/'));
    }

    public function testTheConfigurationFormIsLeftOutForAnAdministratorWhoCannotChangeTheModule(): void
    {
        $reader = $this->factory->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW]]);
        $this->injector->setAdmin($reader);
        $reader->eraseCredentials();

        $this->client->request('POST', '/admin/module/CreditAccount/save', [self::CONFIGURATION_FORM => $this->configurationFields()]);

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, CreditLoyaltyTierQuery::create()->count(), 'Nothing was saved.');
    }

    public function testAnAnonymousVisitorIsSentToTheLoginPage(): void
    {
        $this->client->request('POST', '/admin/module/CreditAccount/save', [self::CONFIGURATION_FORM => $this->configurationFields()]);

        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertStringContainsString('/admin/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testTheConfigurationIsSavedWithItsTiersAndItsExclusions(): void
    {
        $category = $this->factory->category();
        $product = $this->product($category);
        $crawler = $this->open($this->factory->admin(), self::CONFIGURATION_PAGE);

        $this->post('/admin/module/CreditAccount/save', self::CONFIGURATION_FORM, $this->configurationFields([
            'expiration_enabled' => '1',
            'expiration_delay' => '12',
            'expiration_warning_days' => '15',
            'loyalty_trigger_status' => OrderStatus::CODE_SENT,
            'loyalty_mode' => 'grid',
            'tiers' => [
                ['min' => '0', 'max' => '100', 'amount' => '2'],
                ['min' => '100', 'max' => '', 'amount' => '10.50'],
            ],
            'excluded_categories' => [(string) $category->getId()],
            'excluded_products' => $product->getRef(),
        ]), $this->token($crawler, self::CONFIGURATION_FORM));

        self::assertTrue($this->client->getResponse()->isRedirect(), 'The administrator is sent back to the page.');

        $settings = $this->getService(CreditConfiguration::class)->current();

        self::assertTrue($settings['expiration_enabled']);
        self::assertSame(12, $settings['expiration_delay']);
        self::assertSame(15, $settings['expiration_warning_days']);
        self::assertSame(OrderStatus::CODE_SENT, $settings['loyalty_trigger_status']);
        self::assertSame('grid', $settings['loyalty_mode']);
        self::assertSame([['min' => '0.00', 'max' => '100.00', 'amount' => '2.00'], ['min' => '100.00', 'max' => null, 'amount' => '10.50']], $settings['tiers']);
        self::assertSame([$category->getId()], $settings['excluded_categories']);
        self::assertSame($product->getRef(), $settings['excluded_products']);
        self::assertSame(1, CreditLoyaltyExcludedProductQuery::create()->count());
        self::assertSame(1, CreditLoyaltyExcludedCategoryQuery::create()->count());
    }

    public function testAnOverlappingGridIsRefusedAndShownAgainWithItsError(): void
    {
        $crawler = $this->open($this->factory->admin(), self::CONFIGURATION_PAGE);

        $this->post('/admin/module/CreditAccount/save', self::CONFIGURATION_FORM, $this->configurationFields([
            'loyalty_mode' => 'grid',
            'tiers' => [
                ['min' => '0', 'max' => '100', 'amount' => '2'],
                ['min' => '50', 'max' => '', 'amount' => '5'],
            ],
        ]), $this->token($crawler, self::CONFIGURATION_FORM));

        self::assertSame(0, CreditLoyaltyTierQuery::create()->count(), 'Nothing was saved.');

        $page = $this->client->request('GET', self::CONFIGURATION_PAGE);

        self::assertStringContainsString('cover the same amounts', $page->filter('[data-testid="creditaccount-grid"]')->text());
        self::assertCount(2, $page->filter('[data-testid="creditaccount-tier-row"]')->reduce(static fn (Crawler $row): bool => '' !== (string) $row->filter('input')->first()->attr('value')), 'The rows are shown as they were typed.');
    }

    public function testAConfigurationWithoutCsrfTokenOrWithAnUnknownFieldIsRefused(): void
    {
        $crawler = $this->open($this->factory->admin(), self::CONFIGURATION_PAGE);
        $token = $this->token($crawler, self::CONFIGURATION_FORM);
        $fields = $this->configurationFields(['loyalty_mode' => 'percentage', 'loyalty_percentage' => '5']);

        $this->post('/admin/module/CreditAccount/save', self::CONFIGURATION_FORM, $fields, null);
        self::assertSame('none', $this->getService(CreditConfiguration::class)->current()['loyalty_mode'], 'No token, no change.');

        $this->post('/admin/module/CreditAccount/save', self::CONFIGURATION_FORM, $fields + ['unexpected' => '1'], $token);
        self::assertSame('none', $this->getService(CreditConfiguration::class)->current()['loyalty_mode'], 'An unknown field refuses the form.');
    }

    public function testTheCustomerSheetShowsTheBalanceAndAPaginatedJournal(): void
    {
        $customer = $this->customer();

        for ($i = 1; $i <= CreditJournal::PAGE_SIZE + 3; ++$i) {
            $this->credit($customer, '1', 'Gift '.$i);
        }

        $crawler = $this->open($this->factory->admin(), $this->sheet($customer));

        self::assertStringContainsString('23.00', $crawler->filter('[data-testid="creditaccount-balance"]')->text());
        self::assertCount(CreditJournal::PAGE_SIZE, $crawler->filter('[data-testid="creditaccount-journal-line"]'));
        self::assertCount(1, $crawler->filter('[data-testid="creditaccount-pagination"]'));

        $second = $this->client->request('GET', $this->sheet($customer).'&credit_page=2');

        self::assertCount(3, $second->filter('[data-testid="creditaccount-journal-line"]'));
        self::assertStringContainsString('Gift 1', $second->filter('[data-testid="creditaccount-journal"]')->text());
    }

    public function testAnAdministratorWithTheManualRightCreditsThenDebitsWithAReason(): void
    {
        $customer = $this->customer();
        $crawler = $this->open($this->writer(), $this->sheet($customer));

        $this->post($this->adjustUrl($customer), self::ADJUSTMENT_FORM, ['direction' => 'credit', 'amount' => '30', 'reason' => 'Goodwill'], $this->token($crawler, self::ADJUSTMENT_FORM));
        $crawler = $this->client->request('GET', $this->sheet($customer));
        $this->post($this->adjustUrl($customer), self::ADJUSTMENT_FORM, ['direction' => 'debit', 'amount' => '12.50', 'reason' => 'Correction'], $this->token($crawler, self::ADJUSTMENT_FORM));

        $movements = CreditMovementQuery::create()->filterByCustomerId($customer->getId())->orderById()->find();
        self::assertSame(['30.00', '-12.50'], array_map(static fn ($movement): string => CreditAmount::ofDecimal((string) $movement->getAmount())->toDecimal(), iterator_to_array($movements, false)));
        self::assertSame(['Goodwill', 'Correction'], array_map(static fn ($movement): string => (string) $movement->getReason(), iterator_to_array($movements, false)));
        self::assertSame('17.50', (new CreditBalance())->of($customer->getId())->toDecimal());
        self::assertSame(2, AdminLogQuery::create()->filterByResource(CreditLedger::ADMIN_LOG_RESOURCE)->count(), 'Each gesture is in the admin log.');
    }

    public function testAMovementWithoutReasonOrBeyondTheBalanceIsRefused(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '10');
        $crawler = $this->open($this->writer(), $this->sheet($customer));
        $token = $this->token($crawler, self::ADJUSTMENT_FORM);

        $this->post($this->adjustUrl($customer), self::ADJUSTMENT_FORM, ['direction' => 'credit', 'amount' => '5', 'reason' => '  '], $token);
        $this->post($this->adjustUrl($customer), self::ADJUSTMENT_FORM, ['direction' => 'debit', 'amount' => '10.01', 'reason' => 'Too much'], $token);

        self::assertSame('10.00', (new CreditBalance())->of($customer->getId())->toDecimal());
        self::assertSame(1, CreditMovementQuery::create()->filterByCustomerId($customer->getId())->count());

        $page = $this->client->request('GET', $this->sheet($customer));
        $errors = implode(' | ', $page->filter('[data-testid="bo-flash-error"]')->each(static fn (Crawler $error): string => trim($error->text())));
        self::assertStringContainsString('check the fields below', $errors, 'A movement without reason is refused.');
        self::assertStringContainsString('The debit of 10.01 is refused: the balance is 10.00', $errors);
    }

    public function testAMovementWithoutCsrfTokenOrWithAnUnknownFieldIsRefused(): void
    {
        $customer = $this->customer();
        $crawler = $this->open($this->writer(), $this->sheet($customer));
        $token = $this->token($crawler, self::ADJUSTMENT_FORM);
        $fields = ['direction' => 'credit', 'amount' => '5', 'reason' => 'Gift'];

        $this->post($this->adjustUrl($customer), self::ADJUSTMENT_FORM, $fields, null);
        $this->post($this->adjustUrl($customer), self::ADJUSTMENT_FORM, $fields + ['kind' => 'earning'], $token);

        self::assertSame(0, CreditMovementQuery::create()->filterByCustomerId($customer->getId())->count());
    }

    public function testTheCustomerReadRightSeesTheJournalButCannotWrite(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '4');
        $reader = $this->factory->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::UPDATE]]);

        $crawler = $this->open($reader, $this->sheet($customer));

        self::assertCount(1, $crawler->filter('[data-testid="creditaccount-journal-line"]'));
        self::assertCount(0, $crawler->filter('[data-testid="creditaccount-adjust-form"]'), 'No form without the manual right.');

        $this->post($this->adjustUrl($customer), self::ADJUSTMENT_FORM, ['direction' => 'credit', 'amount' => '5', 'reason' => 'Sneaky'], 'whatever');

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame(1, CreditMovementQuery::create()->filterByCustomerId($customer->getId())->count());
    }

    public function testTheOrderListsTheMovementsOfTheOrder(): void
    {
        $customer = $this->customer();
        $order = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $other = $this->factory->order($customer, ['statusCode' => OrderStatus::CODE_PAID]);
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('7'), MovementKind::Earning, Author::system(), orderId: $order->getId());
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('3'), MovementKind::Earning, Author::system(), orderId: $other->getId());

        $crawler = $this->open($this->factory->admin(), '/admin/order/update/'.$order->getId());

        $lines = $crawler->filter('[data-testid="creditaccount-order-line"]');
        self::assertCount(1, $lines);
        self::assertStringContainsString('7.00', $lines->text());
        self::assertCount(0, $crawler->filter('[data-testid="creditaccount-order-empty"]'));
    }

    private function open(Admin $admin, string $url): Crawler
    {
        $admin->eraseCredentials();
        $this->injector->setAdmin($admin);

        $crawler = $this->client->request('GET', $url);
        self::assertSame(200, $this->client->getResponse()->getStatusCode(), $url.' -> '.$this->client->getResponse()->headers->get('Location'));

        return $crawler;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function post(string $url, string $formName, array $fields, ?string $token): void
    {
        $this->client->request('POST', $url, [$formName => $fields + (null === $token ? [] : ['_token' => $token])]);
    }

    private function token(Crawler $crawler, string $formName): string
    {
        $field = $crawler->filter('input[name="'.$formName.'[_token]"]');
        self::assertCount(1, $field, 'The form carries a CSRF token.');

        return (string) $field->attr('value');
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function configurationFields(array $overrides = []): array
    {
        return $overrides + [
            'expiration_delay' => '18',
            'expiration_warning_days' => '30',
            'loyalty_trigger_status' => OrderStatus::CODE_PAID,
            'loyalty_mode' => 'none',
            'loyalty_percentage' => '',
            'tiers' => [],
            'excluded_products' => '',
        ];
    }

    private function writer(): Admin
    {
        return $this->factory->restrictedAdmin([
            CreditAccount::MANUAL_CREDIT_RESOURCE => [AccessManager::CREATE],
            AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::UPDATE],
        ]);
    }

    private function sheet(Customer $customer): string
    {
        return '/admin/customer/update?customer_id='.$customer->getId();
    }

    private function adjustUrl(Customer $customer): string
    {
        return \sprintf('/admin/module/CreditAccount/customer/%d/adjust', $customer->getId());
    }

    private function credit(Customer $customer, string $amount, string $reason = 'Welcome'): void
    {
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal($amount), MovementKind::Adjustment, Author::system(), $reason);
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle(), ['email' => 'bo-'.uniqid().'@example.com']);
    }

    private function product(\Thelia\Model\Category $category): \Thelia\Model\Product
    {
        return $this->factory->product($category, $this->factory->taxRule(), $this->factory->currency());
    }
}
