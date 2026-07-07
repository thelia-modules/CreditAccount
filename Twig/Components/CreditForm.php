<?php

declare(strict_types=1);

namespace CreditAccount\Twig\Components;

use CreditAccount\CreditAccountManager;
use CreditAccount\Model\CreditAccountQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Domain\Customer\CustomerFacade;
use Thelia\Model\CurrencyQuery;

#[AsLiveComponent(name: 'CreditAccount:CreditForm', template: '@CreditAccountModule/components/CreditForm.html.twig')]
class CreditForm
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: true)]
    public ?string $amount = null;

    public function __construct(
        private readonly CreditAccountManager $creditAccountManager,
        private readonly CartFacade $cartFacade,
        private readonly CustomerFacade $customerFacade,
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getAvailableCredit(): float
    {
        $customer = $this->customerFacade->getCurrentCustomer();
        if (null === $customer) {
            return 0.0;
        }

        $creditAccount = CreditAccountQuery::create()->findOneByCustomerId($customer->getId());

        return null !== $creditAccount ? (float) $creditAccount->getAmount() : 0.0;
    }

    public function getAppliedCredit(): float
    {
        $session = $this->requestStack->getSession();

        return (float) $this->creditAccountManager->getDiscount($session);
    }

    public function getCurrencySymbol(): string
    {
        return CurrencyQuery::create()->filterByByDefault(1)->findOne()?->getSymbol() ?? '';
    }

    #[LiveAction]
    public function apply(): void
    {
        if (!$this->customerFacade->isLoggedIn()) {
            return;
        }

        $wanted = (float) str_replace(',', '.', (string) $this->amount);
        $available = $this->getAvailableCredit();

        if ($wanted <= 0 || $available <= 0) {
            return;
        }

        $this->creditAccountManager->applyCreditDiscountInCartAndOrder(min($wanted, $available));
        $this->cartFacade->recalculatePostage($this->cartFacade->getOrCreateFromSession());

        $this->emit('syncSummary');
    }

    #[LiveAction]
    public function remove(): void
    {
        $this->creditAccountManager->removeCreditDiscountFromCartAndOrder(
            $this->requestStack->getSession(),
            $this->eventDispatcher
        );
        $this->cartFacade->recalculatePostage($this->cartFacade->getOrCreateFromSession());

        $this->emit('syncSummary');
    }
}
