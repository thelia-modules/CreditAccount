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
use Thelia\Controller\Front\BaseFrontController;

/**
 * Class CreditAccountFrontController
 * @package CreditAccount\Controller\Front
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CreditAccountFrontController extends BaseFrontController
{
    public function cancelUsage()
    {
        $this->checkAuth();

        if (0 !== $this->getSession()->get('creditAccount.used', 0)) {
            $usedAmount = $this->getSession()->get('creditAccount.amount', 0);

            if ($usedAmount > 0) {
                $cart = $this->getSession()->getSessionCart($this->getDispatcher());
                $order = $this->getSession()->getOrder();

                $order->setDiscount($order->getDiscount() - $usedAmount);

                $cart
                    ->setDiscount($cart->getDiscount() - $usedAmount)
                    ->save();

                $this->getSession()->set('creditAccount.used', 0);
                $this->getSession()->set('creditAccount.amount', 0);
            }
        }

        return $this->generateRedirectFromRoute('order.invoice');
    }

    public function useAmount()
    {
        $this->checkAuth();
        $this->checkCartNotEmpty();

        $customer = $this->getSecurityContext()->getCustomerUser();

        $creditAccount = CreditAccountQuery::create()
            ->findOneByCustomerId($customer->getId());
        $creditUsed = $this->getSession()->get('creditAccount.used');
        $couponUsed = $this->getSession()->get('thelia.consumed_coupons');
        $couponUsedFlag = empty($couponUsed);

        if ($creditAccount->getAmount() > 0 && $creditUsed !== 1 && $couponUsedFlag !==1) {
            $cart = $this->getSession()->getSessionCart($this->getDispatcher());
            $order = $this->getSession()->getOrder();
            $taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry();

            $total = $cart->getTaxedAmount($taxCountry);

            $totalDiscount = $creditAccount->getAmount();

            if ($totalDiscount > $total) {
                $totalDiscount = $total;
            }

            $order
                ->setDiscount($totalDiscount);

            $cart
                ->setDiscount($cart->getDiscount() + $totalDiscount)
                ->save();

            $this->getSession()->set('creditAccount.used', 1);
            $this->getSession()->set('creditAccount.amount', $totalDiscount);
        }

        return $this->generateRedirectFromRoute('order.invoice');
    }
}
