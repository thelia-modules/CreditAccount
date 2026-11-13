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

use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditCartUsageQuery;
use CreditAccount\Model\CreditMovementQuery;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Test\ApiTestCase;

/**
 * Choosing how much credit to spend on a cart, from the front API: the customer of the token and the carts of that
 * customer only, an amount the balance does not cover is refused, and nothing is written in the journal.
 */
final class CreditCartSpendingApiTest extends ApiTestCase
{
    private CreditLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = new CreditLedger(new CreditBalance());
    }

    public function testACustomerChoosesReadsAndWithdrawsTheAmountToSpendOnHisCart(): void
    {
        $alice = $this->customer('alice');
        $this->credit($alice, '20');
        $cart = $this->factory()->cart($alice);
        $token = $this->authenticateAsCustomer($alice);

        $put = $this->jsonRequest('PUT', $this->uri($cart), ['amount' => '12.5'], $token);
        self::assertSame(Response::HTTP_OK, $put->getStatusCode(), (string) $put->getContent());
        self::assertSame(['12.50', '20.00'], [self::decodeJson($put)['amount'], self::decodeJson($put)['balance']]);
        self::assertSame('12.50', CreditAmount::ofDecimal((string) CreditCartUsageQuery::create()->findPk($cart->getId())?->getAmount())->toDecimal(), 'kept with the cart');

        $get = self::decodeJson($this->jsonRequest('GET', $this->uri($cart), token: $token));
        self::assertSame('12.50', $get['amount']);

        $replaced = $this->jsonRequest('PUT', $this->uri($cart), ['amount' => '20'], $token);
        self::assertSame('20.00', self::decodeJson($replaced)['amount'], 'the balance itself may be spent');

        self::assertSame(Response::HTTP_NO_CONTENT, $this->jsonRequest('DELETE', $this->uri($cart), token: $token)->getStatusCode());
        self::assertSame('0.00', self::decodeJson($this->jsonRequest('GET', $this->uri($cart), token: $token))['amount']);
        self::assertSame(0, CreditCartUsageQuery::create()->filterByCartId($cart->getId())->count());

        self::assertSame(1, CreditMovementQuery::create()->filterByCustomerId($alice->getId())->count(), 'choosing writes no movement');
        self::assertSame('20.00', (new CreditBalance())->of((int) $alice->getId())->toDecimal());
    }

    public function testAnAmountAboveTheBalanceIsRefusedNeverCutDownAndKeepsTheChoiceAsItWas(): void
    {
        $alice = $this->customer('alice');
        $this->credit($alice, '10');
        $cart = $this->factory()->cart($alice);
        $token = $this->authenticateAsCustomer($alice);
        $this->jsonRequest('PUT', $this->uri($cart), ['amount' => '4'], $token);

        $refused = $this->jsonRequest('PUT', $this->uri($cart), ['amount' => '10.01'], $token);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $refused->getStatusCode());
        self::assertStringContainsString('balance is 10.00', (string) $refused->getContent());
        self::assertSame('4.00', self::decodeJson($this->jsonRequest('GET', $this->uri($cart), token: $token))['amount'], 'not cut down to the balance, not replaced');
    }

    public function testAnAmountThatIsNotAPositiveDecimalOfTwoDecimalsAtMostIsRefused(): void
    {
        $alice = $this->customer('alice');
        $this->credit($alice, '10');
        $cart = $this->factory()->cart($alice);
        $token = $this->authenticateAsCustomer($alice);

        foreach (['zero' => ['amount' => '0.00'], 'negative' => ['amount' => '-1'], 'three decimals' => ['amount' => '1.005'], 'text' => ['amount' => 'all'], 'none' => ['amount' => null]] as $label => $payload) {
            self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->jsonRequest('PUT', $this->uri($cart), $payload, $token)->getStatusCode(), $label);
        }

        self::assertContains($this->jsonRequest('PUT', $this->uri($cart), ['amount' => 5.5], $token)->getStatusCode(), [Response::HTTP_BAD_REQUEST, Response::HTTP_UNPROCESSABLE_ENTITY], 'a float');
        self::assertSame(0, CreditCartUsageQuery::create()->filterByCartId($cart->getId())->count());
    }

    public function testTheCartOfAnotherCustomerIsNeverReadNorTouched(): void
    {
        [$alice, $bob] = [$this->customer('alice'), $this->customer('bob')];
        $this->credit($alice, '10');
        $this->credit($bob, '10');
        $bobsCart = $this->factory()->cart($bob);
        $bobToken = $this->authenticateAsCustomer($bob);
        $this->jsonRequest('PUT', $this->uri($bobsCart), ['amount' => '3'], $bobToken);
        $aliceToken = $this->authenticateAsCustomer($alice);

        foreach (['GET', 'PUT', 'DELETE'] as $method) {
            $response = $this->jsonRequest($method, $this->uri($bobsCart), 'PUT' === $method ? ['amount' => '5'] : [], $aliceToken);

            self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), $method);
        }

        self::assertSame(Response::HTTP_NOT_FOUND, $this->jsonRequest('PUT', '/api/front/account/credit/carts/999999999/spending', ['amount' => '1'], $aliceToken)->getStatusCode(), 'a cart that does not exist');
        self::assertSame('3.00', CreditAmount::ofDecimal((string) CreditCartUsageQuery::create()->findPk($bobsCart->getId())?->getAmount())->toDecimal(), 'bob keeps his choice');
        self::assertSame([$bobsCart->getId()], CreditCartUsageQuery::create()->select(['CartId'])->find()->getData(), 'and nothing was written for alice');
    }

    public function testNothingIsAnsweredWithoutACustomerToken(): void
    {
        $alice = $this->customer('alice');
        $this->credit($alice, '10');
        $cart = $this->factory()->cart($alice);
        $admin = $this->authenticateAsAdmin();

        foreach (['GET', 'PUT', 'DELETE'] as $method) {
            $body = 'PUT' === $method ? ['amount' => '5'] : [];

            self::assertSame(Response::HTTP_UNAUTHORIZED, $this->jsonRequest($method, $this->uri($cart), $body)->getStatusCode(), $method.' without a token');
            self::assertContains($this->jsonRequest($method, $this->uri($cart), $body, $admin)->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN], $method.' with an administrator token');
        }

        self::assertSame(0, CreditCartUsageQuery::create()->filterByCartId($cart->getId())->count());
    }

    private function uri(Cart $cart): string
    {
        return \sprintf('/api/front/account/credit/carts/%d/spending', $cart->getId());
    }

    private function factory(): \Thelia\Test\FixtureFactory
    {
        return $this->createFixtureFactory();
    }

    private function credit(Customer $customer, string $amount): void
    {
        $this->ledger->record((int) $customer->getId(), CreditAmount::ofDecimal($amount), MovementKind::Adjustment, Author::system(), 'Welcome');
    }

    private function customer(string $name): Customer
    {
        $factory = $this->createFixtureFactory();

        return $factory->customer($factory->customerTitle(), ['email' => $name.'-'.uniqid().'@example.com', 'password' => 'password']);
    }
}
