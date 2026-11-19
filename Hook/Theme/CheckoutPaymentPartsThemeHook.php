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

namespace CreditAccount\Hook\Theme;

use CreditAccount\CreditAccount;
use CreditAccount\Front\PrivateResponseListener;
use CreditAccount\Ledger\CreditBalance;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Customer;
use Twig\Environment;

/**
 * The line of the credit account in the "Pay part of your order with" block of the payment step. The theme calls
 * the hook once per means that pays inside the shop, always under the same name: only the call made for this module
 * is answered, to a signed-in customer who holds credit.
 */
final readonly class CheckoutPaymentPartsThemeHook implements ThemeHookInterface
{
    public const HOOK = 'checkout.payment.parts';

    public function __construct(
        private Environment $twig,
        private SecurityContext $securityContext,
        private RequestStack $requestStack,
        private CreditBalance $balance,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return self::HOOK === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        if ((int) ($parameters['module'] ?? 0) !== CreditAccount::getModuleId()) {
            return '';
        }

        $customer = $this->securityContext->hasAuthenticatedCustomerUser() ? $this->securityContext->getCustomerUser() : null;

        if (!$customer instanceof Customer || !$this->balance->of((int) $customer->getId())->isPositive()) {
            return '';
        }

        // The page carries the balance of a person: the response is marked private for the listener.
        $this->requestStack->getMainRequest()?->attributes->set(PrivateResponseListener::ATTRIBUTE, true);

        return $this->twig->render('@CreditAccountModule/theme_hook/checkout-payment-part.html.twig');
    }
}
