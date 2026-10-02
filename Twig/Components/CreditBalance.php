<?php

declare(strict_types=1);

namespace CreditAccount\Twig\Components;

use CreditAccount\Model\CreditAccountQuery;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Thelia\Domain\Customer\CustomerFacade;
use Thelia\Model\CurrencyQuery;

#[AsTwigComponent(name: 'CreditAccount:Balance', template: '@CreditAccountModule/components/CreditBalance.html.twig')]
final class CreditBalance
{
    public function __construct(
        private readonly CustomerFacade $customerFacade,
    ) {
    }

    public function getBalance(): float
    {
        $customer = $this->customerFacade->getCurrentCustomer();
        if (null === $customer) {
            return 0.0;
        }

        $creditAccount = CreditAccountQuery::create()->findOneByCustomerId($customer->getId());

        return null !== $creditAccount ? (float) $creditAccount->getAmount() : 0.0;
    }

    public function hasCredit(): bool
    {
        return $this->getBalance() > 0;
    }

    public function getCurrencySymbol(): string
    {
        return CurrencyQuery::create()->filterByByDefault(1)->findOne()?->getSymbol() ?? '';
    }
}
