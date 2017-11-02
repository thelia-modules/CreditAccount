<?php

namespace CreditAccount\Form;

use CreditAccount\CreditAccount;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class ConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                "expiration_enabled",
                'choice',
                [
                    "data" => CreditAccount::getConfigValue("expiration_enabled", "false"),
                    "label"=>Translator::getInstance()->trans("Enable expiration", [], CreditAccount::DOMAIN),
                    "label_attr" => ["for" => "expiration_enabled"],
                    "required" => false,
                    'choices'  => [
                        'true' => Translator::getInstance()->trans("Yes", [], CreditAccount::DOMAIN),
                        'false' => Translator::getInstance()->trans("No", [], CreditAccount::DOMAIN)
                    ]
                ]
            )
            ->add(
                "expiration_delay",
                "number",
                [
                    "data" => CreditAccount::getConfigValue("expiration_delay", 18),
                    "label"=>Translator::getInstance()->trans("Expiration delay (in months)", [], CreditAccount::DOMAIN),
                    "label_attr" => ["for" => "expiration_delay"],
                    "required" => false
                ]
            )
        ;
    }

    public function getName()
    {
        return "creditaccount_configuration_form";
    }
}
