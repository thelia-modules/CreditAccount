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
use CreditAccount\BackOffice\ConfigurationForms;
use CreditAccount\BackOffice\CreditConfiguration;
use CreditAccount\CreditAccount;
use CreditAccount\Form\ConfigurationType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Saves the configuration form shown on the configuration page of the module. The page itself is the
 * `module.configuration` hook.
 */
#[AsController]
final readonly class ConfigurationController
{
    public function __construct(
        private AdminGate $gate,
        private ConfigurationForms $forms,
        private CreditConfiguration $configuration,
        private AdminFlash $flash,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/admin/module/CreditAccount/save', name: 'creditaccount_config_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if (null !== $refusal = $this->gate->refuseUnlessConfigurer()) {
            return $refusal;
        }

        $form = $this->forms->create($request->getLocale());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->configuration->save($this->settingsOf($form->getData()));
            $this->flash->success($this->translator->trans('The configuration is saved.', [], CreditAccount::BACK_OFFICE_DOMAIN));
        } else {
            $this->flash->rejected(ConfigurationType::NAME, $request->request->all(ConfigurationType::NAME));
            $this->flash->error($this->translator->trans('The configuration is not saved: check the fields below.', [], CreditAccount::BACK_OFFICE_DOMAIN));
        }

        return new RedirectResponse($this->urls->generate('admin.module.configure', ['module_code' => 'CreditAccount']));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{expiration_enabled: bool, expiration_delay: int, expiration_warning_days: int, loyalty_trigger_status: string, loyalty_mode: string, loyalty_percentage: ?string, tiers: list<array{min: string, max: ?string, amount: string}>, excluded_categories: list<int>, excluded_products: string}
     */
    private function settingsOf(array $data): array
    {
        $percentage = trim((string) ($data['loyalty_percentage'] ?? ''));

        return [
            'expiration_enabled' => (bool) $data['expiration_enabled'],
            'expiration_delay' => (int) $data['expiration_delay'],
            'expiration_warning_days' => (int) $data['expiration_warning_days'],
            'loyalty_trigger_status' => (string) $data['loyalty_trigger_status'],
            'loyalty_mode' => (string) $data['loyalty_mode'],
            'loyalty_percentage' => '' === $percentage ? null : $percentage,
            'tiers' => array_map(static fn (array $tier): array => [
                'min' => trim((string) $tier['min']),
                'max' => '' === trim((string) ($tier['max'] ?? '')) ? null : trim((string) $tier['max']),
                'amount' => trim((string) $tier['amount']),
            ], array_values((array) $data['tiers'])),
            'excluded_categories' => array_map('intval', array_values((array) $data['excluded_categories'])),
            'excluded_products' => (string) ($data['excluded_products'] ?? ''),
        ];
    }
}
