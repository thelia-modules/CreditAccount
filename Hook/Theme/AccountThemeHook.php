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
use CreditAccount\Front\CreditHistory;
use CreditAccount\Front\PrivateResponseListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Customer;
use Twig\Environment;

/**
 * The balance and the history of the credit account, at the bottom of the customer account page of the theme.
 * The customer is the one of the session: nothing the request carries picks whose movements are shown.
 */
final readonly class AccountThemeHook implements ThemeHookInterface
{
    public const HOOK = 'account.bottom';

    public const PAGE_PARAMETER = 'credit_page';

    public function __construct(
        private Environment $twig,
        private SecurityContext $securityContext,
        private RequestStack $requestStack,
        private CreditHistory $history,
        private TranslatorInterface $translator,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return self::HOOK === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        $customer = $this->securityContext->getCustomerUser();
        $request = $this->requestStack->getMainRequest();

        if (!$customer instanceof Customer || null === $request) {
            return '';
        }

        // The page carries the balance of a person: the response is marked private for the listener.
        $request->attributes->set(PrivateResponseListener::ATTRIBUTE, true);

        $history = $this->history->of($customer->getId(), max(1, (int) $request->query->get(self::PAGE_PARAMETER, 1)));
        $pageUrl = static fn (int $page): string => $request->getBaseUrl().$request->getPathInfo().'?'
            .http_build_query([...$request->query->all(), self::PAGE_PARAMETER => $page]).'#credit-account';
        $domain = CreditAccount::DOMAIN;

        return $this->twig->render('@CreditAccountModule/theme_hook/account.html.twig', [
            ...$history,
            'previousUrl' => $history['page'] > 1 ? $pageUrl($history['page'] - 1) : null,
            'nextUrl' => $history['page'] < $history['pages'] ? $pageUrl($history['page'] + 1) : null,
            'labels' => [
                'title' => $this->translator->trans('My credit account', [], $domain),
                'balance' => $this->translator->trans('Balance', [], $domain),
                'history' => $this->translator->trans('History', [], $domain),
                'date' => $this->translator->trans('Date', [], $domain),
                'type' => $this->translator->trans('Type', [], $domain),
                'reason' => $this->translator->trans('Reason', [], $domain),
                'amount' => $this->translator->trans('Amount', [], $domain),
                'empty' => $this->translator->trans('No movement on your credit account yet.', [], $domain),
                'previous' => $this->translator->trans('Previous page', [], $domain),
                'next' => $this->translator->trans('Next page', [], $domain),
                'pages' => $this->translator->trans('Page %page% of %pages%', ['%page%' => $history['page'], '%pages%' => $history['pages']], $domain),
                'navigation' => $this->translator->trans('Credit history pages', [], $domain),
            ],
        ]);
    }
}
