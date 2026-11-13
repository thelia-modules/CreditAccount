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

namespace CreditAccount\BackOffice;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * What a screen of the module tells the administrator after a gesture: the success and error messages go to the
 * flash bag, which the back-office shows at the top of the next page. A refused form is kept as it was typed, so
 * the page can show it again with its errors.
 */
final readonly class AdminFlash
{
    private const REJECTED = 'creditaccount.rejected.';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function success(string $message): void
    {
        $this->flashBag()?->add('success', $message);
    }

    public function error(string $message): void
    {
        $this->flashBag()?->add('error', $message);
    }

    /**
     * @param array<string, mixed> $submitted
     */
    public function rejected(string $formName, array $submitted): void
    {
        $this->requestStack->getCurrentRequest()?->getSession()->set(self::REJECTED.$formName, $submitted);
    }

    /**
     * The form refused by the last gesture, once: the next call finds nothing.
     *
     * @return array<string, mixed>|null
     */
    public function takeRejected(string $formName): ?array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return null;
        }

        $submitted = $request->getSession()->remove(self::REJECTED.$formName);

        return \is_array($submitted) ? $submitted : null;
    }

    private function flashBag(): ?FlashBagInterface
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();

        return $session instanceof FlashBagAwareSessionInterface ? $session->getFlashBag() : null;
    }
}
