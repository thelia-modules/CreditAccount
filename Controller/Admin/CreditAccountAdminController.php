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
namespace CreditAccount\Controller\Admin;

use CreditAccount\CreditAccount;
use CreditAccount\Event\CreditAccountEvent;
use CreditAccount\Form\CreditAccountForm;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Admin;
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

            /** @var  Admin $admin */
            $admin = $this->getSession()->getAdminUser();
            $event->setWhoDidIt($admin->getFirstname() . " " . $admin->getLastname());

            $this->dispatch(CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT, $event);

        } catch (\Exception $ex) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("Add amount to credit account"),
                $ex->getMessage(),
                $form,
                $ex
            );
        }

        return $this->generateRedirect($form->getSuccessUrl());
    }
}
