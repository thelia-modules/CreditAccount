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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * One row of the loyalty grid: an order worth from min (included) up to max (excluded, empty for no limit) earns amount.
 */
final class TierType extends AbstractType
{
    private const AMOUNT_PATTERN = '/^\d{1,10}(\.\d{1,2})?$/';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $message = $this->translator->trans('An amount has at most two decimals, such as 25 or 12.50.', [], CreditAccount::BACK_OFFICE_DOMAIN);
        $required = [new NotBlank(), new Regex(self::AMOUNT_PATTERN, $message)];

        $builder
            ->add('min', TextType::class, ['label' => false, 'constraints' => $required, 'attr' => ['inputmode' => 'decimal']])
            ->add('max', TextType::class, ['label' => false, 'required' => false, 'constraints' => [new Regex(self::AMOUNT_PATTERN, $message)], 'attr' => ['inputmode' => 'decimal']])
            ->add('amount', TextType::class, ['label' => false, 'constraints' => $required, 'attr' => ['inputmode' => 'decimal']]);
    }
}
