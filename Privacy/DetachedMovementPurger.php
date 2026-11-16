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

namespace CreditAccount\Privacy;

use CreditAccount\CreditAccount;
use CreditAccount\Model\Map\CreditMovementTableMap;
use Propel\Runtime\Propel;

/**
 * Applies the retention period to the movements already detached from a customer (see {@see CreditDetacher}).
 * A movement still attached to a customer is never deleted, whatever its age: no balance moves.
 */
final readonly class DetachedMovementPurger
{
    public const RETENTION_YEARS = 'purge_detached_after_years';
    public const DEFAULT_RETENTION_YEARS = '10';
    public const BATCH_SIZE = 1000;

    public function __construct(private int $batchSize = self::BATCH_SIZE)
    {
    }

    /**
     * The retention in years; 0 means the movements are never purged.
     */
    public function retentionYears(): int
    {
        return max(0, (int) CreditAccount::getConfigValue(self::RETENTION_YEARS, self::DEFAULT_RETENTION_YEARS));
    }

    public function count(int $years): int
    {
        $statement = Propel::getReadConnection(CreditMovementTableMap::DATABASE_NAME)->prepare(
            'SELECT COUNT(*) FROM `credit_movement` WHERE `customer_id` IS NULL AND `created_at` < :threshold'
        );
        $statement->bindValue(':threshold', $this->threshold($years));
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * Deletes by bounded batches, so a large journal never holds a long lock.
     *
     * @return int the number of movements deleted
     */
    public function purge(int $years): int
    {
        $connection = Propel::getWriteConnection(CreditMovementTableMap::DATABASE_NAME);
        $threshold = $this->threshold($years);
        $deleted = 0;

        do {
            $statement = $connection->prepare(
                'DELETE FROM `credit_movement` WHERE `customer_id` IS NULL AND `created_at` < :threshold ORDER BY `id` LIMIT '.$this->batchSize
            );
            $statement->bindValue(':threshold', $threshold);
            $statement->execute();
            $batch = $statement->rowCount();
            $deleted += $batch;
        } while ($this->batchSize === $batch);

        return $deleted;
    }

    private function threshold(int $years): string
    {
        return (new \DateTimeImmutable())->modify(\sprintf('-%d years', $years))->format('Y-m-d H:i:s');
    }
}
