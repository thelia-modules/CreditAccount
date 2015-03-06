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

namespace CreditAccount\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class HookManager
 *
 * @package CreditAccount\Hook
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class HookManager extends BaseHook
{
    public function onAccountBottom(HookRenderEvent $event)
    {
        $event->add(
            $this->render("credit-account-status.html")
        );
    }

    public function accountUsageInOrder(HookRenderEvent $event)
    {
        $event->add(
            $this->render("credit-account-usage-on-order.html", [ 'order_id' => $event->getArgument('order_id') ])
        );
    }
}
