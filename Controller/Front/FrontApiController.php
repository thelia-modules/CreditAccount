<?php

declare(strict_types=1);

namespace CreditAccount\Controller\Front;

use CreditAccount\CreditAccountManager;
use CreditAccount\Model\CreditAccountQuery;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;

#[Route('/open_api/creditaccount', name: '')]
class FrontApiController extends BaseFrontController
{
    #[Route('/getAmount', name: 'getAmount', methods: ['GET'])]
    public function getLoyaltyAmout(EventDispatcherInterface $eventDispatcher, SecurityContext $securityContext)
    {
        try {
            $this->checkAuth();
            $this->checkCartNotEmpty($eventDispatcher);

        } catch (\Exception $e) {
            throw new \Exception(Translator::getInstance()->trans('Customer isn\'t logged in or cart is empty'));
        }

        return new JsonResponse(['amount' => $this->getAmount($securityContext)]);
    }

    #[Route('/useCredit', name: 'useCredit', methods: ['POST'])]
    public function useCredit(EventDispatcherInterface $eventDispatcher, SecurityContext $securityContext, CreditAccountManager $creditAccountManager)
    {
        try {
            $this->checkAuth();
            $this->checkCartNotEmpty($eventDispatcher);
        } catch (\Exception $e) {
            throw new \Exception(Translator::getInstance()->trans('Customer isn\'t logged in or cart is empty'));
        }
        $amount = json_decode($this->getRequest()->getContent(), true)['amount'] ?? null;

        if (null === $amount = $this->verifyAmount($amount)) {
            return new JsonResponse([]);
        }

        $amountAvailable = $this->getAmount($securityContext);

        if ($amount > $amountAvailable) {
            return new JsonResponse(['error' => 'Amount too high. Your credit amount is: '.$amountAvailable]);
        }

        $creditAccountManager->applyCreditDiscountInCartAndOrder($amount);

        return new JsonResponse([]);
    }

    protected function getAmount(SecurityContext $securityContext)
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

    protected function verifyAmount(mixed $amount): null | float
    {
        if (!is_scalar($amount)) {
            return null;
        }

        $amount = str_replace(',', '.', (string) $amount);

        if (!is_numeric($amount)) {
            return null;
        }
        
        return (float)$amount;
    }
}
