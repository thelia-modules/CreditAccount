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

namespace CreditAccount\Event;

use CreditAccount\Model\CreditAccount;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Customer;

/**
 * Class CreditAccountEvent.
 *
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class CreditAccountEvent extends ActionEvent
{
    /**
     * @var int|float|string
     */
    protected $amount;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var CreditAccount
     */
    protected $creditAccount;

    /**
     * @var int an order ID, if the change is related to an order, either getting credit, or using some or all the available credit.
     *          Could be 0 or null if the change was manually triggered by an administrator.
     */
    protected $orderId;

    /**
     * @var string the name of the back-office user who performed the change. Could be empty if the change is related to an order.
     */
    protected $whoDidIt;

    // Why the amount moves, written in the journal; a default text is written when empty.
    protected string $reason = '';

    /**
     * @param int|float|string $amount
     * @param int|null         $orderId
     */
    public function __construct(Customer $customer, $amount, $orderId = null)
    {
        $this->customer = $customer;
        $this->amount = $amount;
        $this->orderId = $orderId;
    }

    public function getAmount(): mixed
    {
        return $this->amount;
    }

    /**
     * @return Customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    public function setCreditAccount(CreditAccount $creditAccount): void
    {
        $this->creditAccount = $creditAccount;
    }

    public function getCreditAccount(): mixed
    {
        return $this->creditAccount;
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->orderId;
    }

    /**
     * @param int $orderId
     *
     * @return $this
     */
    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * @return string
     */
    public function getWhoDidIt()
    {
        return $this->whoDidIt;
    }

    /**
     * @param string $whoDidIt
     *
     * @return $this
     */
    public function setWhoDidIt($whoDidIt)
    {
        $this->whoDidIt = $whoDidIt;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }
}
