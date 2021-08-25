<?php

namespace CreditAccount\Command;

use CreditAccount\CreditAccount;
use CreditAccount\Event\CreditAccountEvent;
use CreditAccount\Model\CreditAccountExpiration;
use CreditAccount\Model\CreditAccountExpirationQuery;
use CreditAccount\Model\CreditAccountQuery;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Model\CustomerQuery;

class CreditExpiration extends ContainerAwareCommand
{
    public function configure()
    {
        $this
            ->setName('creditaccount:expiration:check')
            ->setDescription("Check expiration for credit account");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $now = new \DateTime();

        $creditAccountExpirations = CreditAccountExpirationQuery::create()
            ->find();

        /** @var CreditAccountExpiration $creditAccountExpiration */
        foreach ($creditAccountExpirations as $creditAccountExpiration) {
            try {
                $expirationDelay = $creditAccountExpiration->getExpirationDelay();
                $interval = new \DateInterval('P' . $expirationDelay . 'M');

                /** @var \DateTime $startDate */
                $startDate = $creditAccountExpiration->getExpirationStart();
                $expirationDate = $startDate->add($interval);

                if ($now > $expirationDate) {
                    $creditAccount = CreditAccountQuery::create()
                        ->findOneById($creditAccountExpiration->getCreditAccountId());

                    $customer = CustomerQuery::create()
                        ->findOneById($creditAccount->getCustomerId());

                    $event = new CreditAccountEvent($customer, -$creditAccount->getAmount());
                    $event->setWhoDidIt("Expiration $expirationDelay months");
                    $dispatcher->dispatch($event, CreditAccount::CREDIT_ACCOUNT_ADD_AMOUNT);

                    $creditAccountExpiration->delete();

                    $output->writeln(sprintf('Credit for customer id %s expired', $customer->getId()));
                }
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
            }
        }
    }
}
