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
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Ledger\CreditExpiry;
use CreditAccount\Loyalty\LoyaltySettings;
use CreditAccount\Model\CreditLoyaltyExcludedCategory;
use CreditAccount\Model\CreditLoyaltyExcludedCategoryQuery;
use CreditAccount\Model\CreditLoyaltyExcludedProduct;
use CreditAccount\Model\CreditLoyaltyExcludedProductQuery;
use CreditAccount\Model\CreditLoyaltyTier;
use CreditAccount\Model\CreditLoyaltyTierQuery;
use CreditAccount\Model\Map\CreditLoyaltyTierTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\CategoryTableMap;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductQuery;

/**
 * What the merchant chose, read for the configuration form and written back from it: the expiration and the
 * loyalty keys of the module configuration, the tiers of the grid and the exclusions.
 *
 * @phpstan-type Tier array{min: string, max: ?string, amount: string}
 * @phpstan-type Settings array{expiration_enabled: bool, expiration_delay: int, expiration_warning_days: int, loyalty_trigger_status: string, loyalty_mode: string, loyalty_percentage: ?string, tiers: list<Tier>, excluded_categories: list<int>, excluded_products: string}
 */
final readonly class CreditConfiguration
{
    public const DEFAULT_DELAY_IN_MONTHS = 18;

    /**
     * Statuses from which an order never earns: the order is not paid, or it was given up.
     */
    private const STATUSES_THAT_NEVER_EARN = [OrderStatus::CODE_NOT_PAID, OrderStatus::CODE_CANCELED, OrderStatus::CODE_REFUNDED];

    public function __construct(
        private CreditExpiry $expiry,
        private LoyaltySettings $loyalty,
    ) {
    }

    /**
     * @return Settings
     */
    public function current(): array
    {
        $tiers = [];

        foreach (CreditLoyaltyTierQuery::create()->orderByMin()->find() as $tier) {
            $tiers[] = [
                'min' => CreditAmount::ofDecimal((string) $tier->getMin())->toDecimal(),
                'max' => null === $tier->getMax() ? null : CreditAmount::ofDecimal((string) $tier->getMax())->toDecimal(),
                'amount' => CreditAmount::ofDecimal((string) $tier->getAmount())->toDecimal(),
            ];
        }

        $categories = CreditLoyaltyExcludedCategoryQuery::create()->select('CategoryId')->find()->toArray();
        $references = ProductQuery::create()
            ->filterById(CreditLoyaltyExcludedProductQuery::create()->select('ProductId')->find()->toArray(), Criteria::IN)
            ->orderByRef()
            ->select('Ref')
            ->find()
            ->toArray();

        $percentage = $this->loyalty->percentage();

        return [
            'expiration_enabled' => $this->expiry->isEnabled(),
            'expiration_delay' => max(1, (int) CreditAccount::getConfigValue(CreditExpiry::DELAY_IN_MONTHS, (string) self::DEFAULT_DELAY_IN_MONTHS)),
            'expiration_warning_days' => $this->expiry->warningDays(),
            'loyalty_trigger_status' => $this->loyalty->triggerStatusCode(),
            'loyalty_mode' => $this->loyalty->mode()->value,
            'loyalty_percentage' => '0' === $percentage ? null : $percentage,
            'tiers' => $tiers,
            'excluded_categories' => array_map('intval', $categories),
            'excluded_products' => implode("\n", $references),
        ];
    }

    /**
     * Replaces the settings, the grid and the exclusions in one transaction.
     *
     * @param Settings $settings the references of excluded_products must all exist: the form checks it
     */
    public function save(array $settings): void
    {
        $productIds = $this->resolveProductReferences($settings['excluded_products'])['ids'];

        $connection = Propel::getWriteConnection(CreditLoyaltyTierTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            CreditAccount::setConfigValue(CreditExpiry::ENABLED, $settings['expiration_enabled'] ? 'true' : 'false');
            CreditAccount::setConfigValue(CreditExpiry::DELAY_IN_MONTHS, (string) $settings['expiration_delay']);
            CreditAccount::setConfigValue(CreditExpiry::WARNING_DAYS, (string) $settings['expiration_warning_days']);
            CreditAccount::setConfigValue(LoyaltySettings::TRIGGER_STATUS, $settings['loyalty_trigger_status']);
            CreditAccount::setConfigValue(LoyaltySettings::MODE, $settings['loyalty_mode']);
            CreditAccount::setConfigValue(LoyaltySettings::PERCENTAGE, $settings['loyalty_percentage'] ?? '0');

            CreditLoyaltyTierQuery::create()->deleteAll($connection);

            foreach ($settings['tiers'] as $row) {
                (new CreditLoyaltyTier())
                    ->setMin($row['min'])
                    ->setMax($row['max'])
                    ->setAmount($row['amount'])
                    ->save($connection);
            }

            CreditLoyaltyExcludedCategoryQuery::create()->deleteAll($connection);

            foreach (array_unique($settings['excluded_categories']) as $categoryId) {
                (new CreditLoyaltyExcludedCategory())->setCategoryId($categoryId)->save($connection);
            }

            CreditLoyaltyExcludedProductQuery::create()->deleteAll($connection);

            foreach (array_unique($productIds) as $productId) {
                (new CreditLoyaltyExcludedProduct())->setProductId($productId)->save($connection);
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    /**
     * Splits a list of product references, one per line, into the ids that exist and the references that do not.
     *
     * @return array{ids: list<int>, unknown: list<string>}
     */
    public function resolveProductReferences(string $references): array
    {
        $wanted = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n]+/', $references) ?: []), static fn (string $reference): bool => '' !== $reference)));

        if ([] === $wanted) {
            return ['ids' => [], 'unknown' => []];
        }

        $ids = [];
        $found = [];

        foreach (ProductQuery::create()->filterByRef($wanted, Criteria::IN)->select(['Id', 'Ref'])->find() as $row) {
            $ids[] = (int) $row['Id'];
            $found[] = mb_strtolower((string) $row['Ref']);
        }

        return [
            'ids' => $ids,
            'unknown' => array_values(array_filter($wanted, static fn (string $reference): bool => !\in_array(mb_strtolower($reference), $found, true))),
        ];
    }

    /**
     * @return array<string, int> the title of each category, then its id
     */
    public function categoryChoices(string $locale): array
    {
        $choices = [];

        foreach (CategoryQuery::create()->joinWithI18n($locale)->orderBy(CategoryTableMap::COL_ID)->find() as $category) {
            $title = trim((string) $category->setLocale($locale)->getTitle());
            $choices[\sprintf('%s (#%d)', '' === $title ? '?' : $title, $category->getId())] = (int) $category->getId();
        }

        return $choices;
    }

    /**
     * @return array<string, string> the title of each status that can trigger the earning, then its code
     */
    public function statusChoices(string $locale): array
    {
        $choices = [];

        foreach (OrderStatusQuery::create()->joinWithI18n($locale)->orderById()->find() as $status) {
            if (\in_array($status->getCode(), self::STATUSES_THAT_NEVER_EARN, true)) {
                continue;
            }

            $title = trim((string) $status->setLocale($locale)->getTitle());
            $choices['' === $title ? (string) $status->getCode() : $title] = (string) $status->getCode();
        }

        return $choices;
    }
}
