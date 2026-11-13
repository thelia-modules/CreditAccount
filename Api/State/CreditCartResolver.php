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

namespace CreditAccount\Api\State;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Thelia\Model\Cart;
use Thelia\Model\CartQuery;

/**
 * The cart a front route names, when it is the cart of the customer of the token: the query holds both ids, so
 * the cart of another customer is not found, whatever the route says.
 */
final readonly class CreditCartResolver
{
    public function __construct(private CreditCustomerResolver $customers)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    public function cartOfTheCustomer(array $uriVariables): Cart
    {
        $customerId = $this->customers->customerId([]);
        $cartId = filter_var($uriVariables['cartId'] ?? null, \FILTER_VALIDATE_INT);

        $cart = false === $cartId ? null : CartQuery::create()->filterById($cartId)->filterByCustomerId($customerId)->findOne();

        return $cart ?? throw new NotFoundHttpException('No such cart.');
    }
}
