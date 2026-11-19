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

namespace CreditAccount\Tests\Integration;

use CreditAccount\CreditAccount;
use CreditAccount\Loyalty\LegacyLoyaltyModule;
use CreditAccount\Loyalty\LoyaltySettings;
use CreditAccount\Migration\LegacyLoyaltyImport;
use CreditAccount\Model\CreditLoyaltyTier;
use CreditAccount\Model\CreditLoyaltyTierQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;

/**
 * The table of the Loyalty module (3.x, FLOAT columns) is rebuilt as a temporary table: it lives in the test
 * transaction without committing it.
 */
final class LegacyLoyaltyImportTest extends IntegrationTestCase
{
    private int $loyaltyModuleId;

    protected function setUp(): void
    {
        parent::setUp();

        ModuleConfigQuery::resetConfigCache();

        $module = (new Module())
            ->setCode(LegacyLoyaltyModule::CODE)
            ->setFullNamespace('Loyalty\\Loyalty')
            ->setVersion('3.0.0')
            ->setType(BaseModule::CLASSIC_MODULE_TYPE)
            ->setCategory('classic')
            ->setActivate(BaseModule::IS_NOT_ACTIVATED);
        $module->save();
        $this->loyaltyModuleId = (int) $module->getId();
    }

    protected function tearDown(): void
    {
        $this->getPropelConnection()->exec('DROP TEMPORARY TABLE IF EXISTS `loyalty`');
        ModuleConfigQuery::resetConfigCache();

        parent::tearDown();
    }

    public function testTheTiersAreCopiedWithTheirMaximumMovedUpByACentAndTheModeBecomesTheGrid(): void
    {
        $this->loyaltyTable();
        $this->loyaltyTier(0, 49.99, 2);
        $this->loyaltyTier(50, 99.99, 5);
        $this->loyaltyTier(100, 200, 10.5);

        $report = (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertSame(3, $report->importedTiers);
        self::assertSame('grid', $report->modeSet);
        self::assertSame([['0.00', '50.00', '2.00'], ['50.00', '100.00', '5.00'], ['100.00', '200.01', '10.50']], $this->tiers());
        self::assertSame('grid', CreditAccount::getConfigValue(LoyaltySettings::MODE));
    }

    public function testAMaximumTheNextTierStartsAtStaysTheExcludedBoundOfItsTier(): void
    {
        $this->loyaltyTable();
        $this->loyaltyTier(0, 50, 2);
        $this->loyaltyTier(50, 100, 5);

        (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertSame([['0.00', '50.00', '2.00'], ['50.00', '100.01', '5.00']], $this->tiers());
    }

    public function testATierThatNeverMatchedIsLeftOut(): void
    {
        $this->loyaltyTable();
        $this->loyaltyTier(0, null, 3);
        $this->loyaltyTier(80, 20, 3);
        $this->loyaltyTier(10, 20, 0);
        $this->loyaltyTier(null, 20, 3);
        $this->loyaltyTier(30, 40, 4);

        $report = (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertSame(1, $report->importedTiers);
        self::assertSame([['30.00', '40.01', '4.00']], $this->tiers());
    }

    public function testTheSingleSliceModeBecomesThePercentageOfItsCreditOverItsSlice(): void
    {
        $this->loyaltyTable();
        $this->loyaltyTier(0, 10, 1);
        $this->singleSlice('50', '2.5');

        $report = (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertSame(0, $report->importedTiers);
        self::assertSame('percentage', $report->modeSet);
        self::assertSame([], $this->tiers());
        self::assertSame('percentage', CreditAccount::getConfigValue(LoyaltySettings::MODE));
        self::assertSame('5', CreditAccount::getConfigValue(LoyaltySettings::PERCENTAGE));
    }

    public function testASingleSliceThatDoesNotDivideEvenlyKeepsFourDecimals(): void
    {
        $this->loyaltyTable();
        $this->singleSlice('30', '1');

        (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertSame('3.3333', CreditAccount::getConfigValue(LoyaltySettings::PERCENTAGE));
    }

    public function testTheSingleSliceNeverReplacesTheModeAMerchantAlreadyChose(): void
    {
        $this->loyaltyTable();
        $this->singleSlice('50', '2.5');
        CreditAccount::setConfigValue(LoyaltySettings::MODE, 'grid');

        $report = (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertNull($report->modeSet);
        self::assertSame('grid', CreditAccount::getConfigValue(LoyaltySettings::MODE));
        self::assertNull(CreditAccount::getConfigValue(LoyaltySettings::PERCENTAGE));
    }

    public function testASingleSliceThatCreditsMoreThanItCostsIsNotImported(): void
    {
        $this->loyaltyTable();
        $this->singleSlice('10', '12');

        $report = (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertNull($report->modeSet);
        self::assertNotNull($report->skippedBecause);
        self::assertNull(CreditAccount::getConfigValue(LoyaltySettings::MODE));
    }

    public function testTheModeAMerchantAlreadyChoseIsKept(): void
    {
        $this->loyaltyTable();
        $this->loyaltyTier(0, 50, 2);
        CreditAccount::setConfigValue(LoyaltySettings::MODE, 'percentage');

        $report = (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertSame(1, $report->importedTiers);
        self::assertNull($report->modeSet);
        self::assertSame('percentage', CreditAccount::getConfigValue(LoyaltySettings::MODE));
    }

    public function testAGridAlreadyThereIsNeverOverwritten(): void
    {
        $this->loyaltyTable();
        $this->loyaltyTier(0, 50, 2);
        (new CreditLoyaltyTier())->setMin('10')->setMax(null)->setAmount('9')->save();

        $report = (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertSame(0, $report->importedTiers);
        self::assertSame([['10.00', null, '9.00']], $this->tiers());
        self::assertNull(CreditAccount::getConfigValue(LoyaltySettings::MODE), 'The mode is left to the merchant too.');
    }

    public function testRunningTheImportAgainAddsNothing(): void
    {
        $this->loyaltyTable();
        $this->loyaltyTier(0, 49.99, 2);
        $this->loyaltyTier(50, 99.99, 5);
        $import = new LegacyLoyaltyImport();

        $import->import($this->getPropelConnection());
        $second = $import->import($this->getPropelConnection());

        self::assertSame(0, $second->importedTiers);
        self::assertCount(2, $this->tiers());
    }

    public function testAGridThatTheRulesRefuseIsNotImportedAndDoesNotStopTheUpdate(): void
    {
        $this->loyaltyTable();
        $this->loyaltyTier(0, 100, 2);
        $this->loyaltyTier(50, 150, 5);

        $report = (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertSame(0, $report->importedTiers);
        self::assertNotNull($report->skippedBecause);
        self::assertSame([], $this->tiers());
        self::assertNull(CreditAccount::getConfigValue(LoyaltySettings::MODE));
    }

    public function testAShopWithoutTheLoyaltyTableImportsNothing(): void
    {
        $report = (new LegacyLoyaltyImport())->import($this->getPropelConnection());

        self::assertSame(0, $report->importedTiers);
        self::assertSame([], $this->tiers());
        self::assertNull(CreditAccount::getConfigValue(LoyaltySettings::MODE));
    }

    private function singleSlice(string $amount, string $credit): void
    {
        ModuleConfigQuery::create()->setConfigValue($this->loyaltyModuleId, 'mode', 'unique');
        ModuleConfigQuery::create()->setConfigValue($this->loyaltyModuleId, 'unique_slice_amount', $amount);
        ModuleConfigQuery::create()->setConfigValue($this->loyaltyModuleId, 'unique_slice_credit', $credit);
    }

    private function loyaltyTable(): void
    {
        $this->getPropelConnection()->exec(
            'CREATE TEMPORARY TABLE `loyalty` (`id` INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY, `min` FLOAT, `max` FLOAT, `amount` FLOAT)'
        );
    }

    private function loyaltyTier(?float $min, ?float $max, ?float $amount): void
    {
        $this->getPropelConnection()
            ->prepare('INSERT INTO `loyalty` (`min`, `max`, `amount`) VALUES (?, ?, ?)')
            ->execute([$min, $max, $amount]);
    }

    /**
     * @return list<array{0: string, 1: ?string, 2: string}>
     */
    private function tiers(): array
    {
        $tiers = [];

        foreach (CreditLoyaltyTierQuery::create()->orderByMin()->find() as $tier) {
            $tiers[] = [
                number_format((float) $tier->getMin(), 2, '.', ''),
                null === $tier->getMax() ? null : number_format((float) $tier->getMax(), 2, '.', ''),
                number_format((float) $tier->getAmount(), 2, '.', ''),
            ];
        }

        return $tiers;
    }
}
