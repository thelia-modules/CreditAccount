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
use CreditAccount\Event\CreditAccountEvent;
use CreditAccount\Model\CreditAccountQuery;
use CreditAccount\Model\CreditAccount as CreditAccountModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;


/**
 * Class CreditEventListener
 * @package CreditAccount\EventListeners
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CreditEventListener implements EventSubscriberInterface
{

    /**
     * @var \Thelia\Core\HttpFoundation\Request
     */
    protected $request;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

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

        $creditAccount
            ->addAmount($event->getAmount(), $event->getOrderId(), $event->getWhoDidIt())
            ->save();

        $event->setCreditAccount($creditAccount);
    }

    public function verifyCreditUsage(OrderEvent $event)
    {
        $session = $this->request->getSession();

        if ($session->get('creditAccount.used') == 1) {
            $customer = $event->getOrder()->getCustomer();
            $amount = $session->get('creditAccount.amount');

            $creditEvent = new CreditAccountEvent($customer, ($amount*-1), $event->getOrder()->getId());

            $creditEvent
                ->setWhoDidIt(Translator::getInstance()->trans('Customer', [], CreditAccount::DOMAIN))
                ->setOrderId($event->getOrder()->getId())
            ;

            $event->getDispatcher()->dispatch(CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT, $creditEvent);

            $session->set('creditAccount.used', 0);
            $session->set('creditAccount.amount', 0);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT => ['addAmount', 128],
            TheliaEvents::ORDER_BEFORE_PAYMENT => ['verifyCreditUsage', 128]
        ];
    }
}