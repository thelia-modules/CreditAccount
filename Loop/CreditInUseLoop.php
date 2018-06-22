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

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        /** @noinspection MissingService */
        $this->couponManager = $this->container->get('thelia.coupon.manager');
    }

    protected function getArgDefinitions()
    {
        return new ArgumentCollection();
    }

    public function parseResults(LoopResult $loopResult)
    {
        if ($loopResult->getResultDataCollectionCount() > 0) {
            $loopResultRow = new LoopResultRow();

            $loopResultRow->set('CREDIT_COUPONS_AMOUNT', $this->couponManager->getDiscount());
            $loopResult->addRow($loopResultRow);

            $creditUsed = $this->request->getSession()->get('creditAccount.used', 0);
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
        $sesssion = $this->getCurrentRequest()->getSession();
        if (
            $sesssion->get('creditAccount.used', 0) > 0 ||
            !empty($sesssion->getConsumedCoupons())
        ) {
            // Call parseResults once.
            return [ 'hey ! parseResults !' ];
        }

        // Do not call parseResults.
        return [ ];
    }
}