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

namespace CreditAccount\Tests\Unit;

use CreditAccount\Ledger\CreditAmount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CreditAmountTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function decimals(): iterable
    {
        yield 'a DECIMAL column' => ['12.340000', 1234];
        yield 'a negative column' => ['-3.400000', -340];
        yield 'no fraction' => ['7', 700];
        yield 'one decimal' => ['0.5', 50];
        yield 'a half cent rounds up' => ['1.005000', 101];
        yield 'below a half cent rounds down' => ['1.004999', 100];
        yield 'a negative half cent rounds away from zero' => ['-1.005', -101];
        yield 'no integer part' => ['.25', 25];
    }

    #[DataProvider('decimals')]
    public function testReadsADecimalToTheCent(string $decimal, int $cents): void
    {
        self::assertSame($cents, CreditAmount::ofDecimal($decimal)->cents);
    }

    public function testWritesWhatADecimalColumnTakes(): void
    {
        self::assertSame('12.05', CreditAmount::ofCents(1205)->toDecimal());
        self::assertSame('-0.07', CreditAmount::ofCents(-7)->toDecimal());
        self::assertSame('0.00', CreditAmount::zero()->toDecimal());
    }

    public function testAddsWithoutTheErrorOfAFloat(): void
    {
        $sum = CreditAmount::zero();

        foreach (array_fill(0, 10, '0.1') as $tenth) {
            $sum = $sum->plus(CreditAmount::ofDecimal($tenth));
        }

        self::assertTrue($sum->equals(CreditAmount::ofCents(100)));
    }

    public function testRefusesWhatIsNoAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CreditAmount::ofDecimal('12,50');
    }
}
