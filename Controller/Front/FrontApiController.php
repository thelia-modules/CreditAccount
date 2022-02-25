<?php

namespace CreditAccount\Controller\Front;

use CreditAccount\CreditAccountManager;
use CreditAccount\Model\CreditAccountQuery;
use OpenApi\Annotations as OA;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Controller\Front\BaseFrontOpenApiController;
use Thelia\Core\HttpFoundation\JsonResponse;
use OpenApi\Service\OpenApiService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;

/**
 * @Route("/open_api/creditaccount", name="")
 */
class FrontApiController extends BaseFrontOpenApiController
{
    /**
     * @Route("/getAmount", name="getAmount",  methods="GET")
     *
     * @OA\Get(
     *     path="/creditaccount/getAmount",
     *     tags={"creditaccount"},
     *     summary="Get the loyalty credit account for this customer",
     *     @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(
     *                 @OA\Schema(
     *                  @OA\Property(
     *                      property="amount",
     *                      type="number",
     *                      format="float",
     *                 ),
     *             ))
     *     ),
     * )
     *
     */
    public function getLoyaltyAmout(EventDispatcherInterface $eventDispatcher, SecurityContext $securityContext)
    {
        try {
            $this->checkAuth();
            $this->checkCartNotEmpty($eventDispatcher);

        } catch (\Exception $e) {
            throw new \Exception(Translator::getInstance()->trans('Customer isn\'t logged in or cart is empty'));
        }

        return OpenApiService::jsonResponse(
            [
                'amount' => $this->getAmout($securityContext),
            ]
        );
    }

    /**
     * @Route("/useCredit", name="useCredit")
     *
     * @OA\Post(
     *     path="/creditaccount/useCredit",
     *     tags={"creditaccount"},
     *     summary="Use the credit passed in parameters and decrease the cart amount",
     *     @OA\RequestBody(
     *          @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="amount",
     *                     type="number",
     *                     format="float",
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *          response="200",
     *          description="Success",
     *     )
     * )
     *
     */
    public function useCredit(EventDispatcherInterface $eventDispatcher,  OpenApiService $openApiService, SecurityContext $securityContext, CreditAccountManager $creditAccountManager)
    {
        try {
            $this->checkAuth();
            $this->checkCartNotEmpty($eventDispatcher);
        } catch (\Exception $e) {
            throw new \Exception(Translator::getInstance()->trans('Customer isn\'t logged in or cart is empty'));
        }
        $amount = $openApiService->getRequestValue("amount");
        $amountAvailable = $this->getAmout($securityContext);

        if ($amount > $amountAvailable) {
            return $this->jsonResponse(
                [
                    'error' => "Amount too high. You credit amount is : ".$amount
                ]
            );
        }

        $creditAccountManager->applyCreditDiscountInCartAndOrder($amount);

        return new JsonResponse([]);
    }

    protected function getAmout(SecurityContext $securityContext)
    {
        $customer = $securityContext->getCustomerUser();
        $creditAccount = CreditAccountQuery::create()
            ->findOneByCustomerId($customer->getId());
        $amount = 0;
        if($creditAccount !== null) {
            $amount = $creditAccount->getAmount();
        }
        return $amount;
    }
}
