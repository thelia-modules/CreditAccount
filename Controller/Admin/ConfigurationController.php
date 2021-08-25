<?php

namespace CreditAccount\Controller\Admin;

use CreditAccount\CreditAccount;
use CreditAccount\Form\ConfigurationForm;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin/module/CreditAccount", name="creditAccount_configuration")
 */
class ConfigurationController extends BaseAdminController
{
    /**
     * @Route("", name="_view", methods="GET")
     */
    public function viewAction()
    {
        return $this->render(
            "creditaccount/configuration",
            [

            ]
        );
    }

    /**
     * @Route("/save", name="_save", methods="POST")
     */
    public function saveAction()
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'CreditAccount', AccessManager::VIEW)) {
            return $response;
        }

        $form = $this->createForm(ConfigurationForm::getName());

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
