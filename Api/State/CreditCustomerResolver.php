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

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * Whose credit a request is about. On the front it is the customer of the token and nothing the request says; on
 * the administration side it is the customer the route names, which must exist.
 */
final readonly class CreditCustomerResolver
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    public function customerId(array $uriVariables): int
    {
        if (!\array_key_exists('customerId', $uriVariables)) {
            $customer = $this->tokenStorage->getToken()?->getUser();

            if (!$customer instanceof Customer) {
                throw new AccessDeniedHttpException('A customer must be authenticated.');
            }

            return (int) $customer->getId();
        }

        $customerId = filter_var($uriVariables['customerId'], \FILTER_VALIDATE_INT);

        if (false === $customerId || $customerId < 1 || !CustomerQuery::create()->filterById($customerId)->exists()) {
            throw new NotFoundHttpException('No such customer.');
        }

        return $customerId;
    }
}
