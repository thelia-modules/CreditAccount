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

namespace CreditAccount\BackOffice;

use CreditAccount\Form\ConfigurationType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Builds the configuration form for the language of the administrator: the choices it offers are titles.
 */
final readonly class ConfigurationForms
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private CreditConfiguration $configuration,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data the settings to show, the saved ones when null
     */
    public function create(string $locale, ?array $data = null): FormInterface
    {
        return $this->formFactory->createNamed(ConfigurationType::NAME, ConfigurationType::class, $data ?? $this->configuration->current(), [
            'status_choices' => $this->configuration->statusChoices($locale),
            'category_choices' => $this->configuration->categoryChoices($locale),
        ]);
    }
}
