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

use CreditAccount\CreditAccount;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A credit or a debit written by hand on the account of a customer: the reason is required, the amount is
 * positive and the direction says which way it goes.
 */
final class AdjustmentType extends AbstractType
{
    public const NAME = 'creditaccount_adjustment';
    public const CREDIT = 'credit';
    public const DEBIT = 'debit';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('direction', ChoiceType::class, [
                'choices' => [self::CREDIT => self::CREDIT, self::DEBIT => self::DEBIT],
                'expanded' => true,
                'choice_label' => fn (string $direction): string => self::CREDIT === $direction
                    ? $this->translator->trans('Credit the account', [], CreditAccount::BACK_OFFICE_DOMAIN)
                    : $this->translator->trans('Debit the account', [], CreditAccount::BACK_OFFICE_DOMAIN),
                'constraints' => [new NotBlank()],
            ])
            ->add('amount', TextType::class, [
                'attr' => ['inputmode' => 'decimal'],
                'constraints' => [
                    new NotBlank(),
                    new Regex('/^(?!0+(\.0{1,2})?$)\d{1,10}(\.\d{1,2})?$/', $this->translator->trans('The amount is above 0 and has at most two decimals, such as 25 or 12.50.', [], CreditAccount::BACK_OFFICE_DOMAIN)),
                ],
            ])
            ->add('reason', TextType::class, [
                'constraints' => [new NotBlank(normalizer: 'trim'), new Length(max: 255)],
            ]);
    }
}
