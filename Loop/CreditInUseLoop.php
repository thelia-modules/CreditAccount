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

use CreditAccount\CreditAccountManager;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;

/**
 * Class CreditInUseLoop
 * @package CreditAccount\Loop
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class CreditInUseLoop extends BaseLoop implements ArraySearchLoopInterface
{
    public function __construct(
        private readonly CouponManager $couponManager,
        private readonly CreditAccountManager $creditAccountManager,
    ) {
    }

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection();
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        if ($loopResult->getResultDataCollectionCount() > 0) {
            $session = $this->getCurrentRequest()->getSession();
            $loopResultRow = new LoopResultRow();

            $loopResultRow->set('CREDIT_COUPONS_AMOUNT', $this->couponManager->getDiscount());
            $loopResult->addRow($loopResultRow);

            $creditUsed = $this->creditAccountManager->getDiscount($session);
            $loopResultRow->set('CREDIT_ACCOUNT_AMOUNT', $creditUsed);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    public function buildArray(): array
    {
        $session = $this->getCurrentRequest()->getSession();
        if (!$session instanceof Session) {
            return [];
        }

        if (
            $this->creditAccountManager->getDiscount($session) > 0
            || !empty($session->getConsumedCoupons())
        ) {
            // Call parseResults once.
            return ['hey ! parseResults !'];
        }

        // Do not call parseResults.
        return [];
    }
}
