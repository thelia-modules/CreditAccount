<?php
/**
 * Created by PhpStorm.
 * User: audreymartel
 * Date: 22/06/2018
 * Time: 14:34
 */

namespace CreditAccount;

use Front\Front;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\CouponAbstract;
use Thelia\TaxEngine\TaxEngine;

/**
 * Manage how Coupons could interact with Cart and Order
 * Class CreditAccountManager
 * @package CreditAccount
 */
class CreditAccountManager
{
    /**
     * @param Session $session
     * @param EventDispatcherInterface $dispatcher
     * @throws \Propel\Runtime\Exception\PropelException
     */
    const SESSION_KEY_CREDIT_ACCOUNT_USED = 'creditAccount.used';

    /**
     * @param Session $session
     * @param EventDispatcherInterface $dispatcher
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function removeCreditDiscountFromCartAndOrder(Session $session, EventDispatcherInterface $dispatcher)
    {
        $usedAmount = $this->getDiscount($session);
        if ($usedAmount <= 0) {
            return;
        }
        $cart = $session->getSessionCart($dispatcher);
        $order = $session->getOrder();
        $order->setDiscount($order->getDiscount() - $usedAmount);
        $cart->setDiscount($cart->getDiscount() - $usedAmount);
        $cart->save();
        $this->setDiscount($session, 0);
    }

    /**
     * @param $creditDiscountWanted int
     * @throws \Propel\Runtime\Exception\PropelException
     * @throws \Exception
     */
    public function applyCreditDiscountInCartAndOrder(
        $creditDiscountWanted,
        CouponManager $couponManager,
        TaxEngine $taxEngine,
        Session $session,
        EventDispatcher $dispatcher,
        $force = true
    )
    {
        if ($creditDiscountWanted <= 0)
        {
            return;
        }
        $couponUsedArray = $couponManager->getCouponsKept();

        $cart = $session->getSessionCart($dispatcher);
        /** @noinspection MissingService */
        /** @noinspection CaseSensitivityServiceInspection */
        $taxCountry = $taxEngine->getDeliveryCountry();
        /** @noinspection MissingService */
        $taxState = $taxEngine->getDeliveryState();
        $totalCart = $cart->getTaxedAmount($taxCountry, false, $taxState);

        if (!empty($couponUsedArray)) {
            $consumedCoupons = $session->getConsumedCoupons();
            /**
             * @var  $index int
             * @var  $coupon CouponAbstract
             */
            foreach ($couponUsedArray as $index => $coupon) {
                if ($coupon->isCumulative()) {
                    continue;
                }
                if ($force) {
                    unset($consumedCoupons[$coupon->getCode()]);
                } else {
                    /** @noinspection PhpTranslationKeyInspection */
                    throw new \Exception( Translator::getInstance()->trans("The coupon %s is not cumulative. Please remove other discount(s)", ['%s' => $coupon->getCode()], Front::MESSAGE_DOMAIN), 449);
                }
            }
            $session->setConsumedCoupons($consumedCoupons);
        }
        $couponDiscount = $couponManager->getDiscount();

        if ($creditDiscountWanted + $couponDiscount > $totalCart) {
            $creditDiscountWanted = max(0, $totalCart - $couponDiscount);
        }

        $discountCart = $creditDiscountWanted + $couponDiscount;

        $order = $session->getOrder();
        $order->setDiscount($discountCart);

        //update cart
        $cart->setDiscount($discountCart);
        $cart->save();

        //update session
        $this->setDiscount($session, $creditDiscountWanted);
    }

    public function setDiscount(Session $session, $creditDiscountWanted)
    {
        $session->set(self::SESSION_KEY_CREDIT_ACCOUNT_USED, $creditDiscountWanted);
    }

    public function getDiscount(Session $session)
    {
        return $session->get(self::SESSION_KEY_CREDIT_ACCOUNT_USED, 0);
    }
}