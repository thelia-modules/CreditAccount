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

namespace CreditAccount\Command;

use CreditAccount\Ledger\BalanceAudit;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'credit-account:recalculate',
    description: 'Compare the cached balance of every credit account with the sum of its movements, and fix it on demand',
)]
final class RecalculateBalancesCommand extends Command
{
    public function __construct(private readonly BalanceAudit $audit)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Write the sum of the movements into the cache of the accounts that disagree');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $gaps = $this->audit->gaps();

        if ([] === $gaps) {
            $io->success('Every cached balance equals the sum of its movements.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Customer', 'Cached', 'Sum of the movements'],
            array_map(static fn (array $gap): array => [$gap['customerId'], $gap['cached']->toDecimal(), $gap['summed']->toDecimal()], $gaps),
        );

        if (!$input->getOption('fix')) {
            $io->warning(\sprintf('%d cached balance(s) differ from their movements. Run again with --fix to rewrite them.', \count($gaps)));

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d cached balance(s) rewritten from their movements.', $this->audit->fix()));

        return Command::SUCCESS;
    }
}
