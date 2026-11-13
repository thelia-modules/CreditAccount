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

namespace CreditAccount\Controller\Admin;

use CreditAccount\BackOffice\AdminFlash;
use CreditAccount\BackOffice\AdminGate;
use CreditAccount\CreditAccount;
use CreditAccount\Form\AdjustmentType;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Ledger\ManualAdjustment;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\CustomerQuery;

/**
 * Writes the credit or the debit typed on the customer sheet, under the dedicated manual credit right. The
 * sheet itself shows the form through the `customer.edit` hook.
 */
#[AsController]
final readonly class CustomerCreditController
{
    public function __construct(
        private AdminGate $gate,
        private FormFactoryInterface $formFactory,
        private ManualAdjustment $adjustment,
        private AdminFlash $flash,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/admin/module/CreditAccount/customer/{customerId}/adjust', name: 'creditaccount_customer_adjust', requirements: ['customerId' => '\d+'], methods: ['POST'])]
    public function adjust(int $customerId, Request $request): Response
    {
        if (null !== $refusal = $this->gate->refuseUnlessManualWriter()) {
            return $refusal;
        }

        $admin = $this->gate->admin();

        if (null === $admin || null === CustomerQuery::create()->findPk($customerId)) {
            throw new NotFoundHttpException(\sprintf('No customer has the id %d.', $customerId));
        }

        $domain = CreditAccount::BACK_OFFICE_DOMAIN;
        $form = $this->formFactory->createNamed(AdjustmentType::NAME, AdjustmentType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->flash->rejected(AdjustmentType::NAME, $request->request->all(AdjustmentType::NAME));
            $this->flash->error($this->translator->trans('The movement is not written: check the fields below.', [], $domain));

            return $this->backToTheSheet($customerId);
        }

        $data = $form->getData();
        $amount = CreditAmount::ofDecimal((string) $data['amount']);

        try {
            $this->adjustment->write($customerId, AdjustmentType::DEBIT === $data['direction'] ? $amount->negated() : $amount, (string) $data['reason'], $admin);
            $this->flash->success($this->translator->trans('The movement is written.', [], $domain));
        } catch (InsufficientCreditException $exception) {
            $this->flash->rejected(AdjustmentType::NAME, $request->request->all(AdjustmentType::NAME));
            $this->flash->error($this->translator->trans('The debit of %amount% is refused: the balance is %balance%.', ['%amount%' => $exception->requested->toDecimal(), '%balance%' => $exception->balance->toDecimal()], $domain));
        }

        return $this->backToTheSheet($customerId);
    }

    private function backToTheSheet(int $customerId): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate('admin.customer.update.view', ['customer_id' => $customerId]).'#creditaccount-card');
    }
}
