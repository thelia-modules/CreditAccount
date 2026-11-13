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
use CreditAccount\Event\CreditAccountEvent;
use CreditAccount\EventListener\LegacyCreditListener;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Model\CreditMovementQuery;
use Thelia\Model\Customer;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The 3.x event "creditAccount.addAccount" still credits and debits, through the journal.
 */
final class LegacyCreditEventTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CreditBalance $balance;

    private LegacyCreditListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->balance = new CreditBalance();
        $this->listener = new LegacyCreditListener(new CreditLedger($this->balance), new CreditExpiry());

        CreditAccount::setConfigValue(CreditExpiry::ENABLED, 'true');
        CreditAccount::setConfigValue(CreditExpiry::DELAY_IN_MONTHS, '12');
    }

    public function testAPositiveAmountIsAnAdjustmentWithItsReasonItsAuthorAndAnExpiry(): void
    {
        $customer = $this->customer();
        $event = (new CreditAccountEvent($customer, '12.50'))->setWhoDidIt('Jane Doe')->setReason('Sponsorship reward');

        $this->listener->onAddAmount($event);

        $movement = CreditMovementQuery::create()->filterByCustomerId($customer->getId())->findOne();
        self::assertSame('adjustment', $movement->getKind());
        self::assertSame('12.500000', (string) $movement->getAmount());
        self::assertSame('Sponsorship reward', $movement->getReason());
        self::assertSame('system', $movement->getAuthorType());
        self::assertSame('Jane Doe', $movement->getAuthorName());
        self::assertNotNull($movement->getExpiresAt());
        self::assertSame('12.50', $this->balance->of($customer->getId())->toDecimal());
        self::assertSame('12.500000', (string) $event->getCreditAccount()->getAmount());
    }

    public function testAFloatAmountAndTheOrderAreKeptWithoutADefaultReasonBeingEmpty(): void
    {
        $customer = $this->customer();

        $this->listener->onAddAmount(new CreditAccountEvent($customer, 0.1 + 0.2, 42));

        $movement = CreditMovementQuery::create()->filterByCustomerId($customer->getId())->findOne();
        self::assertSame('0.300000', (string) $movement->getAmount());
        self::assertSame('Credit moved by a module (order #42)', $movement->getReason());
    }

    public function testANegativeAmountWithinTheBalanceDebits(): void
    {
        $customer = $this->customer();
        $this->listener->onAddAmount(new CreditAccountEvent($customer, '10'));

        $this->listener->onAddAmount(new CreditAccountEvent($customer, '-4'));

        self::assertSame('6.00', $this->balance->of($customer->getId())->toDecimal());
        self::assertNull(CreditMovementQuery::create()->filterByCustomerId($customer->getId())->filterByAmount('-4', '=')->findOne()->getExpiresAt());
    }

    public function testANegativeAmountAboveTheBalanceIsRefusedAndTheBalanceStays(): void
    {
        $customer = $this->customer();
        $this->listener->onAddAmount(new CreditAccountEvent($customer, '3'));

        try {
            $this->listener->onAddAmount(new CreditAccountEvent($customer, '-3.01'));
            self::fail('The balance was made negative.');
        } catch (InsufficientCreditException) {
            self::assertSame('3.00', $this->balance->of($customer->getId())->toDecimal());
        }

        self::assertSame(1, CreditMovementQuery::create()->filterByCustomerId($customer->getId())->count());
    }

    public function testAZeroAmountWritesNothing(): void
    {
        $customer = $this->customer();

        $this->listener->onAddAmount(new CreditAccountEvent($customer, '0'));

        self::assertSame(0, CreditMovementQuery::create()->filterByCustomerId($customer->getId())->count());
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }
}
