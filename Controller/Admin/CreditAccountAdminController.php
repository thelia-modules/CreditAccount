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

namespace CreditAccount\Controller\Admin;

use CreditAccount\CreditAccount;
use CreditAccount\Event\CreditAccountEvent;
use CreditAccount\Form\CreditAccountForm;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\CustomerQuery;


/**
 * Class CreditAccountAdminController
 * @package CreditAccount\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CreditAccountAdminController extends BaseAdminController
{
    public function addAmount()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::CUSTOMER), array('CreditAccount'), AccessManager::UPDATE)) {
            return $response;
        }

        $form = new CreditAccountForm($this->getRequest());

        try {
            $creditForm = $this->validateForm($form);

            $customer = CustomerQuery::create()->findPk($creditForm->get('customer_id')->getData());

            $event = new CreditAccountEvent($customer, $creditForm->get('amount')->getData());

            $this->dispatch(CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT, $event);

            $this->redirectSuccess($form);
        } catch(\Exception $e) {

        }
    }
} 