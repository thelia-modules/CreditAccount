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

use CreditAccount\CreditAccount;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Admin;

/**
 * What the administrator of the request may do on the screens of the module.
 */
final readonly class AdminGate
{
    private const ADMIN_ROLE = 'ADMIN';

    public function __construct(private SecurityContext $securityContext)
    {
    }

    public function canConfigure(): bool
    {
        return $this->securityContext->isGranted([self::ADMIN_ROLE], [AdminResources::MODULE], ['CreditAccount'], [AccessManager::UPDATE]);
    }

    public function canWriteManualMovements(): bool
    {
        return $this->securityContext->isGranted([self::ADMIN_ROLE], [CreditAccount::MANUAL_CREDIT_RESOURCE], [], [AccessManager::CREATE]);
    }

    /**
     * Null when the administrator may configure the module, else the answer to send: the login page when nobody
     * is signed in, a 403 when the signed-in administrator lacks the right.
     */
    public function refuseUnlessConfigurer(): ?Response
    {
        return $this->canConfigure() ? null : $this->refusal();
    }

    public function refuseUnlessManualWriter(): ?Response
    {
        return $this->canWriteManualMovements() ? null : $this->refusal();
    }

    public function admin(): ?Admin
    {
        $admin = $this->securityContext->getAdminUser();

        return $admin instanceof Admin ? $admin : null;
    }

    private function refusal(): Response
    {
        if (!$this->securityContext->hasAdminUser()) {
            return new RedirectResponse('/admin/login');
        }

        return new Response('Forbidden', Response::HTTP_FORBIDDEN);
    }
}
