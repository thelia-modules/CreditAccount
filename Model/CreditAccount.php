<?php

namespace CreditAccount\Model;

use CreditAccount\Model\Base\CreditAccount as BaseCreditAccount;
use Propel\Runtime\Connection\ConnectionInterface;

class CreditAccount extends BaseCreditAccount
{
    private $updateAmount = 0;
    private $person = 0;
    private $orderId = 0;

    public function addAmount($amount, $orderId, $person = 'Customer')
    {
        if ($amount !== null) {
            $amount = (double) $amount;

            $this->updateAmount += $amount;
            $this->person = $person;
            $this->orderId = $orderId;

            $this->setAmount($this->getAmount() + $amount);
        }
        return $this;
    }

    public function postSave(ConnectionInterface $con = null)
    {
        if ($this->updateAmount != 0) {
            $history =  new CreditAmountHistory();

            $history
                ->setCreditAccountId($this->getId())
                ->setAmount($this->updateAmount)
                ->setWho($this->person)
                ->setOrderId($this->orderId)
                ->save($con);

            $this->updateAmount = 0;
        }
    }
}
