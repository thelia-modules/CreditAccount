<?php

namespace CreditAccount\Controller\Admin;

use CreditAccount\CreditAccount;
use CreditAccount\Form\ConfigurationForm;
use CreditAccount\Model\CreditAccountQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\URL;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Model\Map\CustomerTableMap;
use CreditAccount\Model\Map\CreditAccountTableMap;

/**
 * @Route("/admin/module/CreditAccount", name="creditAccount")
 */
class ConfigurationController extends BaseAdminController
{
    /**
     * @Route("", name="_view")
     */
    public function listAction(Request $request, EventDispatcherInterface $eventDispatcher)
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'CreditAccount', AccessManager::VIEW)) {
            return $response;
        }

        if ($request->isXmlHttpRequest()) {
            $query = CreditAccountQuery::create();
            $this->applyFilters($request, $query);

            // Apply ordering
            $this->applyOrder($request, $query);

            $queryCount = clone $query;

            $query->offset($this->getOffset($request))
                ->limit($this->getLength($request));

            $creditAccounts = $query->find();

            $json = [
                "draw" => $this->getDraw($request),
                "recordsTotal" => $queryCount->count(),
                "recordsFiltered" => $queryCount->count(),
                "data" => [],
            ];

            $moneyFormat = MoneyFormat::getInstance($request);

            foreach ($creditAccounts as $creditAccount) {
                $customer = $creditAccount->getCustomer();

                if ($customer !== null) {
                    $updateUrl = URL::getInstance()->absoluteUrl('admin/customer/update?customer_id=' . $customer->getId());

                    $json['data'][] = [
                        'credit_account_id' => $creditAccount->getId(),
                        'lastname' => $customer->getLastname(),
                        'firstname' => $customer->getFirstname(),
                        'email' => $customer->getEmail(),
                        'balance' => $moneyFormat->formatByCurrency($creditAccount->getAmount(), 2, '.', ' '),
                        'actions' => [
                            'hrefUpdate' => $updateUrl,
                        ]
                    ];
                }
            }

            return new JsonResponse($json);
        }

        return $this->render(
            "creditaccount/configuration",
            [
                'columnsDefinition' => $this->defineColumnsDefinition()
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
            return $this->viewAction(new Request());
        }

        return $this->generateSuccessRedirect($form);
    }

    protected function applyFilters(Request $request, $query)
    {
        $filters = $request->get('filter');

        if (!empty($filters['email'])) {
            $query->useCustomerQuery()
                ->filterByEmail('%' . $filters['email'] . '%', Criteria::LIKE)
                ->endUse();
        }

        if (!empty($filters['lastname'])) {
            $query->useCustomerQuery()
                ->filterByLastname('%' . $filters['lastname'] . '%', Criteria::LIKE)
                ->endUse();
        }

        if (!empty($filters['firstname'])) {
            $query->useCustomerQuery()
                ->filterByFirstname('%' . $filters['firstname'] . '%', Criteria::LIKE)
                ->endUse();
        }

        if (!empty($filters['amount_min'])) {
            $query->filterByAmount($filters['amount_min'], Criteria::GREATER_EQUAL);
        }

        if (!empty($filters['amount_max'])) {
            $query->filterByAmount($filters['amount_max'], Criteria::LESS_EQUAL);
        }
    }

    protected function getOrderColumnName(Request $request)
    {
        $columnDefinition = $this->defineColumnsDefinition(true)[
        (int) $request->get('order')[0]['column']
        ];

        return $columnDefinition['orm'];
    }

    protected function applyOrder(Request $request, CreditAccountQuery $query)
    {
        $columnName = $this->getOrderColumnName($request);
        $orderDir = $this->getOrderDir($request);

        if ($columnName === CreditAccountTableMap::COL_ID) {
            $query->orderById($orderDir);
        } else {
            $query->useCustomerQuery()->orderBy($columnName, $orderDir)->endUse();
        }
    }

    protected function getOrderDir(Request $request)
    {
        return (string) $request->get('order')[0]['dir'] === 'asc' ? Criteria::ASC : Criteria::DESC;
    }

    protected function getLength(Request $request)
    {
        return (int) $request->get('length');
    }

    protected function getOffset(Request $request)
    {
        return (int) $request->get('start');
    }

    protected function getDraw(Request $request)
    {
        return (int) $request->get('draw');
    }

    protected function defineColumnsDefinition($withPrivateData = false)
    {
        $i = -1;

        $definitions = [
            [
                'name' => 'credit_account_id',
                'targets' => ++$i,
                'title' => 'ID',
                'orm' => CreditAccountTableMap::COL_ID,
                'orderable' => true,
                'searchable' => false,
            ],
            [
                'name' => 'lastname',
                'targets' => ++$i,
                'title' => 'Nom',
                'orm' => CustomerTableMap::COL_LASTNAME,
            ],
            [
                'name' => 'firstname',
                'targets' => ++$i,
                'title' => 'Prénom',
                'orm' => CustomerTableMap::COL_FIRSTNAME,
            ],
            [
                'name' => 'email',
                'targets' => ++$i,
                'title' => 'Email',
                'orm' => CustomerTableMap::COL_EMAIL,
            ],
            [
                'name' => 'balance',
                'targets' => ++$i,
                'title' => 'Solde',
                'orderable' => false,
            ],
            [
                'name' => 'actions',
                'targets' => ++$i,
                'title' => 'Action',
                'orderable' => false,
            ]
        ];

        if (!$withPrivateData) {
            foreach ($definitions as &$definition) {
                unset($definition['orm']);
            }
        }

        return $definitions;
    }

    protected function getSearchValue(Request $request, $searchKey)
    {
        return (string) $request->get($searchKey)['value'];
    }
}