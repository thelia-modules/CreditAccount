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

namespace CreditAccount;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Hook\HookCreateEvent;
use Thelia\Core\Event\Hook\ModuleHookCreateEvent;
use Thelia\Core\Event\Hook\ModuleHookToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Core\Translation\Translator;
use Thelia\Exception\TheliaProcessException;
use Thelia\Install\Database;
use Thelia\Model\HookQuery;
use Thelia\Module\BaseModule;

class CreditAccount extends BaseModule
{
    const DOMAIN = 'creditaccount';

    const CREDIT_ACCOUNT_ADD_AMOUNT = 'creditAccount.addAccount';

    public function postActivation(ConnectionInterface $con = null)
    {
        $database = new Database($con->getWrappedConnection());

        $database->insertSql(null, [__DIR__ . '/Config/thelia.sql']);

        // Add order-invoice.before-discount hook if not already defined
        if (null === HookQuery::create()->findOneByCode('order-invoice.before-discount')) {
            try {
                $hookEvent = new HookCreateEvent();

                $hookEvent
                    ->setCode('order-invoice.before-discount')
                    ->setType(TemplateDefinition::FRONT_OFFICE)
                    ->setNative(false)
                    ->setActive(true)
                    ->setLocale('en_US')
                    ->setTitle("Before discount code form block");

                $this->getDispatcher()->dispatch(TheliaEvents::HOOK_CREATE, $hookEvent);

                if ($hookEvent->hasHook()) {
                    // Assign module to this hook
                    $moduleHookEvent = new ModuleHookCreateEvent();

                    $moduleHookEvent
                        ->setModuleId($this->getModuleId())
                        ->setHookId($hookEvent->getHook()->getId())
                        ->setClassname('creditaccount.order_invoice.hook')
                        ->setMethod('orderInvoiceForm');

                    // Activate module hook
                    $this->getDispatcher()->dispatch(TheliaEvents::MODULE_HOOK_CREATE, $moduleHookEvent);

                    if ($moduleHookEvent->hasModuleHook()) {
                        $event = new ModuleHookToggleActivationEvent($moduleHookEvent->getModuleHook());

                        $this->getDispatcher()->dispatch(TheliaEvents::MODULE_HOOK_TOGGLE_ACTIVATION, $event);
                    }
                }
            } catch (\Exception $ex) {
                throw new TheliaProcessException(
                    Translator::getInstance()->trans(
                        "Failed to put module in 'order-invoice.before-discount' hook (%err)",
                        ['%err' => $ex->getMessage()]
                    ),
                    $ex
                );
            }
        }
    }
}
