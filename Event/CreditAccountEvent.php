<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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