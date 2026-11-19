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

namespace CreditAccount\Loyalty;

use CreditAccount\CreditAccount;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Model\CreditLoyaltyExcludedCategoryQuery;
use CreditAccount\Model\CreditLoyaltyExcludedProductQuery;
use Thelia\Domain\Payment\Enum\OrderPaymentPartState;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderPaymentPartQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductQuery;

/**
 * What an order earns on: the lines tax included, without the excluded ones, less their share of the order
 * discount (promotions, then the credit the buyer paid the order with, settled on its payment parts), postage
 * left out. A product is excluded on its own, or through one
 * of its categories or a parent of one.
 */
final readonly class EligibleAmount
{
    public function of(Order $order): CreditAmount
    {
        $excludedProducts = array_map('intval', CreditLoyaltyExcludedProductQuery::create()->select(['ProductId'])->find()->getData());
        $excludedCategories = array_map('intval', CreditLoyaltyExcludedCategoryQuery::create()->select(['CategoryId'])->find()->getData());

        $goods = 0.0;
        $eligible = 0.0;

        foreach (OrderProductQuery::create()->filterByOrderId($order->getId())->find() as $line) {
            $taxed = $this->taxedTotalOf($line);
            $goods += $taxed;

            if (!$this->isExcluded((string) $line->getProductRef(), $excludedProducts, $excludedCategories)) {
                $eligible += $taxed;
            }
        }

        if ($goods <= 0.0 || $eligible <= 0.0) {
            return CreditAmount::zero();
        }

        $discount = min(max(0.0, (float) $order->getDiscount()) + $this->creditSpentOn($order), $goods);

        return CreditAmount::ofFloat($eligible * (1 - $discount / $goods));
    }

    /**
     * What the credit paid of the order: its settled payment parts. A part given back, or not settled yet, paid
     * nothing.
     */
    private function creditSpentOn(Order $order): float
    {
        $spent = OrderPaymentPartQuery::create()
            ->filterByOrderId($order->getId())
            ->filterByPaymentModuleId(CreditAccount::getModuleId())
            ->filterByState(OrderPaymentPartState::SETTLED->value)
            ->withColumn('SUM(order_payment_part.amount)', 'Spent')
            ->select(['Spent'])
            ->findOne();

        return (float) $spent;
    }

    private function taxedTotalOf(OrderProduct $line): float
    {
        $inPromo = 1 === (int) $line->getWasInPromo();
        $unit = (float) ($inPromo ? $line->getPromoPrice() : $line->getPrice());

        foreach ($line->getOrderProductTaxes() as $tax) {
            $unit += (float) ($inPromo ? $tax->getPromoAmount() : $tax->getAmount());
        }

        return $unit * (float) $line->getQuantity();
    }

    /**
     * @param list<int> $excludedProducts
     * @param list<int> $excludedCategories
     */
    private function isExcluded(string $productRef, array $excludedProducts, array $excludedCategories): bool
    {
        if ([] === $excludedProducts && [] === $excludedCategories) {
            return false;
        }

        $product = ProductQuery::create()->findOneByRef($productRef);

        if (null === $product) {
            return false;
        }

        if (\in_array((int) $product->getId(), $excludedProducts, true)) {
            return true;
        }

        if ([] === $excludedCategories) {
            return false;
        }

        $categories = array_map('intval', ProductCategoryQuery::create()->filterByProductId($product->getId())->select(['CategoryId'])->find()->getData());

        foreach ($categories as $categoryId) {
            if ($this->isInAnExcludedCategory($categoryId, $excludedCategories)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<int> $excludedCategories
     */
    private function isInAnExcludedCategory(int $categoryId, array $excludedCategories): bool
    {
        $seen = [];

        while ($categoryId > 0 && !isset($seen[$categoryId])) {
            if (\in_array($categoryId, $excludedCategories, true)) {
                return true;
            }

            $seen[$categoryId] = true;
            $categoryId = (int) CategoryQuery::create()->findPk($categoryId)?->getParent();
        }

        return false;
    }
}
