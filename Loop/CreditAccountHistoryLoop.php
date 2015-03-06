<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace CreditAccount\Loop;

use CreditAccount\Model\CreditAmountHistory;
use CreditAccount\Model\CreditAmountHistoryQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

/**
 * Class CreditAccountHistoryLoop
 * @package CreditAccount\Loop
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CreditAccountHistoryLoop extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('credit_account', null, true),
            Argument::createIntTypeArgument('order')
        );
    }

    public function buildModelCriteria()
    {
        $search = CreditAmountHistoryQuery::create()
            ->filterByCreditAccountId($this->getCreditAccount())
            ->orderByCreatedAt(Criteria::DESC)
        ;

        if (null !== $orderId = $this->getOrder()) {
            $search->filterByOrderId($orderId);
        }

        return $search;
    }

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var CreditAmountHistory $creditAmountHistory */
        foreach ($loopResult->getResultDataCollection() as $creditAmountHistory) {
            $orderId = $creditAmountHistory->getOrderId();

            $loopResultRow = (new LoopResultRow($creditAmountHistory))
                ->set('CREDIT_AMOUNT', $creditAmountHistory->getAmount())
                ->set('WHO_DID_IT', $creditAmountHistory->getWho())
                ->set('HAS_ORDER_ID', ! empty($orderId))
                ->set('ORDER_ID', $creditAmountHistory->getOrderId())
                ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}