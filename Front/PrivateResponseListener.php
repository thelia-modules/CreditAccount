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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A page that shows a balance is the customer's alone: a shared cache never keeps it.
 */
final readonly class PrivateResponseListener implements EventSubscriberInterface
{
    public const ATTRIBUTE = '_credit_account_private';

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -128]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || true !== $event->getRequest()->attributes->get(self::ATTRIBUTE)) {
            return;
        }

        $response = $event->getResponse();
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store', true);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->remove('Expires');
        $response->setVary(['Cookie'], false);
    }
}
