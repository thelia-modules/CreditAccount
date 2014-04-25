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
    public function useAmount()
    {
        $this->checkAuth();
        $this->checkCartNotEmpty();

        $customer = $this->getSecurityContext()->getCustomerUser();

        $creditAccount = CreditAccountQuery::create()
            ->findOneByCustomerId($customer->getId());

        if ($creditAccount->getAmount() > 0) {
            $cart = $this->getSession()->getCart();
            $order = $this->getSession()->getOrder();
            $taxCountry = $this->container->get('thelia.taxEngine')->getDeliveryCountry();

            $total = $cart->getTaxedAmount($taxCountry) + $order->getPostage();
            $totalDiscount = $creditAccount->getAmount();

            if ($totalDiscount > $total) {
                $totalDiscount = $total;
            }

            $order
                ->setDiscount($totalDiscount);

            $cart
                ->setDiscount($cart->getDiscount() + $totalDiscount)
                ->save();

            $this->getSession()->setOrder($order);
            $this->getSession()->set('creditAccount.used', 1);
            $this->getSession()->set('creditAccount.amount', $totalDiscount);

        }

        $this->redirectToRoute('order.invoice');
    }
} 