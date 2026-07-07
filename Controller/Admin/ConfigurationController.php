<?php

declare(strict_types=1);

namespace CreditAccount\Controller\Admin;

use CreditAccount\CreditAccount;
use CreditAccount\Form\ConfigurationForm;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

class ConfigurationController extends BaseAdminController
{
    #[Route('/admin/module/CreditAccount/save', name: 'creditaccount_config_save', methods: ['POST'])]
    public function saveAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], ['CreditAccount'], AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(ConfigurationForm::getName());

        try {
            $data = $this->validateForm($form)->getData();

            $excludeData = ['success_url', 'error_url', 'error_message'];

            foreach ($data as $key => $value) {
                if (!\in_array($key, $excludeData, true)) {
                    CreditAccount::setConfigValue($key, (string) $value);
                }
            }
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans('Error', [], CreditAccount::DOMAIN),
                $e->getMessage(),
                $form
            );
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/CreditAccount'));
    }
}
