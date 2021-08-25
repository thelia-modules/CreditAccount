<?php

namespace CreditAccount\Controller\Front;

use CreditAccount\CreditAccountManager;
use CreditAccount\Model\CreditAccountQuery;
use OpenApi\Annotations as OA;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Controller\Front\BaseFrontOpenApiController;
use Thelia\Core\HttpFoundation\JsonResponse;

/**
 * @Route("/creditaccount", name="creditaccount_api")
 */
class FrontApiController extends BaseFrontOpenApiController
{
    /**
     * @Route("/getAmount", name="getAmount")
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
    public function getLoyaltyAmout()
    {
        try {
            $this->checkAuth();
            $this->checkCartNotEmpty();
        } catch (\Exception $e) {
            return $this->jsonResponse(
                [
                    'error' => "Customer isn't logged in or cart is not empty",
                    'message' => $e->getMessage()
                ]
            );
        }
        return $this->jsonResponse(
            [
                'amount' => $this->getAmout(),
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
    public function useCredit()
    {
        try {
            $this->checkAuth();
            $this->checkCartNotEmpty();
        } catch (\Exception $e) {
            return $this->jsonResponse(
                [
                    'error' => "Customer isn't logged in or cart is not empty",
                    'message' => $e->getMessage()
                ]
            );
        }
        $amount = $this->getRequestValue("amount");
        $amountAvailable = $this->getAmout();
        $amountLabel = money_format("%n", $amountAvailable);

        if ($amount > $amountAvailable) {
            return $this->jsonResponse(
                [
                    'error' => "Amount too high. You credit amount is : ".$amountLabel
                ]
            );
        }

        /** @var CreditAccountManager $creditAccountManager */
        $creditAccountManager = $this->container->get('creditaccount.manager');
        $creditAccountManager->applyCreditDiscountInCartAndOrder($amount, $this->getSession(), $this->getDispatcher());

        return new JsonResponse([]);
    }

    protected function getAmout()
    {
        $customer = $this->getSecurityContext()->getCustomerUser();
        $creditAccount = CreditAccountQuery::create()
            ->findOneByCustomerId($customer->getId());
        $amount = 0;
        if($creditAccount !== null) {
            $amount = $creditAccount->getAmount();
        }
        return $amount;
    }
}
