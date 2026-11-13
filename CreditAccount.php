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

use CreditAccount\DependencyInjection\AdminApiResourcesPass;
use CreditAccount\Expiration\CreditExpirer;
use CreditAccount\Migration\LegacyLedgerImport;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Install\Database;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Module\BaseModule;

class CreditAccount extends BaseModule
{
    public const DOMAIN = 'creditaccount';

    /**
     * Sent by a module that credits or debits a customer, with a CreditAccountEvent: 3.x kept, routed to the journal.
     */
    public const CREDIT_ACCOUNT_ADD_AMOUNT = 'creditAccount.addAccount';

    /**
     * The admin right that guards the manual credit API: a profile is granted it apart from the customer rights.
     * Lowercase, as the permission check lowercases the code it looks for.
     */
    public const MANUAL_CREDIT_RESOURCE = 'module.creditaccount.manual-credit';

    /**
     * @return list<AdminApiResourcesPass>
     */
    public static function getCompilers(): array
    {
        return [new AdminApiResourcesPass()];
    }

    public function postActivation(?ConnectionInterface $con = null): void
    {
        // Every statement is a CREATE TABLE IF NOT EXISTS: activating the module again keeps the journal.
        (new Database($con))->insertSql(null, [__DIR__.'/Config/thelia.sql']);

        $this->ensureMessages($con);
    }

    /**
     * Runs every Config/update/<version>.sql newer than the installed version. Crossing 4.0.0 also copies
     * the 3.x history into the journal, then lets one account per customer exist.
     */
    public function update($currentVersion, $newVersion, ?ConnectionInterface $con = null): void
    {
        $this->ensureMessages($con);

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
            ->exclude([__DIR__.'/I18n/*', __DIR__.'/Tests/*', __DIR__.'/Model/*', __DIR__.'/Migration/*', __DIR__.'/DependencyInjection/*'])
            ->autowire(true)
            ->autoconfigure(true);
    }

    /**
     * Creates the expiration warning message, and gives it the languages of the shop it lacks: a title or a
     * subject that is there is kept, so is the text a merchant edited. The body is the template of the message,
     * `templates/email/default/expiration-warning.{html,txt}.twig`.
     */
    private function ensureMessages(?ConnectionInterface $con = null): void
    {
        $message = MessageQuery::create()->findOneByName(CreditExpirer::WARNING_MESSAGE) ?? (new Message())
            ->setName(CreditExpirer::WARNING_MESSAGE)
            ->setHtmlTemplateFileName('expiration-warning.html')
            ->setTextTemplateFileName('expiration-warning.txt')
            ->setSecured(0);

        foreach (LangQuery::create()->find($con) as $lang) {
            $message->setLocale($lang->getLocale());

            if ('' === trim((string) $message->getTitle())) {
                $message->setTitle('Credit expiration warning');
            }

            if ('' === trim((string) $message->getSubject())) {
                $message->setSubject('Your store credit is about to expire');
            }
        }

        $message->save($con);
    }

    private function allowOneAccountPerCustomer(ConnectionInterface $connection): void
    {
        $existing = $connection->query("SHOW INDEX FROM `credit_account` WHERE `Key_name` = 'unq_credit_account_customer_id'");

        if (false === $existing->fetch()) {
            $connection->exec('ALTER TABLE `credit_account` ADD UNIQUE INDEX `unq_credit_account_customer_id` (`customer_id`)');
        }
    }
}
