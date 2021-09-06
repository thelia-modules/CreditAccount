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

use CreditAccount\Model\CreditAccountExpirationQuery;
use CreditAccount\Model\CreditAccountQuery;
use CreditAccount\Model\CreditAmountHistoryQuery;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Install\Database;
use Thelia\Module\BaseModule;

class CreditAccount extends BaseModule
{
    const DOMAIN = 'creditaccount';

    /**
     * event send when credit account change
     */
    const CREDIT_ACCOUNT_ADD_AMOUNT = 'creditAccount.addAccount';

    /**
     * Sent when credit account used change
     */
    const CREDIT_ACCOUNT_USED = 'creditAccount.used.change';

    public function postActivation(ConnectionInterface $con = null): void
    {
        $database = new Database($con);
        if (!self::getConfigValue('is_initialized', false)) {
            $database->insertSql(null, [__DIR__ . "/Config/thelia.sql"]);
            $database->insertSql(null, [__DIR__ . "/Config/sql/credit_account.sql"]);
            $database->insertSql(null, [__DIR__ . "/Config/sql/credit_account_expiration.sql"]);
            $database->insertSql(null, [__DIR__ . "/Config/sql/credit_amount_history.sql"]);
            self::setConfigValue('is_initialized', true);
        }
    }

    /**
     * @return array
     */
    public function getHooks()
    {
        return array(
            array(
                "type" => TemplateDefinition::FRONT_OFFICE,
                "code" => "order-invoice.before-discount",
                "title" => array(
                    "en_US" => "Before discount code form block"
                ),
                "active" => true
            )
        );
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }

}
