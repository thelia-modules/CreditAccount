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
declare(strict_types=1);

namespace CreditAccount\Controller\Admin;

use CreditAccount\CreditAccount;
use CreditAccount\Event\CreditAccountEvent;
use CreditAccount\Form\CreditAccountForm;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Admin;
use Thelia\Model\CustomerQuery;

#[Route('/admin/creditAccount', name: 'creditAccount')]
class CreditAccountAdminController extends BaseAdminController
{
    #[Route('/add', name: '_add', methods: ['POST'])]
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

            $session = $requestStack->getCurrentRequest()?->getSession();
            $admin = $session instanceof Session ? $session->getAdminUser() : null;
            if ($admin instanceof Admin) {
                $event->setWhoDidIt($admin->getFirstname() . " " . $admin->getLastname());
            }

            $dispatcher->dispatch($event, CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT);

        } catch (\Exception $ex) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("Add amount to credit account"),
                $ex->getMessage(),
                $form,
                $ex
            );
        }

        return $this->generateRedirect($this->safeSuccessUrl($form->getSuccessUrl(), $requestStack));
    }

    /**
     * Only allow redirecting to the current host (or an internal relative path) to
     * prevent an open redirect through the submitted success_url field.
     */
    private function safeSuccessUrl(?string $successUrl, RequestStack $requestStack): string
    {
        $successUrl = (string) $successUrl;
        $host = $requestStack->getCurrentRequest()?->getSchemeAndHttpHost();

        $isSafe = '' !== $successUrl
            && ((str_starts_with($successUrl, '/') && !str_starts_with($successUrl, '//'))
                || (null !== $host && str_starts_with($successUrl, $host.'/')));

        return $isSafe ? $successUrl : '/admin/customers';
    }
}
