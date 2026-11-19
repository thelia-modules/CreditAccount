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

use CreditAccount\EventListener\AnonymizationListener;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\BalanceAudit;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditAccountQuery;
use CreditAccount\Model\CreditMovementQuery;
use CreditAccount\Privacy\CreditDetacher;
use CreditAccount\Privacy\CreditPersonalData;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Model\Customer;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The balance of a customer is personal financial data: exported with its movements, detached when the
 * customer is anonymized, without leaving a cached balance that disagrees with the journal.
 */
final class CreditPrivacyTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CreditLedger $ledger;

    private CreditBalance $balance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->balance = new CreditBalance();
        $this->ledger = new CreditLedger($this->balance);
    }

    public function testAnonymizingDetachesTheMovementsKeepsTheirReasonAndRemovesTheAccount(): void
    {
        $customer = $this->customerWithCredit();

        $this->provider()->anonymizePersonalData($customer);

        self::assertSame(0, CreditMovementQuery::create()->filterByCustomerId($customer->getId())->count());
        self::assertNull(CreditAccountQuery::create()->findOneByCustomerId($customer->getId()));

        $detached = CreditMovementQuery::create()->filterByCustomerId(null)->filterByReason('Welcome gift')->count();
        self::assertGreaterThanOrEqual(1, $detached, 'The movement is still in the journal, with its reason, attached to nobody.');
    }

    public function testTheBalanceAuditFindsNoGapForAnAnonymizedCustomer(): void
    {
        $customer = $this->customerWithCredit();

        $this->provider()->anonymizePersonalData($customer);

        $gaps = (new BalanceAudit())->gaps(Propel::getWriteConnection('TheliaMain'));
        self::assertSame([], array_filter($gaps, static fn (array $gap): bool => $gap['customerId'] === $customer->getId()));
        self::assertTrue($this->balance->of($customer->getId())->isZero());
    }

    public function testTheAnonymizationEventDetachesToo(): void
    {
        $customer = $this->customerWithCredit();

        (new AnonymizationListener(new CreditDetacher()))->onCustomerAnonymize(new CustomerAnonymizeEvent($customer));

        self::assertSame(0, CreditMovementQuery::create()->filterByCustomerId($customer->getId())->count());
        self::assertNull(CreditAccountQuery::create()->findOneByCustomerId($customer->getId()));
    }

    public function testDetachingTwiceChangesNothingMore(): void
    {
        $customer = $this->customerWithCredit();
        $detacher = new CreditDetacher();

        $detacher->detach($customer->getId());
        $detacher->detach($customer->getId());

        self::assertNull(CreditAccountQuery::create()->findOneByCustomerId($customer->getId()));
    }

    public function testAnotherCustomerKeepsTheirJournal(): void
    {
        $anonymized = $this->customerWithCredit();
        $other = $this->customerWithCredit();

        $this->provider()->anonymizePersonalData($anonymized);

        self::assertSame(2, CreditMovementQuery::create()->filterByCustomerId($other->getId())->count());
        self::assertSame('7.50', $this->balance->of($other->getId())->toDecimal());
    }

    public function testTheExportListsTheBalanceAndTheMovements(): void
    {
        $customer = $this->customerWithCredit();

        $data = $this->provider()->exportPersonalData($customer);

        self::assertSame('credit_account', $this->provider()->getPersonalDataSectionName());
        self::assertSame('7.50', $data['balance']);
        self::assertCount(2, $data['movements']);
        self::assertSame('Welcome gift', $data['movements'][0]['reason']);
        self::assertSame('10.000000', $data['movements'][0]['amount']);
        self::assertSame('-2.500000', $data['movements'][1]['amount']);
    }

    public function testTheExportOfACustomerWithoutCreditIsEmpty(): void
    {
        self::assertSame([], $this->provider()->exportPersonalData($this->customer()));
    }

    private function provider(): CreditPersonalData
    {
        return new CreditPersonalData($this->balance, new CreditDetacher());
    }

    private function customerWithCredit(): Customer
    {
        $customer = $this->customer();
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('10'), MovementKind::Adjustment, Author::system(), 'Welcome gift');
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('-2.50'), MovementKind::Adjustment, Author::system(), 'Correction');

        return $customer;
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }
}
