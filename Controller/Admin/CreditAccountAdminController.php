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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Admin;
use Thelia\Model\CustomerQuery;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/creditAccount", name="creditAccount")
 * Class CreditAccountAdminController
 * @package CreditAccount\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CreditAccountAdminController extends BaseAdminController
{
    /**
     * @Route("/add", name="_add", methods="POST")
     */
    public function addAmount(RequestStack $requestStack, EventDispatcherInterface $dispatcher)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::CUSTOMER), array('CreditAccount'), AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(CreditAccountForm::getName());

        try {
            $creditForm = $this->validateForm($form);

            $customer = CustomerQuery::create()->findPk($creditForm->get('customer_id')->getData());

            $event = new CreditAccountEvent($customer, $creditForm->get('amount')->getData());

            /** @var  Admin $admin */
            $admin = $requestStack->getCurrentRequest()->getSession()->getAdminUser();
            $event->setWhoDidIt($admin->getFirstname() . " " . $admin->getLastname());

            $dispatcher->dispatch($event, CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT);

        } catch (\Exception $ex) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("Add amount to credit account"),
                $ex->getMessage(),
                $form,
                $ex
            );
        }

        return $this->generateRedirect($form->getSuccessUrl());
    }
}
