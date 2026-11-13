<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CreditAccount\LiveComponent;

use CreditAccount\CreditAccount;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditBalance;
use CreditAccount\Ledger\InsufficientCreditException;
use CreditAccount\Spending\CreditCartUsage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Helper\FormatService;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Model\Cart;
use Thelia\Model\Currency;
use Thelia\Model\Customer;

/**
 * What the buyer spends of the credit account on the payment step, in the line the theme draws for the module
 * (`checkout.payment.parts`): the balance, the amount already applied, a field to apply an amount and a button to
 * withdraw it. The step then reads the composition of the payment again.
 *
 * The amount typed is an intention: it is sent with the button (or Enter), never on each keystroke, and the server
 * refuses it above the balance read from the database, without cutting it down. The cart and the customer are the
 * ones of the session, never a parameter.
 */
#[AsLiveComponent(name: 'CreditAccount:CheckoutCredit', template: '@CreditAccountModule/theme_hook/checkout-credit.html.twig')]
final class CheckoutCredit
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /**
     * The live event of the checkout of Flexy that makes the payment step read the parts again
     * (FlexyBundle\Event\CheckoutEvents::PAYMENT_PARTS_CHANGED), named here so the module does not depend on the
     * classes of one theme.
     */
    private const PAYMENT_PARTS_CHANGED = 'PAYMENT_PARTS_CHANGED';

    /**
     * At most ten digits before the point, two after it: what the ledger holds as an amount.
     */
    private const AMOUNT_PATTERN = '/^\d{1,10}(?:\.\d{1,2})?$/';

    #[LiveProp(writable: true)]
    public string $amount = '';

    public ?string $error = null;

    public function __construct(
        private readonly CartFacade $cartFacade,
        private readonly SecurityContext $securityContext,
        private readonly CreditBalance $balance,
        private readonly CreditCartUsage $usage,
        private readonly FormatService $format,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getCurrencyId(): int
    {
        // The credit is kept in the default currency, whatever currency the visitor browses in.
        return (int) Currency::getDefaultCurrency()->getId();
    }

    /**
     * The balance read from the database, as a decimal string; zero for a visitor.
     */
    public function getBalance(): string
    {
        $customer = $this->customer();

        return null === $customer ? '0' : $this->balance->of((int) $customer->getId())->toDecimal();
    }

    /**
     * What the buyer chose to spend on the cart, as a decimal string.
     */
    public function getApplied(): string
    {
        $cart = $this->cartOfTheCustomer();

        return null === $cart ? '0' : $this->usage->amountOn($cart)->toDecimal();
    }

    /**
     * @return array<string, string>
     */
    public function getTexts(): array
    {
        return [
            'available' => $this->translator->trans('Available on your credit account', [], CreditAccount::DOMAIN),
            'applied' => $this->translator->trans('Applied to this order', [], CreditAccount::DOMAIN),
            'amount' => $this->translator->trans('Amount to use', [], CreditAccount::DOMAIN),
            'apply' => $this->translator->trans('Apply', [], CreditAccount::DOMAIN),
            'withdraw' => $this->translator->trans('Withdraw', [], CreditAccount::DOMAIN),
        ];
    }

    #[LiveAction]
    public function apply(): void
    {
        $this->error = null;
        $cart = $this->cartOfTheCustomer();

        if (null === $cart) {
            return;
        }

        $typed = str_replace(',', '.', trim($this->amount));

        $amount = 1 === preg_match(self::AMOUNT_PATTERN, $typed) ? CreditAmount::ofDecimal($typed) : null;

        if (null === $amount || !$amount->isPositive()) {
            $this->error = $this->translator->trans('Enter an amount above zero, with at most two decimals.', [], CreditAccount::DOMAIN);

            return;
        }

        try {
            $this->usage->set($cart, $amount);
        } catch (InsufficientCreditException $exception) {
            $this->error = $this->translator->trans(
                'Your credit account only holds %balance%.',
                ['%balance%' => $this->format->money($exception->balance->toDecimal(), $this->getCurrencyId())],
                CreditAccount::DOMAIN,
            );

            return;
        }

        $this->amount = '';
        $this->emit(self::PAYMENT_PARTS_CHANGED);
    }

    #[LiveAction]
    public function withdraw(): void
    {
        $this->error = null;
        $cart = $this->cartOfTheCustomer();

        if (null === $cart) {
            return;
        }

        $this->usage->withdraw($cart);
        $this->amount = '';
        $this->emit(self::PAYMENT_PARTS_CHANGED);
    }

    private function customer(): ?Customer
    {
        $customer = $this->securityContext->hasAuthenticatedCustomerUser() ? $this->securityContext->getCustomerUser() : null;

        return $customer instanceof Customer ? $customer : null;
    }

    /**
     * The cart of the session, when it is the one of the signed-in customer.
     */
    private function cartOfTheCustomer(): ?Cart
    {
        $customer = $this->customer();

        if (null === $customer) {
            return null;
        }

        $cart = $this->cartFacade->getOrCreateFromSession();

        return (int) $cart->getCustomerId() === (int) $customer->getId() ? $cart : null;
    }
}
