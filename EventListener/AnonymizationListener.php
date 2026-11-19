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

namespace CreditAccount\EventListener;

use CreditAccount\Privacy\CreditDetacher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Detaches the credit journal from a customer being anonymized. The personal data provider does the same inside
 * the transaction of the core anonymizer; this one keeps the guarantee when the event is dispatched without it.
 */
final readonly class AnonymizationListener
{
    public function __construct(private CreditDetacher $detacher)
    {
    }

    #[AsEventListener(event: TheliaEvents::CUSTOMER_ANONYMIZE)]
    public function onCustomerAnonymize(CustomerAnonymizeEvent $event): void
    {
        $this->detacher->detach((int) $event->getCustomer()->getId());
    }
}
