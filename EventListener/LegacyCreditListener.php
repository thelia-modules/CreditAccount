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

namespace CreditAccount\EventListener;

use CreditAccount\CreditAccount;
use CreditAccount\Event\CreditAccountEvent;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditAccountQuery;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Routes the 3.x event of the modules that credit or debit a customer (sponsorship, gift card...) to the journal.
 * It is a gesture written by hand: an adjustment, which says why.
 */
final readonly class LegacyCreditListener
{
    public function __construct(private CreditLedger $ledger, private CreditExpiry $expiry)
    {
    }

    /**
     * @throws InsufficientCreditException a debit the balance does not cover: the balance is never made negative
     */
    #[AsEventListener(event: CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT)]
    public function onAddAmount(CreditAccountEvent $event): void
    {
        $raw = $event->getAmount();
        $amount = \is_float($raw) ? CreditAmount::ofFloat($raw) : CreditAmount::ofDecimal((string) $raw);

        if ($amount->isZero()) {
            return;
        }

        $customerId = (int) $event->getCustomer()->getId();
        $reason = trim($event->getReason());

        if ('' === $reason) {
            $reason = 'Credit moved by a module';
        }

        if (!empty($event->getOrderId())) {
            $reason .= \sprintf(' (order #%d)', (int) $event->getOrderId());
        }

        $this->ledger->record(
            $customerId,
            $amount,
            MovementKind::Adjustment,
            Author::system(trim((string) $event->getWhoDidIt())),
            $reason,
            expiresAt: $amount->isPositive() ? $this->expiry->forACreditWrittenAt(new \DateTimeImmutable()) : null,
        );

        $account = CreditAccountQuery::create()->findOneByCustomerId($customerId);

        if (null !== $account) {
            $event->setCreditAccount($account);
        }
    }
}
