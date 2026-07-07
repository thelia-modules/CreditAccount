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

declare(strict_types=1);

namespace CreditAccount\Loop;

use CreditAccount\Model\CreditAccount;
use CreditAccount\Model\CreditAccountQuery;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;


/**
 * Class CreditAccountLoop
 * @package CreditAccount\Loop
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 *
 * @method int|null getCustomer()
 */
class CreditAccountLoop extends BaseLoop implements PropelSearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('customer', null)
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $customer = $this->getCustomer();

        $search = CreditAccountQuery::create();

        if ($customer !== null) {
            $search->filterByCustomerId($customer);
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {

        /** @var CreditAccount $creditAccount */
        foreach ($loopResult->getResultDataCollection() as $creditAccount) {
            $loopResultRow = (new LoopResultRow($creditAccount))
                ->set('ID', $creditAccount->getId())
                ->set('CUSTOMER_ID', $creditAccount->getCustomerId())
                ->set('CREDIT_AMOUNT', $creditAccount->getAmount());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }


}