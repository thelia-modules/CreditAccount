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

namespace CreditAccount\Hook;

use CreditAccount\BackOffice\AdminFlash;
use CreditAccount\BackOffice\AdminGate;
use CreditAccount\BackOffice\ConfigurationForms;
use CreditAccount\BackOffice\CreditJournal;
use CreditAccount\Form\AdjustmentType;
use CreditAccount\Form\ConfigurationType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

/**
 * The three places of the back-office Twig where the module shows up: its configuration page, the customer sheet
 * and the order.
 */
class BackOfficeHook extends BaseHook
{
    public function __construct(
        private readonly ConfigurationForms $configurationForms,
        private readonly CreditJournal $journal,
        private readonly AdminGate $gate,
        private readonly AdminFlash $flash,
        private readonly FormFactoryInterface $formFactory,
        private readonly RequestStack $requestStack,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    /**
     * @return array<string, list<array{type: string, method: string}>>
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [['type' => 'back', 'method' => 'onModuleConfiguration']],
            'customer.edit' => [['type' => 'back', 'method' => 'onCustomerEdit']],
            'order-edit.after-order-product-list' => [['type' => 'back', 'method' => 'onOrderEdit']],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        // The hook is sent on the configuration page of every module.
        if ('CreditAccount' !== $event->getArgument('modulecode')) {
            return;
        }

        $canUpdate = $this->gate->canConfigure();
        $form = null;

        if ($canUpdate) {
            $submitted = $this->flash->takeRejected(ConfigurationType::NAME);
            $form = $this->configurationForms->create($this->locale(), $submitted);

            if (null !== $submitted) {
                $form->submit($submitted);
            }
        }

        $event->add($this->render('CreditAccount/configuration.html.twig', [
            'form' => $form?->createView(),
            'can_update' => $canUpdate,
        ]));
    }

    public function onCustomerEdit(HookRenderEvent $event): void
    {
        $customerId = (int) $event->getArgument('customer_id');
        $canWrite = $this->gate->canWriteManualMovements();
        $form = null;

        if ($canWrite) {
            $form = $this->adjustmentForm();
        }

        $event->add($this->render('CreditAccount/customer-credit.html.twig', [
            'customer_id' => $customerId,
            'journal' => $this->journal->ofCustomer($customerId, (int) $this->requestStack->getCurrentRequest()?->query->get('credit_page', 1)),
            'form' => $form?->createView(),
        ]));
    }

    public function onOrderEdit(HookRenderEvent $event): void
    {
        $event->add($this->render('CreditAccount/order-credit.html.twig', [
            'journal' => $this->journal->ofOrder((int) $event->getArgument('order_id')),
        ]));
    }

    private function adjustmentForm(): FormInterface
    {
        $submitted = $this->flash->takeRejected(AdjustmentType::NAME);
        $form = $this->formFactory->createNamed(AdjustmentType::NAME, AdjustmentType::class, $submitted ? null : ['direction' => AdjustmentType::CREDIT]);

        if (null !== $submitted) {
            $form->submit($submitted);
        }

        return $form;
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en_US';
    }
}
