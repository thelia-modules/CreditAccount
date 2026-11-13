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

namespace CreditAccount\Front;

use CreditAccount\BackOffice\CreditJournal;
use CreditAccount\CreditAccount;
use CreditAccount\Ledger\MovementKind;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\Currency;

/**
 * What the customer sees of their credit account: the balance and one page of the journal, with the kind of each
 * movement in words the customer reads. The page comes from the back-office journal, so both screens page alike.
 *
 * @phpstan-type Line array{date: ?\DateTimeInterface, label: string, reason: string, amount: string, negative: bool}
 */
final readonly class CreditHistory
{
    public function __construct(
        private CreditJournal $journal,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{balance: string, currencyId: ?int, lines: list<Line>, page: int, pages: int, total: int}
     */
    public function of(int $customerId, int $page): array
    {
        $journal = $this->journal->ofCustomer($customerId, $page);

        return [
            'balance' => $journal['balance'],
            // The credit is kept in the default currency, whatever currency the visitor browses in.
            'currencyId' => Currency::getDefaultCurrency()->getId(),
            'lines' => array_map(fn (array $line): array => [
                'date' => $line['date'],
                'label' => $this->kindLabel($line['kind']),
                'reason' => $line['reason'],
                'amount' => $line['amount'],
                'negative' => $line['negative'],
            ], $journal['lines']),
            'page' => $journal['page'],
            'pages' => $journal['pages'],
            'total' => $journal['total'],
        ];
    }

    private function kindLabel(string $kind): string
    {
        $domain = CreditAccount::DOMAIN;

        return match (MovementKind::tryFrom($kind)) {
            MovementKind::Earning => $this->translator->trans('Loyalty credit earned', [], $domain),
            MovementKind::EarningReversal => $this->translator->trans('Loyalty credit taken back', [], $domain),
            MovementKind::Spending => $this->translator->trans('Credit used on an order', [], $domain),
            MovementKind::SpendingRefund => $this->translator->trans('Credit given back', [], $domain),
            MovementKind::Adjustment => $this->translator->trans('Adjustment by the shop', [], $domain),
            MovementKind::Expiration => $this->translator->trans('Credit expired', [], $domain),
            MovementKind::Imported, MovementKind::BalanceCarry => $this->translator->trans('Previous balance', [], $domain),
            null => $kind,
        };
    }
}
