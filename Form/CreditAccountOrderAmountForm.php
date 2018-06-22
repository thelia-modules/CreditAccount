<?php
/**
 * Created by PhpStorm.
 * User: audreymartel
 * Date: 21/06/2018
 * Time: 09:42
 */

namespace CreditAccount\Form;


use Symfony\Component\Validator\Constraints;
use Thelia\Form\BaseForm;

class CreditAccountOrderAmountForm  extends BaseForm
{

    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "order-credit-account-amount",
                "text",
                [
                    "required"    => true,
                    "constraints" => [
                        new Constraints\NotBlank()
                    ]
                ]
            )
        ;
    }

    public function getName()
    {
        return "credit_account_order_amount";
    }
}