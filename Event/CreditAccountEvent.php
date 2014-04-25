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


    public function __construct(Customer $customer, $amount)
    {
        $this->customer = $customer;
        $this->amount = $amount;
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


} 