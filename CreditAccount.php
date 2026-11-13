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
use CreditAccount\Spending\CreditPaymentPart;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Install\Database;
use Thelia\Model\Cart;
use Thelia\Model\LangQuery;
use Thelia\Model\Message;
use Thelia\Model\MessageQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPart;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Module\InstantPaymentModuleInterface;

/**
 * The credit of a customer, and a payment means that settles part of an order inside the shop: the amount the
 * buyer chose to spend on the cart pays its share when the order is paid, a provider that charges a balance
 * takes the rest (Thelia\Module\InstantPaymentModuleInterface, Thelia 3.3).
 */
class CreditAccount extends AbstractPaymentModule implements InstantPaymentModuleInterface
{
    public const DOMAIN = 'creditaccount';

    /**
     * The translation domain of the back-office templates of the module (I18n/backOffice/default).
     */
    public const BACK_OFFICE_DOMAIN = 'creditaccount.bo.default';

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

    public function getAmountAppliedToCart(Cart $cart): string
    {
        return $this->paymentPart()->amountAppliedTo($cart);
    }

    public function settlePart(OrderPaymentPart $part): void
    {
        $this->paymentPart()->settle($part);
    }

    public function releasePart(OrderPaymentPart $part): void
    {
        $this->paymentPart()->release($part);
    }

    /**
     * Nothing to ask a provider: the core settles the part before it gets here, and does not call this at all
     * for an order the credit covers whole.
     */
    public function pay(Order $order): ?Response
    {
        return null;
    }

    /**
     * Offered to a signed-in customer who holds credit, on the cart being judged.
     */
    public function isValidPayment(): bool
    {
        return $this->paymentPart()->isUsableOnTheCartBeingPaid();
    }

    /**
     * An unpaid order may be presented to the payment again: the amount applied is read from what the buyer chose
     * for the cart, which the settlement leaves as it is until the order is paid.
     */
    public function supportsPaymentRetry(): bool
    {
        return true;
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([__DIR__.'/CreditAccount.php', __DIR__.'/I18n/*', __DIR__.'/Tests/*', __DIR__.'/Model/*', __DIR__.'/Migration/*', __DIR__.'/DependencyInjection/*'])
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

    private function paymentPart(): CreditPaymentPart
    {
        return $this->getContainer()->get(CreditPaymentPart::class);
    }

    private function allowOneAccountPerCustomer(ConnectionInterface $connection): void
    {
        $existing = $connection->query("SHOW INDEX FROM `credit_account` WHERE `Key_name` = 'unq_credit_account_customer_id'");

        if (false === $existing->fetch()) {
            $connection->exec('ALTER TABLE `credit_account` ADD UNIQUE INDEX `unq_credit_account_customer_id` (`customer_id`)');
        }
    }
}
