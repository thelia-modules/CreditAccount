<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace CreditAccount\Event;

use CreditAccount\Model\CreditAccount;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Customer;


/**
 * Class CreditAccountEvent
 * @package CreditAccount\Event
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class CreditAccountEvent extends ActionEvent
{
    /**
     * @var
     */
    protected $amount;

    /**
     * @var \Thelia\Model\Customer
     */
    protected $customer;

    /**
     * @var CreditAccount $creditAccount
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



    public function __construct(Customer $customer, $amount, $orderId = null)
    {
        $this->customer = $customer;
        $this->amount = $amount;
        $this->orderId = $orderId;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }


    /**
     * @return \Thelia\Model\Customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }


    /**
     * @param CreditAccount $creditAccount
     */
    public function setCreditAccount(CreditAccount $creditAccount)
    {
        $this->creditAccount = $creditAccount;
    }

    /**
     * @return mixed
     */
    public function getCreditAccount()
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
     * @return $this
     */
    public function setWhoDidIt($whoDidIt)
    {
        $this->whoDidIt = $whoDidIt;
        return $this;
    }

}
