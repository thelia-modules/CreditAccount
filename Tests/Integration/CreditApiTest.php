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
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditMovementQuery;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\Customer;
use Thelia\Test\ApiTestCase;

/**
 * The credit API: the balance and the journal of the customer of the token on the front, the same of a named
 * customer plus the manual credit on the administration side. Every operation is replayed without a token, with
 * an account that has no business there, and across two customers.
 */
final class CreditApiTest extends ApiTestCase
{
    private const FRONT_BALANCE = '/api/front/account/credit/balance';
    private const FRONT_MOVEMENTS = '/api/front/account/credit/movements';

    private CreditLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = new CreditLedger(new CreditBalance(), $this->getService(\Symfony\Component\HttpFoundation\RequestStack::class));
    }

    public function testACustomerReadsTheBalanceOfHisOwnAccountOnly(): void
    {
        [$alice, $bob] = [$this->customer('alice'), $this->customer('bob')];
        $this->credit($alice, '12.50');
        $this->credit($bob, '3');

        $aliceBalance = $this->jsonRequest('GET', self::FRONT_BALANCE, token: $this->authenticateAsCustomer($alice));
        $bobBalance = $this->jsonRequest('GET', self::FRONT_BALANCE, token: $this->authenticateAsCustomer($bob));

        self::assertSame(Response::HTTP_OK, $aliceBalance->getStatusCode());
        self::assertSame('12.50', self::decodeJson($aliceBalance)['balance']);
        self::assertSame('3.00', self::decodeJson($bobBalance)['balance']);
        self::assertArrayNotHasKey('customerId', self::decodeJson($aliceBalance));
    }

    public function testTheHistoryOfACustomerIsPaginatedNewestFirst(): void
    {
        $alice = $this->customer('alice');
        $this->credit($alice, '1', 'first');
        $this->credit($alice, '2', 'second');
        $this->credit($alice, '4', 'third');
        $token = $this->authenticateAsCustomer($alice);

        $firstPage = self::decodeJson($this->jsonRequest('GET', self::FRONT_MOVEMENTS.'?itemsPerPage=2', token: $token));
        $secondPage = self::decodeJson($this->jsonRequest('GET', self::FRONT_MOVEMENTS.'?itemsPerPage=2&page=2', token: $token));

        self::assertSame(3, $firstPage['hydra:totalItems']);
        self::assertSame(['third', 'second'], array_column($firstPage['hydra:member'], 'reason'));
        self::assertSame(['first'], array_column($secondPage['hydra:member'], 'reason'));
        self::assertSame('4.00', $firstPage['hydra:member'][0]['amount']);
        self::assertSame('adjustment', $firstPage['hydra:member'][0]['kind']);
        self::assertArrayNotHasKey('authorName', $firstPage['hydra:member'][0]);
        self::assertArrayNotHasKey('adminId', $firstPage['hydra:member'][0]);
    }

    public function testACustomerNeverSeesTheMovementsOfAnotherCustomer(): void
    {
        [$alice, $bob] = [$this->customer('alice'), $this->customer('bob')];
        $this->credit($alice, '10', 'alice only');
        $this->credit($bob, '5', 'bob only');
        $token = $this->authenticateAsCustomer($bob);

        foreach ([self::FRONT_MOVEMENTS, self::FRONT_MOVEMENTS.'?customerId='.$alice->getId(), self::FRONT_MOVEMENTS.'?customer_id='.$alice->getId()] as $uri) {
            $reasons = array_column(self::decodeJson($this->jsonRequest('GET', $uri, token: $token))['hydra:member'], 'reason');

            self::assertSame(['bob only'], $reasons, $uri);
        }
    }

    public function testTheFrontReadsNeedACustomerToken(): void
    {
        $alice = $this->customer('alice');
        $this->credit($alice, '10');
        $admin = $this->authenticateAsAdmin();

        foreach ([self::FRONT_BALANCE, self::FRONT_MOVEMENTS] as $uri) {
            self::assertSame(Response::HTTP_UNAUTHORIZED, $this->jsonRequest('GET', $uri)->getStatusCode(), $uri.' without a token');
            self::assertContains($this->jsonRequest('GET', $uri, token: $admin)->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN], $uri.' with an administrator token');
        }
    }

    public function testNothingWritesAMovementFromTheFront(): void
    {
        $alice = $this->customer('alice');
        $this->credit($alice, '10');
        $token = $this->authenticateAsCustomer($alice);
        $body = ['amount' => '100', 'reason' => 'gift'];

        foreach ([self::FRONT_BALANCE, self::FRONT_MOVEMENTS] as $uri) {
            foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
                self::assertContains(
                    $this->jsonRequest($method, $uri, $body, $token, 'PATCH' === $method ? 'merge-patch+json' : 'jsonld')->getStatusCode(),
                    [Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED],
                    $method.' '.$uri,
                );
            }
        }

        $adjustment = '/api/admin/credit_account/customers/'.$alice->getId().'/adjustments';
        self::assertContains($this->jsonRequest('POST', $adjustment, $body, $token)->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
        self::assertSame(1, CreditMovementQuery::create()->filterByCustomerId($alice->getId())->count());
        self::assertSame('10.00', $this->balanceOf($alice));
    }

    public function testAnAdministratorWithTheCustomerRightReadsTheBalanceAndTheJournalOfACustomer(): void
    {
        $alice = $this->customer('alice');
        $this->credit($alice, '7.25', 'welcome');
        $reader = $this->createFixtureFactory()->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW]]);
        $token = $this->authenticateAsAdmin($reader);

        $balance = self::decodeJson($this->jsonRequest('GET', $this->admin($alice, 'balance'), token: $token));
        $movements = self::decodeJson($this->jsonRequest('GET', $this->admin($alice, 'movements'), token: $token));

        self::assertSame('7.25', $balance['balance']);
        self::assertSame($alice->getId(), $balance['customerId']);
        self::assertSame('welcome', $movements['hydra:member'][0]['reason']);
        self::assertSame('system', $movements['hydra:member'][0]['authorType']);
        self::assertSame(Response::HTTP_NOT_FOUND, $this->jsonRequest('GET', '/api/admin/credit_account/customers/999999999/balance', token: $token)->getStatusCode());
    }

    public function testTheAdminReadsAreRefusedWithoutTheCustomerRightWithoutATokenAndToACustomer(): void
    {
        $alice = $this->customer('alice');
        $this->credit($alice, '7.25');
        $withoutTheRight = $this->authenticateAsAdmin($this->createFixtureFactory()->restrictedAdmin([AdminResources::CATEGORY => [AccessManager::VIEW]]));
        $customerToken = $this->authenticateAsCustomer($alice);

        foreach (['balance', 'movements'] as $what) {
            $uri = $this->admin($alice, $what);

            self::assertSame(Response::HTTP_UNAUTHORIZED, $this->jsonRequest('GET', $uri)->getStatusCode(), $what.' without a token');
            self::assertSame(Response::HTTP_FORBIDDEN, $this->jsonRequest('GET', $uri, token: $withoutTheRight)->getStatusCode(), $what.' without the customer right');
            self::assertContains($this->jsonRequest('GET', $uri, token: $customerToken)->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN], $what.' with a customer token, his own account included');
        }
    }

    public function testTheDedicatedRightWritesAManualCreditThroughTheLedgerAndLogsIt(): void
    {
        $alice = $this->customer('alice');
        $writer = $this->createFixtureFactory()->restrictedAdmin([CreditAccount::MANUAL_CREDIT_RESOURCE => [AccessManager::CREATE]], ['firstname' => 'Grace', 'lastname' => 'Hopper']);

        $response = $this->jsonRequest('POST', $this->admin($alice, 'adjustments'), ['amount' => '12.50', 'reason' => 'Goodwill'], $this->authenticateAsAdmin($writer));
        $body = self::decodeJson($response);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('12.50', $body['amount']);
        self::assertSame('12.50', $body['balance']);
        self::assertSame('12.50', $this->balanceOf($alice));

        $movement = CreditMovementQuery::create()->filterByCustomerId($alice->getId())->findOne();
        self::assertSame('adjustment', $movement->getKind());
        self::assertSame('Goodwill', $movement->getReason());
        self::assertSame(Author::ADMIN, $movement->getAuthorType());
        self::assertSame($writer->getId(), $movement->getAdminId());
        self::assertSame('Grace Hopper', $movement->getAuthorName());

        $logged = AdminLogQuery::create()->filterByResource(CreditLedger::ADMIN_LOG_RESOURCE)->filterByResourceId($alice->getId())->count();
        self::assertSame(1, $logged);
    }

    public function testTheCustomerRightsDoNotIncludeTheManualCredit(): void
    {
        $alice = $this->customer('alice');
        $reader = $this->createFixtureFactory()->restrictedAdmin([AdminResources::CUSTOMER => [AccessManager::VIEW, AccessManager::CREATE, AccessManager::UPDATE, AccessManager::DELETE]]);

        $response = $this->jsonRequest('POST', $this->admin($alice, 'adjustments'), ['amount' => '12.50', 'reason' => 'Goodwill'], $this->authenticateAsAdmin($reader));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame(0, CreditMovementQuery::create()->filterByCustomerId($alice->getId())->count());
    }

    public function testTheManualCreditIsRefusedWithoutATokenAndToACustomer(): void
    {
        $alice = $this->customer('alice');
        $uri = $this->admin($alice, 'adjustments');
        $body = ['amount' => '12.50', 'reason' => 'Goodwill'];

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->jsonRequest('POST', $uri, $body)->getStatusCode());
        self::assertContains($this->jsonRequest('POST', $uri, $body, $this->authenticateAsCustomer($alice))->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
        self::assertSame(0, CreditMovementQuery::create()->filterByCustomerId($alice->getId())->count());
    }

    public function testADebitNeverMakesTheBalanceNegative(): void
    {
        $alice = $this->customer('alice');
        $this->credit($alice, '5');
        $token = $this->authenticateAsAdmin($this->writer());
        $uri = $this->admin($alice, 'adjustments');

        $refused = $this->jsonRequest('POST', $uri, ['amount' => '-5.01', 'reason' => 'Too much'], $token);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $refused->getStatusCode());
        self::assertStringContainsString('balance is 5.00', (string) $refused->getContent());
        self::assertSame('5.00', $this->balanceOf($alice));
        self::assertSame(1, CreditMovementQuery::create()->filterByCustomerId($alice->getId())->count());

        $accepted = $this->jsonRequest('POST', $uri, ['amount' => '-5', 'reason' => 'All of it'], $token);
        self::assertSame(Response::HTTP_CREATED, $accepted->getStatusCode());
        self::assertSame('0.00', $this->balanceOf($alice));
    }

    public function testAManualMovementNeedsAReasonAndAnAmountOfTwoDecimalsAtMost(): void
    {
        $alice = $this->customer('alice');
        $token = $this->authenticateAsAdmin($this->writer());
        $uri = $this->admin($alice, 'adjustments');

        $invalid = [
            'no reason' => ['amount' => '5'],
            'blank reason' => ['amount' => '5', 'reason' => '   '],
            'no amount' => ['reason' => 'why'],
            'zero' => ['amount' => '0.00', 'reason' => 'why'],
            'three decimals' => ['amount' => '1.005', 'reason' => 'why'],
            'not a number' => ['amount' => 'a lot', 'reason' => 'why'],
        ];

        foreach ($invalid as $label => $payload) {
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->jsonRequest('POST', $uri, $payload, $token)->getStatusCode(), $label);
        }

        self::assertContains($this->jsonRequest('POST', $uri, ['amount' => 12.5, 'reason' => 'why'], $token)->getStatusCode(), [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY], 'a float');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->jsonRequest('POST', '/api/admin/credit_account/customers/999999999/adjustments', ['amount' => '5', 'reason' => 'why'], $token)->getStatusCode());
        self::assertSame(0, CreditMovementQuery::create()->filterByCustomerId($alice->getId())->count());
    }

    private function writer(): Admin
    {
        return $this->createFixtureFactory()->restrictedAdmin([CreditAccount::MANUAL_CREDIT_RESOURCE => [AccessManager::CREATE]]);
    }

    private function admin(Customer $customer, string $what): string
    {
        return \sprintf('/api/admin/credit_account/customers/%d/%s', $customer->getId(), $what);
    }

    private function balanceOf(Customer $customer): string
    {
        return (new CreditBalance())->of($customer->getId())->toDecimal();
    }

    private function credit(Customer $customer, string $amount, string $reason = 'Welcome'): void
    {
        $this->ledger->record($customer->getId(), CreditAmount::ofDecimal($amount), MovementKind::Adjustment, Author::system(), $reason);
    }

    private function customer(string $name): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle(), ['email' => $name.'-'.uniqid().'@example.com', 'password' => 'password']);
    }
}
