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

namespace CreditAccount\Front;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Lang;

/**
 * Money written the way the front theme writes its own amounts (Flexy: the Twig filter format_currency, in the
 * language of the visitor): €1,234.50 in English, 1 234,50 € in French. The blocks of the module sit next to the
 * amounts of the theme, and must not read differently.
 */
final readonly class ThemeMoney
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function format(string $amount, string $currencyCode): string
    {
        return (new \NumberFormatter($this->locale(), \NumberFormatter::CURRENCY))->formatCurrency((float) $amount, $currencyCode);
    }

    private function locale(): string
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        return $session instanceof Session ? $session->getLang()->getLocale() : Lang::getDefaultLanguage()->getLocale();
    }
}
