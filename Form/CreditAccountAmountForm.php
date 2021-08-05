<?php
/**
 * Created by PhpStorm.
 * User: audreymartel
 * Date: 21/06/2018
 * Time: 09:42
 */

namespace CreditAccount\Form;


use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints;
use Thelia\Form\BaseForm;

class CreditAccountAmountForm  extends BaseForm
{

    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "credit-account-amount",
                TextType::class,
                [
                    "required"    => true,
                    "constraints" => [
                        new Constraints\NotBlank()
                    ]
                ]
            )
            ->add(
                "credit-account-force",
                CheckboxType::class,
                //if force setting credit in case there is a
                // non cumulative promo code, set to true
                ['required' => false]
            )
        ;
    }

    public static function getName()
    {
        return "credit_account_order_amount";
    }
}