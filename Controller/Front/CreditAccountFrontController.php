<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
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