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

use CreditAccount\CreditAccountManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Coupon\CouponManager;


/**
 * Class CreditInUseLoop
 * @package CreditAccount\Loop
 * @author  Franck Allimant <franck@cqfdev.fr>
 */
class CreditInUseLoop extends BaseLoop implements ArraySearchLoopInterface
{
    /** @var CouponManager  */
    private $couponManager;
    /** @var CreditAccountManager  */
    private $creditAccountManager;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        /** @noinspection MissingService */
        $this->couponManager = $this->container->get('thelia.coupon.manager');
        $this->creditAccountManager = $this->container->get('creditaccount.manager');
    }

    protected function getArgDefinitions()
    {
        return new ArgumentCollection();
    }

    public function parseResults(LoopResult $loopResult)
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


    /**
     * this method returns an array
     *
     * @return array
     */
    public function buildArray()
    {
        $session = $this->getCurrentRequest()->getSession();
        if (
            $this->creditAccountManager->getDiscount($session) > 0 ||
            !empty($session->getConsumedCoupons())
        ) {
            // Call parseResults once.
            return [ 'hey ! parseResults !' ];
        }

        // Do not call parseResults.
        return [ ];
    }
}