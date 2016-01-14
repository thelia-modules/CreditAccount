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

use CreditAccount\Model\CreditAccount;
use CreditAccount\Model\CreditAccountQuery;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;


/**
 * Class CreditInUseLoop
 * @package CreditAccount\Loop
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class CreditInUseLoop extends BaseLoop implements ArraySearchLoopInterface
{
    protected function getArgDefinitions()
    {
        return new ArgumentCollection();
    }

    public function parseResults(LoopResult $loopResult)
    {
        if ($loopResult->getResultDataCollectionCount() > 0) {
            $loopResultRow = new LoopResultRow();

            $resCollection = $loopResult->getResultDataCollection();

            if ($resCollection[0] === "coupon") {
                $loopResultRow
                    ->set('AMOUNT_USED', 'coupon')
                ;
                $loopResult->addRow($loopResultRow);
                return $loopResult;
            }

            $loopResultRow
                ->set('AMOUNT_USED', $this->request->getSession()->get('creditAccount.amount', 0))
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }


    /**
     * this method returns an array
     *
     * @return array
     */
    public function buildArray()
    {
        if (0 != $this->request->getSession()->get('coupon.used', 0)) {
            return ['coupon'];
        }

        if (0 != $this->request->getSession()->get('creditAccount.used', 0)) {
            // Call parseResults once.
            return [ 'hey ! parseResults !' ];
        }

        // Do not call parseResults.
        return [ ];
    }
}