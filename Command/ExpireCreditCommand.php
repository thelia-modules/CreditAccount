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

use CreditAccount\Expiration\CreditExpirer;
use CreditAccount\Ledger\CreditExpiry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'credit-account:expire',
    description: 'Warn the customers whose credit is about to expire, and expire the credit that reached its date unspent',
)]
final class ExpireCreditCommand extends Command
{
    private const DEFAULT_LIMIT = 200;

    public function __construct(private readonly CreditExpirer $expirer, private readonly CreditExpiry $expiry)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Customers handled by this run, the oldest expiry first', (string) self::DEFAULT_LIMIT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = filter_var($input->getOption('limit'), \FILTER_VALIDATE_INT);

        if (false === $limit || $limit < 1) {
            $io->error('The limit is a whole number above zero.');

            return Command::INVALID;
        }

        if (!$this->expiry->isEnabled()) {
            $io->note('Credit expiration is disabled in the module configuration: nothing to do.');

            return Command::SUCCESS;
        }

        $report = $this->expirer->run(new \DateTimeImmutable(), $limit);

        $io->success(\sprintf(
            '%d customer(s) handled: %d movement(s) expired for %s, %d warning(s) sent.',
            $report->customersHandled,
            $report->movementsExpired,
            $report->amountExpired->toDecimal(),
            $report->warningsSent,
        ));

        if ($report->warningsFailed > 0) {
            $io->warning(\sprintf('%d warning(s) could not be sent and will be tried again by the next run.', $report->warningsFailed));
        }

        if ($report->customersHandled >= $limit) {
            $io->note('The limit was reached: run the command again to handle the customers left.');
        }

        return Command::SUCCESS;
    }
}
