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

namespace CreditAccount\Spending;

use CreditAccount\CreditAccount;
use CreditAccount\Ledger\Author;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Ledger\CreditLedger;
use CreditAccount\Ledger\MovementKind;
use CreditAccount\Model\CreditMovementQuery;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Thelia\Domain\Module\Payment\PaymentCartContext;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Model\Cart;
use Thelia\Model\Map\OrderPaymentPartTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Model\OrderPaymentPartQuery;

/**
 * The credit as a payment means settling part of an order inside the shop: what the module class answers the
 * core with (Thelia\Module\InstantPaymentModuleInterface).
 *
 * Everything is written on the connection of the core, inside the transaction it holds while it settles or
 * releases the parts of an order: nothing is begun or rolled back here, a refusal is thrown.
 */
#[Autoconfigure(public: true)]
final readonly class CreditPaymentPart
{
    private const AUTHOR = 'Payment';

    public function __construct(
        private CreditLedger $ledger,
        private CreditBalance $balance,
        private CreditCartUsage $usage,
        private CreditExpiry $expiry,
        private PaymentCartContext $paymentCartContext,
    ) {
    }

    /**
     * What the buyer chose to spend on the cart, as a decimal string: unchanged once the part is settled and the
     * order not yet paid, which is how the core knows an unpaid order may be presented again. Whether the balance
     * still covers it is the settlement's to say, not this answer's.
     */
    public function amountAppliedTo(Cart $cart): string
    {
        if (null === $cart->getCustomerId()) {
            return '0';
        }

        return $this->usage->amountOn($cart)->toDecimal();
    }

    /**
     * Only a signed-in customer holding credit spends it, on the cart the core is judging.
     */
    public function isUsableOnTheCartBeingPaid(): bool
    {
        $customerId = $this->paymentCartContext->cart()?->getCustomerId();

        return null !== $customerId && $this->balance->of((int) $customerId)->isPositive();
    }

    /**
     * Spends the amount of the part from the credit of the customer of the order.
     *
     * @throws \CreditAccount\Ledger\InsufficientCreditException the balance no longer covers the part
     */
    public function settle(OrderPaymentPart $part): void
    {
        $connection = $this->connection();
        $order = $part->getOrder($connection);
        $customerId = $order->getCustomerId();

        if (null === $customerId) {
            throw new \LogicException(\sprintf('Order %s has no customer to spend credit from.', $order->getRef()));
        }

        $this->ledger->record(
            (int) $customerId,
            CreditAmount::ofDecimal((string) $part->getAmount())->negated(),
            MovementKind::Spending,
            Author::customer(),
            \sprintf('Order %s', $order->getRef()),
            (int) $order->getId(),
            connection: $connection,
            paymentPartId: (int) $part->getId(),
        );
    }

    /**
     * Gives back exactly what the part spent, with the expiry of a credit written now. The spending is found by
     * the id of the part: a part with none (never settled, or settled by another means) gives nothing, and one
     * already given back is handed back by the ledger, never credited twice.
     */
    public function release(OrderPaymentPart $part): void
    {
        $connection = $this->connection();
        $spending = CreditMovementQuery::create()
            ->filterByPaymentPartId($part->getId())
            ->filterByKind(MovementKind::Spending->value)
            ->findOne($connection);

        if (null === $spending || null === $spending->getCustomerId()) {
            return;
        }

        $order = $part->getOrder($connection);

        $this->ledger->record(
            (int) $spending->getCustomerId(),
            CreditAmount::ofDecimal((string) $spending->getAmount())->absolute(),
            MovementKind::SpendingRefund,
            Author::system(self::AUTHOR),
            \sprintf('Order %s: spending given back', $order->getRef()),
            (int) $part->getOrderId(),
            $this->expiry->forACreditWrittenAt(new \DateTimeImmutable()),
            connection: $connection,
            paymentPartId: (int) $part->getId(),
        );
    }

    /**
     * A refund does not release the parts of the order (the core leaves them settled, and asks again at a later
     * cancellation): the module gives its own back, by the same write as release(), so that asking again credits
     * nothing a second time.
     */
    public function releaseThePartsOf(Order $order): void
    {
        $parts = OrderPaymentPartQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByPaymentModuleId(CreditAccount::getModuleId())
            ->filterByState(OrderPaymentPartState::SETTLED->value)
            ->orderById()
            ->find($this->connection());

        foreach ($parts as $part) {
            $this->release($part);
        }
    }

    private function connection(): ConnectionInterface
    {
        return Propel::getWriteConnection(OrderPaymentPartTableMap::DATABASE_NAME);
    }
}
