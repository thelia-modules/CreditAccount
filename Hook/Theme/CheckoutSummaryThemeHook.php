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
use CreditAccount\Front\CheckoutSummaryParts;
use CreditAccount\Front\PrivateResponseListener;
use CreditAccount\Front\ThemeMoney;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Cart\CartFacade;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Twig\Environment;

/**
 * The lines the credit adds under the total of the checkout summary: what the credit pays, and what is left to pay.
 * The total itself does not change: the credit is a part of the payment, not a discount.
 */
final readonly class CheckoutSummaryThemeHook implements ThemeHookInterface
{
    public const HOOK = 'checkout.summary.bottom';

    public function __construct(
        private Environment $twig,
        private SecurityContext $securityContext,
        private RequestStack $requestStack,
        private CartFacade $cartFacade,
        private CheckoutSummaryParts $parts,
        private TranslatorInterface $translator,
        private ThemeMoney $money,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return self::HOOK === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        $customer = $this->securityContext->hasAuthenticatedCustomerUser() ? $this->securityContext->getCustomerUser() : null;
        $total = $parameters['summary']['total_taxed_price'] ?? null;

        if (!$customer instanceof Customer || !is_numeric($total)) {
            return '';
        }

        $cart = $this->cartFacade->getOrCreateFromSession();

        if ((int) $cart->getCustomerId() !== (int) $customer->getId()) {
            return '';
        }

        $parts = $this->parts->of($cart, (string) $total);

        if (null === $parts) {
            return '';
        }

        $this->requestStack->getMainRequest()?->attributes->set(PrivateResponseListener::ATTRIBUTE, true);

        $currency = $cart->getCurrency()?->getCode() ?: Currency::getDefaultCurrency()->getCode();

        return $this->twig->render('@CreditAccountModule/theme_hook/checkout-summary.html.twig', [
            'credit' => $this->money->format($parts['credit'], $currency),
            'left' => $this->money->format($parts['left'], $currency),
            'texts' => [
                'credit' => $this->translator->trans('Paid with your credit account', [], CreditAccount::DOMAIN),
                'left' => $this->translator->trans('Left to pay', [], CreditAccount::DOMAIN),
            ],
        ]);
    }
}
