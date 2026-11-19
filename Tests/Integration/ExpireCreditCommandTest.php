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

use CreditAccount\Command\ExpireCreditCommand;
use CreditAccount\CreditAccount;
use CreditAccount\Expiration\CreditExpirer;
use CreditAccount\Expiration\CreditLots;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Customer;
use Thelia\Model\MessageQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

final class ExpireCreditCommandTest extends IntegrationTestCase
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

        CreditAccount::setConfigValue(CreditExpiry::ENABLED, 'true');
        CreditAccount::setConfigValue(CreditExpiry::WARNING_DAYS, '30');
    }

    public function testTheLimitOptionBoundsARunAndTheNextRunContinues(): void
    {
        $customers = [$this->customerWithAnExpiredCredit('2026-01-01'), $this->customerWithAnExpiredCredit('2026-01-02'), $this->customerWithAnExpiredCredit('2026-01-03')];
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '2']));
        self::assertStringContainsString('2 customer(s) handled', $tester->getDisplay());
        self::assertStringContainsString('run the command again', $tester->getDisplay());
        self::assertTrue($this->balance->of($customers[0]->getId())->isZero());
        self::assertTrue($this->balance->of($customers[1]->getId())->isZero());
        self::assertSame('5.00', $this->balance->of($customers[2]->getId())->toDecimal());

        $tester->execute(['--limit' => '2']);
        self::assertTrue($this->balance->of($customers[2]->getId())->isZero());
    }

    public function testALimitThatIsNotAPositiveWholeNumberIsRefused(): void
    {
        $customer = $this->customerWithAnExpiredCredit('2026-01-01');
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['--limit' => '0']));
        self::assertSame(Command::INVALID, $tester->execute(['--limit' => 'many']));
        self::assertSame('5.00', $this->balance->of($customer->getId())->toDecimal());
    }

    public function testNothingIsDoneWhenExpirationIsOff(): void
    {
        $customer = $this->customerWithAnExpiredCredit('2026-01-01');
        CreditAccount::setConfigValue(CreditExpiry::ENABLED, 'false');
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('disabled', $tester->getDisplay());
        self::assertSame('5.00', $this->balance->of($customer->getId())->toDecimal());
    }

    public function testTheWarningMessageIsCreatedAndItsTemplateRendersWithRealisticVariables(): void
    {
        MessageQuery::create()->filterByName(CreditExpirer::WARNING_MESSAGE)->delete();

        $activation = new \ReflectionMethod(CreditAccount::class, 'ensureMessages');
        $activation->invoke(new CreditAccount());

        $message = MessageQuery::create()->findOneByName(CreditExpirer::WARNING_MESSAGE);
        self::assertNotNull($message);
        self::assertSame('expiration-warning.html', $message->getHtmlTemplateFileName());

        $mailer = $this->getService(MailerFactory::class);
        $email = $mailer->createEmailMessage(
            CreditExpirer::WARNING_MESSAGE,
            ['shop@example.com' => 'Shop'],
            ['customer@example.com' => 'Customer'],
            [
                'expiring_total' => '12.50',
                'expiring_lots' => [['amount' => '7.50', 'expires_on' => '2026-07-01'], ['amount' => '5.00', 'expires_on' => '2026-07-09']],
                'warning_days' => 30,
            ],
            'en_US',
        );

        self::assertStringContainsString('2026-07-09', (string) $email->getHtmlBody());
        self::assertStringContainsString('Total expiring: 12.50', (string) $email->getTextBody());
        self::assertStringContainsString('within 30 days', (string) $email->getTextBody());
    }

    private function tester(): CommandTester
    {
        $mailer = $this->createMock(MailerFactory::class);
        $expiry = new CreditExpiry();

        return new CommandTester(new ExpireCreditCommand(new CreditExpirer($this->ledger, new CreditLots(), $expiry, $mailer), $expiry));
    }

    private function customerWithAnExpiredCredit(string $expiredOn): Customer
    {
        $customer = $this->factory->customer($this->factory->customerTitle());
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal('5'), MovementKind::Adjustment, Author::system(), 'Gift', expiresAt: new \DateTimeImmutable($expiredOn.' 00:00:00'));

        return $customer;
    }
}
