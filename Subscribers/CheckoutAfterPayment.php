<?php
/**
 * The Computop Shopware Plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The Computop Shopware Plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Computop Shopware Plugin. If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5.6, 7 , 7.1
 *
 * @category  Payment
 * @package   Computop_Shopware5_Plugin
 * @author    FATCHIP GmbH <support@fatchip.de>
 * @copyright 2018 Computop
 * @license   <http://www.gnu.org/licenses/> GNU Lesser General Public License
 * @link      https://www.computop.com
 */

namespace Shopware\Plugins\FatchipCTPayment\Subscribers;

use Enlight\Event\SubscriberInterface;
use Shopware\Plugins\FatchipCTPayment\Util;

class CheckoutAfterPayment implements SubscriberInterface
{
    /** @var Util $utils * */
    protected $utils;

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Shopware_Controllers_Frontend_Checkout::saveShippingPaymentAction::after' => 'onAfterPaymentAction',
        );
    }

    public function onAfterPaymentAction(\Enlight_Hook_HookArgs $args)
    {
        $this->utils = Shopware()->Container()->get('FatchipCTPaymentUtils');
        $subject = $args->getSubject();
        $request = $subject->Request();
        $params = $request->getParams();
        $userId = Shopware()->Session()->offsetGet('sUserId');
        $userData = Shopware()->Modules()->Admin()->sGetUserData();
        $paymentName = $this->utils->getPaymentNameFromId($userData['additional']['payment']['id']);

        if ($request->getActionName() === 'saveShippingPayment') {
            $this->updateUserDoB($paymentName, $userId, $params);
            $this->updateUserPhone($paymentName, $userId, $params);
            $this->updateUserSSN($paymentName, $userId, $params);
            $this->updateUserAnnualSalary($paymentName, $userId, $params);
            $this->updateUserLastschriftBank($paymentName, $userId, $params);
            $this->updateUserLastschriftIban($paymentName, $userId, $params);
            $this->updateUserLastschriftKontoinhaber($paymentName, $userId, $params);

            $this->setIssuerInSession($paymentName, $params);

            if ($paymentName === 'fatchip_computop_easycredit') {
                $subject->redirect(['controller' => 'FatchipCTEasyCredit', 'action' => 'gateway', 'forceSecure' => true]);
            }
        }
    }

    private function updateUserDoB($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_birthyear'])) {
            $this->utils->updateUserDoB($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_birthyear'] . '-' .
                $params['FatchipComputopPaymentData'][$paymentName . '_birthmonth'] . '-' .
                $params['FatchipComputopPaymentData'][$paymentName . '_birthday']
            );
        }
    }

    private function updateUserPhone($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_phone'])) {
            $this->utils->updateUserPhone($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_phone']
            );
        }
    }

    private function updateUserSSN($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_socialsecuritynumber'])) {
            $this->utils->updateUserSSN($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_socialsecuritynumber']
            );
        }
    }

    private function updateUserAnnualSalary($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_annualsalary'])) {
            $this->utils->updateUserAnnualSalary($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '__annualsalary']
            );
        }
    }

    private function updateUserLastschriftBank($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_bank'])) {
            $this->utils->updateUserLastschriftBank($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_bank']
            );
        }
    }

    private function updateUserLastschriftIban($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_iban'])) {
            $this->utils->updateUserLastschriftIban($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_iban']
            );
        }
    }

    private function updateUserLastschriftKontoinhaber($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_kontoinhaber'])) {
            $this->utils->updateUserLastschriftKontoinhaber($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_kontoinhaber']
            );
        }
    }

    private function setIssuerInSession($paymentName, $params)
    {
        $session = Shopware()->Session();
        if (!empty($params['FatchipComputopPaymentData']['fatchip_computop_ideal_issuer']) && $paymentName === 'fatchip_computop_ideal') {
            $session->offsetSet('FatchipComputopIdealIssuer',
                $params['FatchipComputopPaymentData']['fatchip_computop_ideal_issuer']
            );
        }

        /* if (!empty($params['FatchipComputopPaymentData']['fatchip_computop_sofort_issuer']) && $paymentName === 'fatchip_computop_sofort') {
             $session->offsetSet('FatchipComputopSofortIssuer',
               $params['FatchipComputopPaymentData']['fatchip_computop_sofort_issuer']
             );
         }
        */
    }
}
