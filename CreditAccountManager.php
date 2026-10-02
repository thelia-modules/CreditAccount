<?php

declare(strict_types=1);

namespace CreditAccount;

use CreditAccount\Event\CreditAccountEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Promotion\Coupon\Type\CouponAbstract;
use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Exception\InvalidArgumentException;

/**
 * Manage how the customer credit interacts with the cart and the order.
 */
class CreditAccountManager
{
    const SESSION_KEY_CREDIT_ACCOUNT_USED = 'creditAccount.used';

    public function __construct(
        private readonly CouponManager $couponManager,
        private readonly TaxEngine $taxEngine,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function removeCreditDiscountFromCartAndOrder(SessionInterface $session, EventDispatcherInterface $dispatcher): void
    {
        if (!$session instanceof Session) {
            return;
        }

        $usedAmount = (float) $this->getDiscount($session);
        if ($usedAmount <= 0) {
            return;
        }

        $cart = $session->getSessionCart($dispatcher);
        $order = $session->getOrder();
        $order->setDiscount((string) ((float) $order->getDiscount() - $usedAmount));
        $cart->setDiscount((string) ((float) $cart->getDiscount() - $usedAmount));
        $cart->save();
        $this->setDiscount($session, 0, $dispatcher);
    }

    /**
     * @throws \Propel\Runtime\Exception\PropelException
     * @throws \Exception
     */
    public function applyCreditDiscountInCartAndOrder($creditDiscountWanted, $force = true): void
    {
        if ($creditDiscountWanted < 0) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $session = $request?->getSession();
        if (!$session instanceof Session) {
            return;
        }

        $couponUsedArray = $this->couponManager->getCouponsKept();

        $cart = $session->getSessionCart($this->eventDispatcher);

        $taxCountry = $this->taxEngine->getDeliveryCountry();
        $taxState = $this->taxEngine->getDeliveryState();
        $totalCart = $cart->getTaxedAmount($taxCountry, false, $taxState);

        if (!empty($couponUsedArray)) {
            $consumedCoupons = $session->getConsumedCoupons();
            /** @var CouponAbstract $coupon */
            foreach ($couponUsedArray as $coupon) {
                if ($coupon->isCumulative()) {
                    continue;
                }
                if ($force) {
                    unset($consumedCoupons[$coupon->getCode()]);
                } else {
                    throw new \Exception(
                        Translator::getInstance()->trans(
                            "The coupon %s is not cumulative. Please remove other discount(s)",
                            ['%s' => $coupon->getCode()],
                            CreditAccount::DOMAIN
                        ),
                        449
                    );
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
        $order->setDiscount((string) $discountCart);

        // update cart
        $cart->setDiscount((string) $discountCart);
        $cart->save();

        // update session
        $this->setDiscount($session, $creditDiscountWanted, $this->eventDispatcher);
    }

    public function setDiscount(
        SessionInterface $session,
        $creditDiscountWanted,
        ?EventDispatcherInterface $dispatcher = null
    ): void {
        $session->set(self::SESSION_KEY_CREDIT_ACCOUNT_USED, $creditDiscountWanted);

        if ($dispatcher === null) {
            throw new InvalidArgumentException("dispatcher must be passed");
        }

        if (!$session instanceof Session) {
            return;
        }

        $dispatcher->dispatch(
            new CreditAccountEvent($session->getCustomerUser(), $creditDiscountWanted),
            CreditAccount::CREDIT_ACCOUNT_USED
        );
    }

    public function getDiscount(SessionInterface $session)
    {
        return $session->get(self::SESSION_KEY_CREDIT_ACCOUNT_USED, 0);
    }
}
