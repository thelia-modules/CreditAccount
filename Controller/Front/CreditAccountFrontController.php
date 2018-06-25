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

use CreditAccount\CreditAccountManager;
use CreditAccount\Model\CreditAccountQuery;
use Front\Front;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\CouponManager;
use Thelia\Log\Tlog;
use Thelia\Model\Customer;
use Thelia\TaxEngine\TaxEngine;

/**
 * Class CreditAccountFrontController
 * @package CreditAccount\Controller\Front
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CreditAccountFrontController extends BaseFrontController
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function cancelUsage()
    {
        $this->checkAuth();
        /** @var CreditAccountManager $creditAccountManager */
        /** @noinspection MissingService */
        $creditAccountManager = $this->container->get('creditaccount.manager');
        $creditAccountManager->removeCreditDiscountFromCartAndOrder($this->getSession(), $this->getDispatcher());
        return $this->generateRedirectFromRoute('order.invoice');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function useAmount()
    {
        $this->checkAuth();
        $this->checkCartNotEmpty();

        $customer = $this->getSecurityContext()->getCustomerUser();

        /** @noinspection PhpParamsInspection */
        $creditAccount = CreditAccountQuery::create()
            ->findOneByCustomerId($customer->getId());
        $creditDiscount = $creditAccount->getAmount();
        /** @var CouponManager $couponManager */
        $couponManager = $this->container->get('thelia.coupon.manager');
        /** @var CreditAccountManager $creditAccountManager */
        $creditAccountManager = $this->container->get('creditaccount.manager');
        /** @var TaxEngine $taxEngine */
        $taxEngine = $this->container->get('thelia.taxEngine');
        $creditAccountManager->applyCreditDiscountInCartAndOrder($creditDiscount, $couponManager, $taxEngine, $this->getSession(), $this->getDispatcher());
        return $this->generateRedirectFromRoute('order.invoice');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function useAmountInCart()
    {
        $this->checkAuth();
        $this->checkCartNotEmpty();

        /** @var Customer $customer */
        $customer = $this->getSecurityContext()->getCustomerUser();

        /** @noinspection PhpParamsInspection */
        $creditAccount = CreditAccountQuery::create()
            ->findOneByCustomerId($customer->getId());

        $orderAmountForm = $this->createForm("credit_account_amount_form");

        try {
            $form = $this->validateForm($orderAmountForm, 'post');
            $creditDiscount = $form->get('credit-account-amount')->getData();
            $force = $form->get('credit-account-force')->getData();

            if ($creditDiscount > $creditAccount->getAmount()) {
                $amountLabel = money_format("%n", $creditAccount->getAmount());
                /** @noinspection PhpTranslationKeyInspection */
                throw new \Exception(
                        Translator::getInstance()->trans(
                            "Amount too high. You credit amount is : ",
                            [],
                            Front::MESSAGE_DOMAIN
                        ) . $amountLabel
                );
            }

            /** @var CreditAccountManager $creditAccountManager */
            $creditAccountManager = $this->container->get('creditaccount.manager');
            $creditAccountManager->applyCreditDiscountInCartAndOrder($creditDiscount, $this->getSession(), $this->getDispatcher(), $force);

        } catch (\Exception $e) {
            Tlog::getInstance()->error(
                sprintf("Error while setting account credit to order : %s", $e->getMessage())
            );
            $orderAmountForm->setErrorMessage($e->getMessage());

            $this->getParserContext()
                ->addForm($orderAmountForm)
                ->setGeneralError($e->getMessage());
            return $this->generateErrorRedirect($orderAmountForm);
        }

        return $this->generateSuccessRedirect($orderAmountForm);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function removeAmountFromCart()
    {
        /** @var CreditAccountManager $creditAccountManager */
        $creditAccountManager = $this->container->get('creditaccount.manager');
        $creditAccountManager->removeCreditDiscountFromCartAndOrder($this->getSession(), $this->getDispatcher());
        return $this->generateRedirectFromRoute('cart.view');
    }
}
