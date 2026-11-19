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

use CreditAccount\CreditAccount;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Domain\Payment\DTO\InstantShare;
use Thelia\Domain\Payment\OrderPaymentSplitter;
use Thelia\Log\Tlog;
use Thelia\Model\Cart;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\InstantPaymentModuleInterface;

/**
 * What the summary of the checkout says under its total: the part the credit pays, and what is left to pay once
 * every means that pays inside the shop took its part (the credit, a credit note...).
 *
 * The parts are split as the core splits them when the order is placed (OrderPaymentSplitter: in the order of the
 * modules, each part capped at what is still due), with no provider behind them: what is left is what the buyer
 * pays with the means chosen next, whichever it is. The summary is drawn before that means is chosen.
 */
final readonly class CheckoutSummaryParts
{
    public function __construct(
        #[Autowire(service: 'service_container')]
        private ContainerInterface $container,
        private OrderPaymentSplitter $splitter = new OrderPaymentSplitter(),
    ) {
    }

    /**
     * @return array{credit: string, left: string}|null decimal strings, null when the credit pays nothing
     */
    public function of(Cart $cart, string $total): ?array
    {
        $shares = [];

        foreach (ModuleQuery::create()->filterByActivate(BaseModule::IS_ACTIVATED)->filterByType(BaseModule::PAYMENT_MODULE_TYPE)->orderByPosition()->find() as $module) {
            try {
                $instance = $module->getPaymentModuleInstance($this->container);
                $applied = $instance instanceof InstantPaymentModuleInterface ? trim($instance->getAmountAppliedToCart($cart)) : '0';
            } catch (\Throwable $throwable) {
                Tlog::getInstance()->warning(\sprintf('CreditAccount: payment module %d could not tell what it applied to the cart (%s); the summary leaves it out.', (int) $module->getId(), $throwable::class));

                continue;
            }

            if (1 === preg_match('/^\d+(?:\.\d+)?$/', $applied) && (float) $applied > 0) {
                $shares[] = new InstantShare((int) $module->getId(), $applied);
            }
        }

        // A provider that charges the balance stands behind the parts, so the split never refuses a rest.
        $composition = $this->splitter->split($total, $shares, 0, true);

        foreach ($composition->parts as $part) {
            if ($part->isInstant && CreditAccount::getModuleId() === $part->moduleId && (float) $part->amount > 0) {
                return ['credit' => $part->amount, 'left' => $composition->balance];
            }
        }

        return null;
    }
}
