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

namespace CreditAccount\Controller\Front;

use CreditAccount\CreditAccount;
use CreditAccount\CreditAccountManager;
use CreditAccount\Form\CreditAccountAmountForm;
use CreditAccount\Model\CreditAccountQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Model\Customer;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/creditAccount", name="creditAccount_front")
 * Class CreditAccountFrontController
 * @package CreditAccount\Controller\Front
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CreditAccountFrontController extends BaseFrontController
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     * @Route("/cancel", name="_cancel", methods="GET")
     */
    public function cancelUsage(RequestStack $requestStack, EventDispatcherInterface $dispatcher, CreditAccountManager $creditAccountManager)
    {
        $this->checkAuth();
        $creditAccountManager->removeCreditDiscountFromCartAndOrder($requestStack->getCurrentRequest()->getSession(), $dispatcher);
        return $this->generateRedirectFromRoute('order.invoice');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     * @Route("/use", name="_useAmont", methods="GET")
     */
    public function useAmount(EventDispatcherInterface $dispatcher, SecurityContext $securityContext, CreditAccountManager $creditAccountManager, RequestStack $requestStack)
    {
        $this->checkAuth();
        $this->checkCartNotEmpty($dispatcher);

        $customer = $securityContext->getCustomerUser();

        /** @noinspection PhpParamsInspection */
        $creditAccount = CreditAccountQuery::create()
            ->findOneByCustomerId($customer->getId());
        $creditDiscount = $creditAccount->getAmount();
        $creditAccountManager->applyCreditDiscountInCartAndOrder($creditDiscount);
        return $this->generateRedirectFromRoute('order.invoice');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     * @Route("/cart/add", name="_useAmontInOrder", methods="POST")
     */
    public function useAmountInCart(EventDispatcherInterface $dispatcher, SecurityContext $securityContext, CreditAccountManager $creditAccountManager, RequestStack $requestStack, ParserContext $parserContext)
    {
        $this->checkAuth();
        $this->checkCartNotEmpty($dispatcher);

        /** @var Customer $customer */
        $customer = $securityContext->getCustomerUser();

        /** @noinspection PhpParamsInspection */
        $creditAccount = CreditAccountQuery::create()
            ->findOneByCustomerId($customer->getId());

        $orderAmountForm = $this->createForm(CreditAccountAmountForm::getName());

        try {
            $form = $this->validateForm($orderAmountForm, 'post');
            $creditDiscount = $form->get('credit-account-amount')->getData();
            $force = $form->get('credit-account-force')->getData();

            if ($creditAccount === null || $creditDiscount > $creditAccount->getAmount()) {
                $amountLabel = money_format("%n", $creditAccount === null ? 0 : $creditAccount->getAmount());
                /** @noinspection PhpTranslationKeyInspection */
                throw new \Exception(
                        Translator::getInstance()->trans(
                            "Amount too high. You credit amount is : ",
                            [],
                            CreditAccount::DOMAIN
                        ) . $amountLabel
                );
            }
            $creditAccountManager->applyCreditDiscountInCartAndOrder($creditDiscount, $force);

        } catch (\Exception $e) {
            Tlog::getInstance()->error(
                sprintf("Error while setting account credit to order : %s", $e->getMessage())
            );
            $orderAmountForm->setErrorMessage($e->getMessage());

            $parserContext
                ->addForm($orderAmountForm)
                ->setGeneralError($e->getMessage());
            return $this->generateErrorRedirect($orderAmountForm);
        }

        return $this->generateSuccessRedirect($orderAmountForm);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     * @Route("/cart/remove", name="_removeAmont", methods="GET")
     */
    public function removeAmountFromCart(RequestStack $requestStack, EventDispatcherInterface $dispatcher, CreditAccountManager $creditAccountManager)
    {
        $creditAccountManager->removeCreditDiscountFromCartAndOrder($requestStack->getSession(), $dispatcher);
        return $this->generateRedirectFromRoute('cart.view');
    }
}
