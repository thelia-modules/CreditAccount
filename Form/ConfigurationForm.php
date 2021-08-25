<?php

namespace CreditAccount\Form;

use CreditAccount\CreditAccount;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class ConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "expiration_enabled",
                ChoiceType::class,
                [
                    "data" => CreditAccount::getConfigValue("expiration_enabled", "false"),
                    "label"=>Translator::getInstance()->trans("Enable expiration", [], CreditAccount::DOMAIN),
                    "label_attr" => ["for" => "expiration_enabled"],
                    "required" => false,
                    'choices'  => [
                        Translator::getInstance()->trans("Yes", [], CreditAccount::DOMAIN) => 'true',
                        Translator::getInstance()->trans("No", [], CreditAccount::DOMAIN) => 'false'
                    ]
                ]
            )
            ->add(
                "expiration_delay",
                NumberType::class,
                [
                    "data" => CreditAccount::getConfigValue("expiration_delay", 18),
                    "label"=>Translator::getInstance()->trans("Expiration delay (in months)", [], CreditAccount::DOMAIN),
                    "label_attr" => ["for" => "expiration_delay"],
                    "required" => false
                ]
            )
        ;
    }

    public static function getName()
    {
        return "creditaccount_configuration_form";
    }
}
