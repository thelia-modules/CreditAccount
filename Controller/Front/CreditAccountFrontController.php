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

use CreditAccount\Model\CreditAccountQuery;
use Front\Front;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\CouponAbstract;
use Thelia\Log\Tlog;
use Thelia\Model\Customer;

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

        $usedAmount = $this->getSession()->get('creditAccount.used', 0);
        if (0 < $usedAmount) {
            if ($usedAmount > 0) {
                $cart = $this->getSession()->getSessionCart($this->getDispatcher());
                $order = $this->getSession()->getOrder();

                $order->setDiscount($order->getDiscount() - $usedAmount);

                $cart
                    ->setDiscount($cart->getDiscount() - $usedAmount)
                    ->save();

                $this->getSession()->set('creditAccount.used', 0);
            }
        }

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
        $this->applyCreditDiscountInOrder($creditDiscount);

        return $this->generateRedirectFromRoute('order.invoice');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function useAmountInOrder()
    {
        $this->checkAuth();
        $this->checkCartNotEmpty();

        /** @var Customer $customer */
        $customer = $this->getSecurityContext()->getCustomerUser();

        /** @noinspection PhpParamsInspection */
        $creditAccount = CreditAccountQuery::create()
            ->findOneByCustomerId($customer->getId());

        $orderAmountForm = $this->createForm("credit_account_order_amount");

        try {
            $form = $this->validateForm($orderAmountForm, 'post');
            $creditDiscount = $form->get('order-credit-account-amount')->getData();

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

            $this->applyCreditDiscountInOrder($creditDiscount);

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
     * @param $creditDiscountWanted
     * @throws \Propel\Runtime\Exception\PropelException
     * @throws \Exception
     */
    private function applyCreditDiscountInOrder($creditDiscountWanted) {
        if ($creditDiscountWanted <= 0) {
            return;
        }

        /** @var CouponManager $couponManager */
        /** @noinspection MissingService */
        $couponManager = $this->container->get('thelia.coupon.manager');

        $couponUsedArray = $couponManager->getCouponsKept();

        $cart = $this->getSession()->getSessionCart($this->getDispatcher());
        /** @noinspection MissingService */
        /** @noinspection CaseSensitivityServiceInspection */
        $taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry();
        /** @noinspection MissingService */
        $taxState = $this->container->get('thelia.taxEngine')->getDeliveryState();
        $totalCart = $cart->getTaxedAmount($taxCountry, false, $taxState);

        if (!empty($couponUsedArray)) {
            $couponManager->clear();
            $this->getSession()->setConsumedCoupons([]);
            /**
             * @var  $index int
             * @var  $coupon CouponAbstract
             */
            foreach ($couponUsedArray as $index => $coupon) {
                if ($coupon->isCumulative()) {
                    $couponManager->pushCouponInSession($coupon->getCode());
                }
            }
        }
        $couponDiscount = $couponManager->getDiscount();

        if ($creditDiscountWanted + $couponDiscount > $totalCart) {
            $creditDiscountWanted = max(0, $totalCart - $couponDiscount);
        }

        $discountCart = $creditDiscountWanted + $couponDiscount;

        $order = $this->getSession()->getOrder();
        $order->setDiscount($discountCart);

        //update cart
        $cart->setDiscount($discountCart);
        $cart->save();

        //update session
        $this->getSession()->set('creditAccount.used', $creditDiscountWanted);
    }
}
