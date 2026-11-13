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

use CreditAccount\Ledger\CreditAmount;

/**
 * What a grid of tiers must hold to pick one tier for any amount: each tier [min, max[ is well formed, none
 * overlaps another, and only the last one may have no maximum. A single tier with no maximum is a valid grid.
 */
final readonly class TierGridRules
{
    /**
     * @param list<array{min: ?string, max: ?string, amount: ?string}> $rows
     */
    public function problemOf(array $rows): ?TierProblem
    {
        $tiers = [];

        foreach ($rows as $row) {
            if (!$this->isAmount($row['min']) || !$this->isAmount($row['amount'])) {
                return TierProblem::InvalidRow;
            }

            $min = CreditAmount::ofDecimal((string) $row['min'])->cents;
            $max = $this->isAmount($row['max']) ? CreditAmount::ofDecimal((string) $row['max'])->cents : null;

            if ($min < 0 || CreditAmount::ofDecimal((string) $row['amount'])->cents <= 0 || (null !== $max && $max <= $min) || (null === $max && null !== $row['max'] && '' !== trim($row['max']))) {
                return TierProblem::InvalidRow;
            }

            $tiers[] = [$min, $max];
        }

        usort($tiers, static fn (array $first, array $second): int => $first[0] <=> $second[0]);

        foreach ($tiers as $index => [$min, $max]) {
            if (!isset($tiers[$index + 1])) {
                continue;
            }

            if (null === $max) {
                return TierProblem::OnlyTheLastIsOpen;
            }

            if ($tiers[$index + 1][0] < $max) {
                return TierProblem::Overlap;
            }
        }

        return null;
    }

    private function isAmount(?string $value): bool
    {
        return null !== $value && 1 === preg_match('/^\d{1,10}(\.\d{1,6})?$/', trim($value));
    }
}
