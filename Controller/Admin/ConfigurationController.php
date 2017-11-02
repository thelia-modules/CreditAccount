<?php

namespace CreditAccount\Controller\Admin;

use CreditAccount\CreditAccount;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;

class ConfigurationController extends BaseAdminController
{
    public function viewAction()
    {
        return $this->render(
            "creditaccount/configuration",
            [

            ]
        );
    }

    public function saveAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'CreditAccount', AccessManager::VIEW)) {
            return $response;
        }

        $form = $this->createForm("creditaccount_configuration_form");

        try {
            $data = $this->validateForm($form)->getData();

            $excludeData = [
                'success_url',
                'error_url',
                'error_message',
            ];

            foreach ($data as $key => $value) {
                if (!in_array($key, $excludeData)) {
                    CreditAccount::setConfigValue($key, $value);
                }
            }
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans(
                    "Error",
                    [],
                    CreditAccount::DOMAIN
                ),
                $e->getMessage(),
                $form
            );
            return $this->viewAction();
        }

        return $this->generateSuccessRedirect($form);
    }
}
