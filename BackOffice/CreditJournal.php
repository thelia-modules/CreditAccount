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

namespace CreditAccount\BackOffice;

use CreditAccount\CreditAccount;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditMovement;
use CreditAccount\Model\CreditMovementQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\OrderQuery;

/**
 * The journal as the back-office shows it: the pages of a customer, the movements of an order.
 *
 * @phpstan-type Line array{id: int, date: ?\DateTimeInterface, kind: string, label: string, amount: string, negative: bool, reason: string, author: string, orderId: ?int, orderReference: ?string, expiresAt: ?\DateTimeInterface}
 */
final readonly class CreditJournal
{
    public const PAGE_SIZE = 20;

    public function __construct(
        private CreditBalance $balance,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{balance: string, currency: string, lines: list<Line>, page: int, pages: int, total: int}
     */
    public function ofCustomer(int $customerId, int $page): array
    {
        $query = CreditMovementQuery::create()->filterByCustomerId($customerId);
        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);

        $movements = $query
            ->orderById(Criteria::DESC)
            ->offset(($page - 1) * self::PAGE_SIZE)
            ->limit(self::PAGE_SIZE)
            ->find();

        return [
            'balance' => $this->balance->of($customerId)->toDecimal(),
            'currency' => $this->currencySymbol(),
            'lines' => $this->lines(iterator_to_array($movements, false)),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    /**
     * @return array{currency: string, lines: list<Line>}
     */
    public function ofOrder(int $orderId): array
    {
        $movements = CreditMovementQuery::create()
            ->filterByOrderId($orderId)
            ->orderById(Criteria::ASC)
            ->find();

        return [
            'currency' => $this->currencySymbol(),
            'lines' => $this->lines(iterator_to_array($movements, false)),
        ];
    }

    /**
     * @param list<CreditMovement> $movements
     *
     * @return list<Line>
     */
    private function lines(array $movements): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map(static fn (CreditMovement $movement): ?int => $movement->getOrderId(), $movements))));
        $references = [];

        if ([] !== $orderIds) {
            foreach (OrderQuery::create()->filterById($orderIds)->select(['Id', 'Ref'])->find() as $row) {
                $references[(int) $row['Id']] = (string) $row['Ref'];
            }
        }

        return array_map(function (CreditMovement $movement) use ($references): array {
            $amount = CreditAmount::ofDecimal((string) $movement->getAmount());
            $orderId = $movement->getOrderId();

            return [
                'id' => (int) $movement->getId(),
                'date' => $movement->getCreatedAt(),
                'kind' => (string) $movement->getKind(),
                'label' => $this->kindLabel((string) $movement->getKind()),
                'amount' => $amount->toDecimal(),
                'negative' => $amount->isNegative(),
                'reason' => (string) $movement->getReason(),
                'author' => $this->authorLabel($movement),
                'orderId' => $orderId,
                'orderReference' => null === $orderId ? null : ($references[$orderId] ?? null),
                'expiresAt' => $movement->getExpiresAt(),
            ];
        }, $movements);
    }

    private function kindLabel(string $kind): string
    {
        $domain = CreditAccount::BACK_OFFICE_DOMAIN;

        return match (MovementKind::tryFrom($kind)) {
            MovementKind::Earning => $this->translator->trans('Loyalty earning', [], $domain),
            MovementKind::EarningReversal => $this->translator->trans('Loyalty earning taken back', [], $domain),
            MovementKind::Spending => $this->translator->trans('Spent on an order', [], $domain),
            MovementKind::SpendingRefund => $this->translator->trans('Spending given back', [], $domain),
            MovementKind::Adjustment => $this->translator->trans('Manual movement', [], $domain),
            MovementKind::Expiration => $this->translator->trans('Expiration', [], $domain),
            MovementKind::Imported => $this->translator->trans('Imported from version 3', [], $domain),
            MovementKind::BalanceCarry => $this->translator->trans('Balance carried over', [], $domain),
            null => $kind,
        };
    }

    private function authorLabel(CreditMovement $movement): string
    {
        $domain = CreditAccount::BACK_OFFICE_DOMAIN;

        return match ($movement->getAuthorType()) {
            'admin' => '' !== (string) $movement->getAuthorName() ? (string) $movement->getAuthorName() : $this->translator->trans('Administrator', [], $domain),
            'customer' => $this->translator->trans('Customer', [], $domain),
            default => '' !== (string) $movement->getAuthorName() ? (string) $movement->getAuthorName() : $this->translator->trans('Automatic', [], $domain),
        };
    }

    private function currencySymbol(): string
    {
        return (string) CurrencyQuery::create()->findOneByByDefault(1)?->getSymbol();
    }
}
