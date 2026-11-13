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

namespace CreditAccount\Form;

use CreditAccount\BackOffice\CreditConfiguration;
use CreditAccount\CreditAccount;
use CreditAccount\Loyalty\LoyaltyMode;
use CreditAccount\Loyalty\TierGridRules;
use CreditAccount\Loyalty\TierProblem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The settings of the module: expiration, loyalty, grid and exclusions. The data is the array of
 * {@see CreditConfiguration::current()}; a field the form does not know is refused.
 */
final class ConfigurationType extends AbstractType
{
    public const NAME = 'creditaccount_configuration';

    public function __construct(
        private readonly CreditConfiguration $configuration,
        private readonly TierGridRules $gridRules,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('expiration_enabled', CheckboxType::class, ['required' => false])
            ->add('expiration_delay', IntegerType::class, ['constraints' => [new NotBlank(), new Range(min: 1, max: 120)]])
            ->add('expiration_warning_days', IntegerType::class, ['constraints' => [new NotBlank(), new Range(min: 0, max: 365)]])
            ->add('loyalty_trigger_status', ChoiceType::class, ['choices' => $options['status_choices'], 'constraints' => [new NotBlank()]])
            ->add('loyalty_mode', ChoiceType::class, [
                'choices' => array_column(LoyaltyMode::cases(), 'value', 'value'),
                'choice_label' => fn (string $mode): string => match (LoyaltyMode::from($mode)) {
                    LoyaltyMode::None => $this->translator->trans('No credit earned', [], CreditAccount::BACK_OFFICE_DOMAIN),
                    LoyaltyMode::Grid => $this->translator->trans('From a grid of tiers', [], CreditAccount::BACK_OFFICE_DOMAIN),
                    LoyaltyMode::Percentage => $this->translator->trans('A percentage of the order', [], CreditAccount::BACK_OFFICE_DOMAIN),
                },
                'constraints' => [new NotBlank()],
            ])
            ->add('loyalty_percentage', TextType::class, [
                'required' => false,
                'attr' => ['inputmode' => 'decimal'],
                'constraints' => [new Regex('/^\d{1,3}(\.\d{1,2})?$/', $this->translator->trans('A percentage has at most two decimals, such as 2.5.', [], CreditAccount::BACK_OFFICE_DOMAIN))],
            ])
            ->add('tiers', CollectionType::class, [
                'entry_type' => TierType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => false,
                'error_bubbling' => false,
                'required' => false,
                'entry_options' => ['label' => false],
            ])
            ->add('excluded_categories', ChoiceType::class, ['choices' => $options['category_choices'], 'multiple' => true, 'required' => false])
            ->add('excluded_products', TextareaType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'status_choices' => [],
            'category_choices' => [],
            'constraints' => [new Callback($this->validateTogether(...))],
        ]);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function validateTogether(array $settings, ExecutionContextInterface $context): void
    {
        $domain = CreditAccount::BACK_OFFICE_DOMAIN;
        $mode = $settings['loyalty_mode'] ?? LoyaltyMode::None->value;
        $tiers = array_values(array_filter((array) ($settings['tiers'] ?? []), 'is_array'));

        if (LoyaltyMode::Percentage->value === $mode) {
            $percentage = $settings['loyalty_percentage'] ?? null;

            if (!\is_string($percentage) || !is_numeric($percentage) || (float) $percentage <= 0.0 || (float) $percentage > 100.0) {
                $context->buildViolation($this->translator->trans('Give a percentage above 0 and up to 100.', [], $domain))->atPath('[loyalty_percentage]')->addViolation();
            }
        }

        if (LoyaltyMode::Grid->value === $mode && [] === $tiers) {
            $context->buildViolation($this->translator->trans('The grid needs at least one tier.', [], $domain))->atPath('[tiers]')->addViolation();
        }

        $problem = $this->gridRules->problemOf(array_map(static fn (array $tier): array => ['min' => $tier['min'] ?? null, 'max' => $tier['max'] ?? null, 'amount' => $tier['amount'] ?? null], $tiers));

        if (null !== $problem) {
            $message = match ($problem) {
                TierProblem::InvalidRow => $this->translator->trans('Each tier needs a minimum and an amount above 0, and a maximum above its minimum if any.', [], $domain),
                TierProblem::OnlyTheLastIsOpen => $this->translator->trans('Only the last tier may have no maximum.', [], $domain),
                TierProblem::Overlap => $this->translator->trans('Two tiers cover the same amounts: a tier ends where the next one starts.', [], $domain),
            };
            $context->buildViolation($message)->atPath('[tiers]')->addViolation();
        }

        $unknown = $this->configuration->resolveProductReferences((string) ($settings['excluded_products'] ?? ''))['unknown'];

        if ([] !== $unknown) {
            $context->buildViolation($this->translator->trans('No product has the reference: %references%.', ['%references%' => implode(', ', $unknown)], $domain))->atPath('[excluded_products]')->addViolation();
        }
    }
}
