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

namespace CreditAccount\Form;

use CreditAccount\CreditAccount;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

/**
 * Class CreditAccountForm
 * @package CreditAccount\Form
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class CreditAccountForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'amount',
                NumberType::class,
                [
                    'constraints' => [ new NotBlank() ],
                    'label' => $this->translator->trans('Add this amount to account', [], CreditAccount::DOMAIN),
                    'label_attr' => [
                        'help' => $this->translator->trans('Enter a negative number to decreate account balance.', [], CreditAccount::DOMAIN)
                    ]
                ]
            )
            ->add(
                'customer_id',
                HiddenType::class,
                [
                    'constraints' => [  new NotBlank(), new GreaterThan(['value' => 0]) ]
                ]
            )
        ;
    }

    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'credit_account';
    }
}
