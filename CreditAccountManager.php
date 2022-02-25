<?php
/**
 * Created by PhpStorm.
 * User: audreymartel
 * Date: 22/06/2018
 * Time: 14:34
 */

namespace CreditAccount;

use CreditAccount\Event\CreditAccountEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\CouponManager;
use Thelia\Coupon\Type\CouponAbstract;
use Thelia\Model\Exception\InvalidArgumentException;
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

    /** @var CouponManager $couponManager */
    private $couponManager;

    /** @var TaxEngine $taxEngine */
    private $taxEngine;

    /** @var EventDispatcherInterface $eventDispatcher */
    private $eventDispatcher;

    /** @var RequestStack $requestStack */
    private $requestStack;

    public function __construct(CouponManager $couponManager, TaxEngine $taxEngine,EventDispatcherInterface $eventDispatcher, RequestStack $requestStack)
    {
        $this->couponManager = $couponManager;
        $this->taxEngine = $taxEngine;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
    }

    /**
     * @param SessionInterface $session
     * @param EventDispatcherInterface $dispatcher
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function removeCreditDiscountFromCartAndOrder(SessionInterface $session, EventDispatcherInterface $dispatcher)
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
        $this->setDiscount($session, 0, $dispatcher);
    }

    /**
     * @param $creditDiscountWanted int
     * @param Session $session
     * @param EventDispatcher $dispatcher
     * @param bool $force
     * @throws \Propel\Runtime\Exception\PropelException
     * @throws \Exception
     */
    public function applyCreditDiscountInCartAndOrder(
        $creditDiscountWanted,
        $force = true
    )
    {
        if ($creditDiscountWanted <= 0)
        {
            return;
        }
        $couponUsedArray = $this->couponManager->getCouponsKept();

        /* @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();
        $cart = $session->getSessionCart();

        /** @noinspection MissingService */
        /** @noinspection CaseSensitivityServiceInspection */
        $taxCountry = $this->taxEngine->getDeliveryCountry();
        /** @noinspection MissingService */
        $taxState = $this->taxEngine->getDeliveryState();
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
                    throw new \Exception(
                        Translator::getInstance()->trans(
                            "The coupon %s is not cumulative. Please remove other discount(s)",
                            ['%s' => $coupon->getCode()],
                            CreditAccount::DOMAIN),
                        449);
                }
            }
            $session->setConsumedCoupons($consumedCoupons);
        }
        $couponDiscount = $this->couponManager->getDiscount();

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
        $this->setDiscount($session, $creditDiscountWanted, $this->eventDispatcher);
    }

    /**
     * @param Session $session
     * @param $creditDiscountWanted
     * @param EventDispatcherInterface $dispatcher
     */
    public function setDiscount(
        SessionInterface $session,
        $creditDiscountWanted,
        EventDispatcherInterface $dispatcher = null)
    {
        $session->set(self::SESSION_KEY_CREDIT_ACCOUNT_USED, $creditDiscountWanted);
        if ($dispatcher === null) {
            throw new InvalidArgumentException("dispatcher must be passed");
        }

        $dispatcher->dispatch((new  CreditAccountEvent($session->getCustomerUser(), $creditDiscountWanted)),CreditAccount::CREDIT_ACCOUNT_USED);
    }

    public function getDiscount(SessionInterface $session)
    {
        return $session->get(self::SESSION_KEY_CREDIT_ACCOUNT_USED, 0);
    }
}