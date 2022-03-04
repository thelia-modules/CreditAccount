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

namespace CreditAccount\EventListeners;

use CreditAccount\CreditAccount;
use CreditAccount\CreditAccountManager;
use CreditAccount\Event\CreditAccountEvent;
use CreditAccount\Model\CreditAccount as CreditAccountModel;
use CreditAccount\Model\CreditAccountExpiration;
use CreditAccount\Model\CreditAccountExpirationQuery;
use CreditAccount\Model\CreditAccountQuery;
use CreditAccount\Model\CreditAmountHistory;
use CreditAccount\Model\CreditAmountHistoryQuery;
use OpenApi\Events\ModelExtendDataEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Coupon\CouponManager;
use Thelia\Model\CouponQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Order;


/**
 * Class CreditEventListener
 * @package CreditAccount\EventListeners
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CreditEventListener implements EventSubscriberInterface
{
    const CANCELED = 'canceled';

    /**
     * @var \Thelia\Core\HttpFoundation\Request
     */
    protected $requestStack;

    /**
     * @var \Thelia\Core\Translation\Translator
     */
    protected $translator;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var CreditAccountManager
     */
    private $creditAccountManager;

    /**
     * @var CouponManager
     */
    private $couponManager;

    /**
     * CreditEventListener constructor.
     * @param RequestStack $requestStack
     * @param Translator $translator
     * @param CreditAccountManager $creditAccountManager
     * @param CouponManager $couponManager
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(
        RequestStack $requestStack,
        Translator $translator,
        CreditAccountManager $creditAccountManager,
        CouponManager $couponManager,
        EventDispatcherInterface $dispatcher)
    {
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->creditAccountManager = $creditAccountManager;
        $this->couponManager = $couponManager;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param CreditAccountEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function addAmount(CreditAccountEvent $event)
    {
        $customer = $event->getCustomer();

        $creditAccount = CreditAccountQuery::create()
            ->filterByCustomerId($customer->getId())
            ->findOne();
        ;

        if (null === $creditAccount) {
            $creditAccount = (new CreditAccountModel())
                ->setCustomerId($customer->getId());
        }

        $creditAccount->addAmount($event->getAmount(), $event->getOrderId(), $event->getWhoDidIt())
            ->save();

        $event->setCreditAccount($creditAccount);
    }

    /**
     * @param CreditAccountEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateOrCreateExpiration(CreditAccountEvent $event)
    {
        if (CreditAccount::getConfigValue('expiration_enabled', false) === "true"  && $event->getAmount() > 0) {
            $creditAccountExpiration =  CreditAccountExpirationQuery::create()
                ->filterByCreditAccountId($event->getCreditAccount()->getId())
                ->findOneOrCreate();

            $creditAccountExpiration->setExpirationStart(new \DateTime())
                ->setExpirationDelay(CreditAccount::getConfigValue('expiration_delay', 18));

            $creditAccountExpiration->save();
        }
    }

    /**
     * @param OrderEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function verifyCreditUsage(OrderEvent $event)
    {
        $session = $this->requestStack->getSession();
        $amount = $this->creditAccountManager->getDiscount($session);
        if ($amount > 0) {
            $customer = $event->getOrder()->getCustomer();

            $creditEvent = new CreditAccountEvent($customer, ($amount*-1), $event->getOrder()->getId());

            /** @noinspection PhpTranslationKeyInspection */
            $creditEvent
                ->setWhoDidIt(Translator::getInstance()->trans('Customer', [], CreditAccount::DOMAIN))
                ->setOrderId($event->getOrder()->getId())
            ;

            $this->dispatcher->dispatch($creditEvent, CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT);

            $this->creditAccountManager->setDiscount($session, 0, $this->dispatcher);
        }
    }

    /**
     * @param CouponConsumeEvent $event
     * @throws \Exception
     */
    public function verifyCoupon(CouponConsumeEvent $event)
    {
        $session = $this->requestStack->getSession();
        $couponQuery = CouponQuery::create();
        /** @noinspection PhpParamsInspection */
        $coupon = $couponQuery->findOneByCode($event->getCode());
        if (($this->creditAccountManager->getDiscount($session) > 0 || $this->couponManager->getDiscount() > 0) && !$coupon->getIsCumulative()) {
            /** @noinspection PhpTranslationKeyInspection */
            throw new \Exception(
                 Translator::getInstance()->trans("The coupon %s is not cumulative. Please remove other discount(s)", ['%s' => $coupon->getCode()], CreditAccount::DOMAIN)
             );
        }
    }

    /**
     * @param Order $order
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function removeOrderCredit(Order $order)
    {
        /** @var CreditAmountHistory[] $haveCredits */
        /** @noinspection PhpParamsInspection */
        $haveCredits = CreditAmountHistoryQuery::create()
            ->findByOrderId($order->getId());

        /** @var CreditAmountHistory $haveCredit */
        foreach ($haveCredits as $haveCredit) {
            $creditEvent = new CreditAccountEvent($order->getCustomer(), -($haveCredit->getAmount()), $order->getId());
            $this->dispatcher->dispatch($creditEvent, CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT);
        }
    }

    /**
     * @param OrderEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateCreditOnCancel(OrderEvent $event)
    {
        $order = $event->getOrder();
        if ($order->isCancelled()) {
            $this->removeOrderCredit($order);
        }
    }

    /**
     * @param CartEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     * @throws \Exception
     */
    public function checkCreditExpiration(CartEvent $event)
    {
        $customerId  = $event->getCart()->getCustomerId();

        if (null === $customerId) {
            return;
        }

        /** @var CreditAccountExpiration $creditExpiration */
        $creditExpiration = CreditAccountExpirationQuery::create()
            ->useCreditAccountQuery()
                ->filterByCustomerId($customerId)
            ->endUse()
            ->findOne();

        if (null === $creditExpiration) {
            return;
        }

        $expirationDelay = $creditExpiration->getExpirationDelay();
        $interval = new \DateInterval('P'.$expirationDelay.'M');

        /** @var \DateTime $startDate */
        $startDate = $creditExpiration->getExpirationStart();
        $expirationDate = $startDate->add($interval);

        $now = new \DateTime();

        if ($now > $expirationDate) {
            /** @noinspection PhpParamsInspection */
            $customer = CustomerQuery::create()
                ->findOneById($customerId);

            /** @noinspection PhpParamsInspection */
            $creditAccount = CreditAccountQuery::create()
                ->findOneByCustomerId($customer->getId());

            $event = new CreditAccountEvent($customer, -($creditAccount->getAmount()));
            $event->setWhoDidIt("Expiration $expirationDelay months");
            $this->dispatcher->dispatch($event, CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT);

            $creditExpiration->delete();
        }
    }

    public function addCreditFieldToCartModel(ModelExtendDataEvent $event)
    {
        $event->setExtendDataKeyValue('credit_used', $this->requestStack->getSession()->get('creditAccount.used'));
    }

    public static function getSubscribedEvents()
    {
        return [
            CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT => [
                    ['addAmount', 128],
                    ['updateOrCreateExpiration', 64],
                ],
            TheliaEvents::ORDER_BEFORE_PAYMENT => ['verifyCreditUsage', 128],
            TheliaEvents::ORDER_UPDATE_STATUS => ['updateCreditOnCancel'],
            TheliaEvents::COUPON_CONSUME => ["verifyCoupon", 140],
            TheliaEvents::CART_ADDITEM => ["checkCreditExpiration", 10],
            'add_extend_data_cart' => ["addCreditFieldToCartModel", 128]
        ];
    }
}