<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CreditAccount;

use CreditAccount\Migration\LegacyLedgerImport;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Install\Database;
use Thelia\Module\BaseModule;

class CreditAccount extends BaseModule
{
    public const DOMAIN = 'creditaccount';

    /**
     * Sent by a module that credits or debits a customer, with a CreditAccountEvent: 3.x kept, routed to the journal.
     */
    public const CREDIT_ACCOUNT_ADD_AMOUNT = 'creditAccount.addAccount';

    public function postActivation(?ConnectionInterface $con = null): void
    {
        // Every statement is a CREATE TABLE IF NOT EXISTS: activating the module again keeps the journal.
        (new Database($con))->insertSql(null, [__DIR__.'/Config/thelia.sql']);
    }

    /**
     * Runs every Config/update/<version>.sql newer than the installed version. Crossing 4.0.0 also copies
     * the 3.x history into the journal, then lets one account per customer exist.
     */
    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $finder = Finder::create()->name('*.sql')->depth(0)->sortByName()->in(__DIR__.'/Config/update');
        $database = new Database($con);

        foreach ($finder as $file) {
            if (version_compare((string) $currentVersion, $file->getBasename('.sql'), '<')) {
                $database->insertSql(null, [$file->getPathname()]);
            }
        }

        if (version_compare((string) $currentVersion, '4.0.0', '<')) {
            $connection = $con ?? Propel::getWriteConnection('TheliaMain');

            (new LegacyLedgerImport())->import($connection);

            $this->allowOneAccountPerCustomer($connection);
        }
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/I18n/*', __DIR__.'/Tests/*', __DIR__.'/Model/*', __DIR__.'/Migration/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }

    private function allowOneAccountPerCustomer(ConnectionInterface $connection): void
    {
        $existing = $connection->query("SHOW INDEX FROM `credit_account` WHERE `Key_name` = 'unq_credit_account_customer_id'");

        if (false === $existing->fetch()) {
            $connection->exec('ALTER TABLE `credit_account` ADD UNIQUE INDEX `unq_credit_account_customer_id` (`customer_id`)');
        }
    }
}
