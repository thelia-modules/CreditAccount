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

declare(strict_types=1);

namespace CreditAccount\Hook;

use CreditAccount\Form\ConfigurationForm;
use CreditAccount\Form\CreditAccountForm;
use CreditAccount\Model\CreditAccountQuery;
use CreditAccount\Model\CreditAmountHistory;
use CreditAccount\Model\CreditAmountHistoryQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\OrderQuery;

/**
 * Back-office hooks for the CreditAccount module (Thelia 3, default-twig).
 */
class HookManager extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
            'customer.edit' => [
                ['type' => 'back', 'method' => 'onCustomerEdit'],
            ],
            'order-edit.after-order-product-list' => [
                ['type' => 'back', 'method' => 'onOrderEditAfterProductList'],
            ],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $form = $this->formFactory->createForm(ConfigurationForm::getName());

        $accounts = [];
        $creditAccounts = CreditAccountQuery::create()
            ->joinWithCustomer()
            ->orderBy(CustomerTableMap::COL_LASTNAME)
            ->find();

        foreach ($creditAccounts as $creditAccount) {
            $customer = $creditAccount->getCustomer();
            if (null === $customer) {
                continue;
            }

            $accounts[] = [
                'id' => $creditAccount->getId(),
                'lastname' => $customer->getLastname(),
                'firstname' => $customer->getFirstname(),
                'email' => $customer->getEmail(),
                'balance' => (float) $creditAccount->getAmount(),
                'customer_id' => $customer->getId(),
            ];
        }

        $event->add(
            $this->render('CreditAccount/configuration.html.twig', [
                'form' => $form->createView()->getView(),
                'accounts' => $accounts,
                'currency_symbol' => $this->getDefaultCurrencySymbol(),
            ])
        );
    }

    public function onCustomerEdit(HookRenderEvent $event): void
    {
        $customerId = (int) $event->getArgument('customer_id');

        $form = $this->formFactory->createForm(CreditAccountForm::getName());

        $creditAccount = CreditAccountQuery::create()->findOneByCustomerId($customerId);
        $balance = null !== $creditAccount ? (float) $creditAccount->getAmount() : 0.0;

        $history = [];
        if (null !== $creditAccount) {
            $entries = CreditAmountHistoryQuery::create()
                ->filterByCreditAccountId($creditAccount->getId())
                ->orderByCreatedAt(Criteria::DESC)
                ->find();

            // Preload the order refs in a single query to avoid an N+1 on the history.
            $orderIds = array_values(array_filter(array_map(
                static fn (CreditAmountHistory $entry): ?int => $entry->getOrderId(),
                iterator_to_array($entries)
            )));
            $orderRefs = [];
            if ([] !== $orderIds) {
                foreach (OrderQuery::create()->filterById($orderIds)->select(['Id', 'Ref'])->find() as $row) {
                    $orderRefs[(int) $row['Id']] = $row['Ref'];
                }
            }

            foreach ($entries as $entry) {
                $history[] = $this->presentHistoryEntry($entry, $orderRefs);
            }
        }

        $event->add(
            $this->render('CreditAccount/customer-edit.html.twig', [
                'form' => $form->createView()->getView(),
                'customer_id' => $customerId,
                'balance' => $balance,
                'currency_symbol' => $this->getDefaultCurrencySymbol(),
                'history' => $history,
            ])
        );
    }

    public function onOrderEditAfterProductList(HookRenderEvent $event): void
    {
        $orderId = (int) $event->getArgument('order_id');

        $entries = [];
        foreach (CreditAmountHistoryQuery::create()->filterByOrderId($orderId)->find() as $entry) {
            $entries[] = ['amount' => (float) $entry->getAmount()];
        }

        $event->add(
            $this->render('CreditAccount/order-usage.html.twig', [
                'entries' => $entries,
                'currency_symbol' => $this->getDefaultCurrencySymbol(),
            ])
        );
    }

    /**
     * @param array<int, string> $orderRefs
     *
     * @return array<string, mixed>
     */
    private function presentHistoryEntry(CreditAmountHistory $entry, array $orderRefs): array
    {
        $orderId = $entry->getOrderId();
        $orderRef = !empty($orderId) ? ($orderRefs[$orderId] ?? null) : null;

        return [
            'amount' => (float) $entry->getAmount(),
            'created_at' => $entry->getCreatedAt(),
            'who' => $entry->getWho(),
            'order_id' => $orderId,
            'order_ref' => $orderRef,
        ];
    }

    private function getDefaultCurrencySymbol(): string
    {
        $currency = CurrencyQuery::create()->filterByByDefault(1)->findOne();

        return $currency?->getSymbol() ?? '';
    }
}
