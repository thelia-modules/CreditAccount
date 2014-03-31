<?php

namespace CreditAccount\Model;

use CreditAccount\Model\Base\CreditAccount as BaseCreditAccount;
use Propel\Runtime\Connection\ConnectionInterface;

class CreditAccount extends BaseCreditAccount
{
    private $updateAmount = 0;

    public function addAmount($amount)
    {
        if ($amount !== null) {
            $amount = (double) $amount;

            $this->updateAmount += $amount;
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
                ->save($con);

            $this->updateAmount = 0;
        }
    }
}
