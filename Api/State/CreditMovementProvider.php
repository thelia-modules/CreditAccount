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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use CreditAccount\Api\Resource\CreditAccountMovement;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Model\CreditMovement;
use CreditAccount\Model\CreditMovementQuery;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * The journal of one customer, newest first. The customer is the scope of the query, not a filter: it comes from
 * the token (front) or the route (admin), never from the query string. The primary key orders the lines, as two
 * movements written in the same second share their date.
 *
 * @implements ProviderInterface<CreditAccountMovement>
 */
final readonly class CreditMovementProvider implements ProviderInterface
{
    public function __construct(
        private CreditCustomerResolver $customers,
        private Pagination $pagination,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return TraversablePaginator<CreditAccountMovement>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $query = CreditMovementQuery::create()
            ->filterByCustomerId($this->customers->customerId($uriVariables))
            ->orderById(Criteria::DESC);

        [$page, $offset, $itemsPerPage] = $this->pagination->getPagination($operation, $context);

        $total = (clone $query)->count();
        $movements = $query->offset($offset)->limit($itemsPerPage)->find();

        return new TraversablePaginator(
            new \ArrayIterator(array_map($this->toResource(...), iterator_to_array($movements, false))),
            $page,
            $itemsPerPage,
            $total,
        );
    }

    private function toResource(CreditMovement $movement): CreditAccountMovement
    {
        $resource = new CreditAccountMovement();
        $resource->id = (int) $movement->getId();
        $resource->customerId = (int) $movement->getCustomerId();
        $resource->kind = (string) $movement->getKind();
        $resource->amount = CreditAmount::ofDecimal((string) $movement->getAmount())->toDecimal();
        $resource->reason = (string) $movement->getReason();
        $resource->orderId = $movement->getOrderId();
        $resource->expiresAt = null === $movement->getExpiresAt() ? null : \DateTimeImmutable::createFromInterface($movement->getExpiresAt());
        $resource->createdAt = null === $movement->getCreatedAt() ? null : \DateTimeImmutable::createFromInterface($movement->getCreatedAt());
        $resource->authorType = (string) $movement->getAuthorType();
        $resource->adminId = $movement->getAdminId();
        $resource->authorName = (string) $movement->getAuthorName();

        return $resource;
    }
}
