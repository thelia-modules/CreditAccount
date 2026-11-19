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

namespace CreditAccount\Migration;

use CreditAccount\CreditAccount;
use CreditAccount\Ledger\CreditAmount;
use CreditAccount\Loyalty\LoyaltyMode;
use CreditAccount\Loyalty\LoyaltySettings;
use CreditAccount\Loyalty\TierGridRules;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Log\Tlog;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleQuery;

/**
 * Copies the configuration of the Loyalty module (3.x) into the grid of CreditAccount, when a shop updates to 4.0.
 *
 * It only fills what is empty: nothing happens when the grid already has a tier, and the mode is set only when
 * the merchant never chose one. Run again, it does nothing more. A grid that TierGridRules refuses is not
 * imported, and the reason goes to the log: the update is never stopped by it. The Loyalty tables are read, never
 * written.
 *
 * What Loyalty did, and how it is carried over:
 * - a tier earns when min <= cart total <= max, both included. Here a tier ends at its maximum excluded, so a
 *   maximum is moved up by one cent (the totals are in cents) unless the next tier starts there or before;
 * - a tier without a minimum, a maximum or an amount, or that ends before it starts, never matched: it is left out;
 * - the single slice mode credited `credit` for each full `amount` of the cart (a multiple). A grid cannot say
 *   that: it becomes the percentage `credit / amount` (2.50 for each 50.00 is 5 %), set only when the merchant
 *   never chose a mode. A cart of 120.00 then earns 6.00 where Loyalty gave 5.00: the slices become linear.
 */
final readonly class LegacyLoyaltyImport
{
    private const LOYALTY_MODULE = 'Loyalty';
    private const SINGLE_SLICE_MODE = 'unique';

    public function __construct(private TierGridRules $rules = new TierGridRules())
    {
    }

    public function import(ConnectionInterface $connection): LegacyLoyaltyReport
    {
        if (!$this->tableExists('loyalty', $connection)) {
            return new LegacyLoyaltyReport(0, null, 'no Loyalty table');
        }

        if ((int) $connection->query('SELECT COUNT(*) FROM `credit_loyalty_tier`')->fetchColumn() > 0) {
            return $this->skipped('the grid already has tiers');
        }

        if (self::SINGLE_SLICE_MODE === $this->loyaltyConfiguration('mode', 'multiple')) {
            return $this->importSingleSlice();
        }

        $rows = $this->tiersOfTheTable($connection);

        if ([] === $rows) {
            return $this->skipped('Loyalty has no tier that could earn');
        }

        $problem = $this->rules->problemOf($rows);

        if (null !== $problem) {
            return $this->skipped(\sprintf('the Loyalty grid is not a valid grid here (%s)', $problem->name));
        }

        $connection->beginTransaction();

        try {
            $statement = $connection->prepare('INSERT INTO `credit_loyalty_tier` (`min`, `max`, `amount`) VALUES (:min, :max, :amount)');

            foreach ($rows as $row) {
                $statement->bindValue(':min', $row['min']);
                $statement->bindValue(':max', $row['max']);
                $statement->bindValue(':amount', $row['amount']);
                $statement->execute();
            }

            $mode = null === CreditAccount::getConfigValue(LoyaltySettings::MODE) ? LoyaltyMode::Grid->value : null;

            if (null !== $mode) {
                CreditAccount::setConfigValue(LoyaltySettings::MODE, $mode);
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        Tlog::getInstance()->info(\sprintf('CreditAccount: %d Loyalty tier(s) copied into the loyalty grid.', \count($rows)));

        return new LegacyLoyaltyReport(\count($rows), $mode, null);
    }

    /**
     * @return list<array{min: string, max: ?string, amount: string}>
     */
    private function tiersOfTheTable(ConnectionInterface $connection): array
    {
        $tiers = [];

        foreach ($connection->query('SELECT `min`, `max`, `amount` FROM `loyalty` ORDER BY `id`')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (null === $row['min'] || null === $row['max'] || null === $row['amount']) {
                continue;
            }

            $min = max(0, CreditAmount::ofFloat((float) $row['min'])->cents);
            $max = CreditAmount::ofFloat((float) $row['max'])->cents;
            $amount = CreditAmount::ofFloat((float) $row['amount'])->cents;

            if ($max < $min || $amount <= 0) {
                continue;
            }

            $tiers[] = ['min' => $min, 'max' => $max, 'amount' => $amount];
        }

        usort($tiers, static fn (array $first, array $second): int => $first['min'] <=> $second['min']);

        $rows = [];

        foreach ($tiers as $index => $tier) {
            $next = $tiers[$index + 1]['min'] ?? null;
            $max = null !== $next && $next <= $tier['max'] ? $tier['max'] : $tier['max'] + 1;

            $rows[] = [
                'min' => CreditAmount::ofCents($tier['min'])->toDecimal(),
                'max' => CreditAmount::ofCents($max)->toDecimal(),
                'amount' => CreditAmount::ofCents($tier['amount'])->toDecimal(),
            ];
        }

        return $rows;
    }

    private function importSingleSlice(): LegacyLoyaltyReport
    {
        if (null !== CreditAccount::getConfigValue(LoyaltySettings::MODE)) {
            return $this->skipped('the merchant already chose a loyalty mode');
        }

        $slice = CreditAmount::ofFloat((float) $this->loyaltyConfiguration('unique_slice_amount', '0'));
        $credit = CreditAmount::ofFloat((float) $this->loyaltyConfiguration('unique_slice_credit', '0'));

        if (!$slice->isPositive() || !$credit->isPositive()) {
            return $this->skipped('Loyalty has no slice that could earn');
        }

        $percentage = round($credit->cents * 100 / $slice->cents, 4);

        if ($percentage > 100.0) {
            return $this->skipped(\sprintf('the Loyalty slice credits more than it costs (%s %%)', $percentage));
        }

        $percentage = rtrim(rtrim(number_format($percentage, 4, '.', ''), '0'), '.');

        CreditAccount::setConfigValue(LoyaltySettings::PERCENTAGE, $percentage);
        CreditAccount::setConfigValue(LoyaltySettings::MODE, LoyaltyMode::Percentage->value);

        Tlog::getInstance()->warning(\sprintf('CreditAccount: Loyalty credited %s for each full %s of the cart; the loyalty is now %s %% of the eligible amount. Review it.', $credit->toDecimal(), $slice->toDecimal(), $percentage));

        return new LegacyLoyaltyReport(0, LoyaltyMode::Percentage->value, null);
    }

    private function loyaltyConfiguration(string $name, string $default): string
    {
        $module = ModuleQuery::create()->findOneByCode(self::LOYALTY_MODULE);

        if (null === $module) {
            return $default;
        }

        return ModuleConfigQuery::create()->getConfigValue((int) $module->getId(), $name, $default) ?? $default;
    }

    private function skipped(string $reason): LegacyLoyaltyReport
    {
        Tlog::getInstance()->warning('CreditAccount: the Loyalty configuration is not imported: '.$reason.'.');

        return new LegacyLoyaltyReport(0, null, $reason);
    }

    private function tableExists(string $table, ConnectionInterface $connection): bool
    {
        try {
            $connection->query(\sprintf('SELECT 1 FROM `%s` LIMIT 0', $table));

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
