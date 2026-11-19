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
use CreditAccount\Expiration\CreditExpirer;
use CreditAccount\Expiration\CreditLots;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\BalanceAudit;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditMovementQuery;
use PHPUnit\Framework\MockObject\MockObject;
use Thelia\Mailer\Exception\EmailNotSentException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Customer;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * An expiry takes what is left of a credit once the debits were charged to the oldest expiry first, once, and a
 * customer is warned once per credit.
 */
final class CreditExpirationTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    private CreditLedger $ledger;

    private CreditBalance $balance;

    private MailerFactory&MockObject $mailer;

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createFixtureFactory();
        $this->balance = new CreditBalance();
        $this->ledger = new CreditLedger($this->balance);
        $this->mailer = $this->createMock(MailerFactory::class);
        $this->now = new \DateTimeImmutable('2026-06-15 12:00:00');

        CreditAccount::setConfigValue(CreditExpiry::ENABLED, 'true');
        CreditAccount::setConfigValue(CreditExpiry::WARNING_DAYS, '30');
    }

    public function testTheUnspentRemainderOfAnExpiredCreditIsTakenOnceAndTheOtherCreditsStay(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '10', '2026-06-01');
        $this->credit($customer, '10', '2027-01-01');
        $this->spend($customer, '4');

        $report = $this->expirer()->run($this->now, 10);
        $again = $this->expirer()->run($this->now, 10);

        self::assertSame(1, $report->movementsExpired);
        self::assertSame('6.00', $report->amountExpired->toDecimal());
        self::assertSame(0, $again->movementsExpired, 'Replaying the command expires nothing twice.');
        self::assertSame('10.00', $this->balance->of($customer->getId())->toDecimal(), 'Only the second credit is left.');

        $expiration = CreditMovementQuery::create()->filterByCustomerId($customer->getId())->filterByKind(MovementKind::Expiration->value)->findOne();
        self::assertSame('-6.000000', (string) $expiration->getAmount());
        self::assertSame('system', $expiration->getAuthorType());
        self::assertStringContainsString('expired unspent', $expiration->getReason());
    }

    public function testDebitsAreChargedToTheOldestExpiryFirstWhateverTheOrderOfWriting(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '10', '2026-06-10');
        $this->credit($customer, '10', '2026-06-01');
        $this->spend($customer, '15');

        $report = $this->expirer()->run($this->now, 10);

        self::assertSame('5.00', $report->amountExpired->toDecimal(), '15 spent: the credit of June 1 and 5 of the one of June 10 are gone, 5 are left to expire.');
        self::assertSame(1, $report->movementsExpired);
        self::assertTrue($this->balance->of($customer->getId())->isZero());
    }

    public function testACreditThatNeverExpiresIsSpentLastAndNeverTaken(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '10', null);
        $this->credit($customer, '10', '2026-06-01');
        $this->spend($customer, '10');

        $report = $this->expirer()->run($this->now, 10);

        self::assertSame(0, $report->movementsExpired, 'The spending went to the credit that expires.');
        self::assertSame('10.00', $this->balance->of($customer->getId())->toDecimal());
    }

    public function testACreditExpiringWithinTheDelayIsAnnouncedOnceWithWhatIsLeftOfIt(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '10', '2026-07-01');
        $this->credit($customer, '8', '2026-12-01');
        $this->spend($customer, '3');

        $this->mailer->expects(self::once())->method('sendEmailToCustomerOrFail')->with(
            CreditExpirer::WARNING_MESSAGE,
            self::callback(static fn (Customer $recipient): bool => true),
            self::callback(static fn (array $parameters): bool => '7.00' === $parameters['expiring_total']
                && [['amount' => '7.00', 'expires_on' => '2026-07-01']] === $parameters['expiring_lots']
                && 30 === $parameters['warning_days']),
        );

        $first = $this->expirer()->run($this->now, 10);
        $second = $this->expirer()->run($this->now, 10);

        self::assertSame(1, $first->warningsSent);
        self::assertSame(0, $second->warningsSent);
        self::assertNotNull(CreditMovementQuery::create()->filterByCustomerId($customer->getId())->filterByAmount('10', '=')->findOne()->getWarnedAt());
    }

    public function testACreditSpentInFullIsNotAnnounced(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '10', '2026-07-01');
        $this->credit($customer, '10', '2026-12-01');
        $this->spend($customer, '10');

        $this->mailer->expects(self::never())->method('sendEmailToCustomerOrFail');

        $this->expirer()->run($this->now, 10);
    }

    public function testAWarningTheMailerRefusedIsTriedAgainNextRun(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '10', '2026-07-01');

        $this->mailer->expects(self::exactly(2))->method('sendEmailToCustomerOrFail')
            ->willReturnOnConsecutiveCalls(
                self::throwException(EmailNotSentException::sendingFailed(CreditExpirer::WARNING_MESSAGE, new \RuntimeException('down'))),
                null,
            );

        $failed = $this->expirer()->run($this->now, 10);
        self::assertSame(1, $failed->warningsFailed);
        self::assertNull(CreditMovementQuery::create()->filterByCustomerId($customer->getId())->findOne()->getWarnedAt());

        $retried = $this->expirer()->run($this->now, 10);
        self::assertSame(1, $retried->warningsSent);
    }

    public function testARunHandlesAtMostTheLimitAndTheNextOneContinues(): void
    {
        $customers = [$this->customer(), $this->customer(), $this->customer()];

        foreach ($customers as $index => $customer) {
            $this->credit($customer, '5', \sprintf('2026-05-0%d', $index + 1));
        }

        $first = $this->expirer()->run($this->now, 2);
        $second = $this->expirer()->run($this->now, 2);
        $third = $this->expirer()->run($this->now, 2);

        self::assertSame(2, $first->customersHandled);
        self::assertSame(1, $second->customersHandled);
        self::assertSame(0, $third->customersHandled);

        foreach ($customers as $customer) {
            self::assertTrue($this->balance->of($customer->getId())->isZero());
        }
    }

    public function testAnExpiredAndFullySpentCreditDoesNotUseUpTheLimit(): void
    {
        $spent = $this->customer();
        $this->credit($spent, '5', '2026-05-01');
        $this->spend($spent, '5');
        $this->credit($spent, '5', '2027-05-01');

        $waiting = $this->customer();
        $this->credit($waiting, '5', '2026-05-02');

        $report = $this->expirer()->run($this->now, 1);

        self::assertSame(1, $report->customersHandled);
        self::assertTrue($this->balance->of($waiting->getId())->isZero());
    }

    public function testNothingHappensWhenExpirationIsOffOrTheCreditIsDetached(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '5', '2026-05-01');
        CreditAccount::setConfigValue(CreditExpiry::ENABLED, 'false');

        self::assertSame(0, $this->expirer()->run($this->now, 10)->movementsExpired);
        self::assertSame('5.00', $this->balance->of($customer->getId())->toDecimal());
    }

    public function testTheBalanceCacheStaysTheSumOfTheMovementsAfterAnExpiry(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '9.99', '2026-05-01');

        $this->expirer()->run($this->now, 10);

        self::assertSame([], array_filter($this->gaps(), static fn (array $gap): bool => $gap['customerId'] === $customer->getId()));
    }

    public function testTheLotsOfACustomerShowWhatEachDebitReached(): void
    {
        $customer = $this->customer();
        $this->credit($customer, '10', '2026-07-01');
        $this->spend($customer, '4');

        $lots = (new CreditLots())->unspentOf($customer->getId(), \Propel\Runtime\Propel::getWriteConnection('TheliaMain'));

        self::assertCount(1, $lots);
        self::assertSame('6.00', $lots[0]->remaining->toDecimal());
    }

    /**
     * @return list<array{customerId: int}>
     */
    private function gaps(): array
    {
        return (new BalanceAudit())->gaps(\Propel\Runtime\Propel::getWriteConnection('TheliaMain'));
    }

    private function expirer(): CreditExpirer
    {
        return new CreditExpirer($this->ledger, new CreditLots(), new CreditExpiry(), $this->mailer);
    }

    private function credit(Customer $customer, string $amount, ?string $expiresOn): void
    {
        $this->ledger->record(
            $customer->getId(),
            CreditAmount::ofDecimal($amount),
            MovementKind::Adjustment,
            Author::system(),
            'Gift',
            expiresAt: null === $expiresOn ? null : new \DateTimeImmutable($expiresOn.' 00:00:00'),
        );
    }

    private function spend(Customer $customer, string $amount): void
    {
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal($amount)->negated(), MovementKind::Adjustment, Author::system(), 'Spent');
    }

    private function customer(): Customer
    {
        return $this->factory->customer($this->factory->customerTitle());
    }
}
