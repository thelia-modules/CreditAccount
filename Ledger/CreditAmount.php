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

namespace CreditAccount\Ledger;

/**
 * An amount of credit, held in cents: the balance is money, and a float would let two sums of the same
 * movements disagree on the last cent.
 */
final readonly class CreditAmount
{
    private function __construct(public int $cents)
    {
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function ofCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * Reads a DECIMAL column or a typed amount ("12.5", "-3.400000"), rounded half away from zero to the cent.
     */
    public static function ofDecimal(string $decimal): self
    {
        $decimal = trim($decimal);

        if (1 !== preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $decimal, $parts) || ('' === $parts[2] && '' === ($parts[3] ?? ''))) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not an amount.', $decimal));
        }

        $fraction = str_pad($parts[3] ?? '', 3, '0');
        $cents = (int) $parts[2] * 100 + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            ++$cents;
        }

        return new self('-' === $parts[1] ? -$cents : $cents);
    }

    /**
     * For the amounts the core hands out as floats (cart totals, order totals).
     */
    public static function ofFloat(float $amount): self
    {
        if (!is_finite($amount)) {
            throw new \InvalidArgumentException('An amount must be finite.');
        }

        return new self((int) round($amount * 100));
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function minus(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function negated(): self
    {
        return new self(-$this->cents);
    }

    public function absolute(): self
    {
        return new self(abs($this->cents));
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isZero(): bool
    {
        return 0 === $this->cents;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    public static function min(self $first, self $second): self
    {
        return $first->cents <= $second->cents ? $first : $second;
    }

    /**
     * The value a DECIMAL column takes, two decimals.
     */
    public function toDecimal(): string
    {
        $absolute = abs($this->cents);

        return \sprintf('%s%d.%02d', $this->cents < 0 ? '-' : '', intdiv($absolute, 100), $absolute % 100);
    }

    public function toFloat(): float
    {
        return $this->cents / 100;
    }
}
