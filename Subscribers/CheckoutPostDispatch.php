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

class CheckoutPostDispatch implements SubscriberInterface
{

    /** @var Util $utils * */
    protected $utils;

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => 'onPostdispatchFrontendCheckout',
        );
    }

    public function onPostdispatchFrontendCheckout(\Enlight_Controller_ActionEventArgs $args)
    {
        $this->utils = Shopware()->Container()->get('FatchipCTPaymentUtils');
        $pluginConfig = Shopware()->Plugins()->Frontend()->FatchipCTPayment()->Config()->toArray();
        $subject = $args->getSubject();
        $view = $subject->View();
        $request = $subject->Request();
        $response = $subject->Response();
        $session = Shopware()->Session();
        $params = $request->getParams();

        $userData = Shopware()->Modules()->Admin()->sGetUserData();
        $paymentName = $this->utils->getPaymentNameFromId($userData['additional']['payment']['id']);
        if (!$request->isDispatched() || $response->isException()) {
            return;
        }

        if ($request->getActionName() == 'shippingPayment') {
            $birthday = explode('-', $this->utils->getUserDoB($userData));
            $paymentData['birthday'] = $birthday[2];
            $paymentData['birthmonth'] = $birthday[1];
            $paymentData['birthyear'] = $birthday[0];
            $paymentData['phone'] = $this->utils->getUserPhone($userData);
            $paymentData['idealIssuerList'] = Shopware()->Models()->getRepository('Shopware\CustomModels\FatchipCTIdeal\FatchipCTIdealIssuers')->findAll();
            $paymentData['idealIssuer'] = $session->offsetGet('FatchipComputopIdealIssuer');
            //$paymentData['sofortIssuerList'] = Shopware()->Models()->getRepository('Shopware\CustomModels\FatchipCTIdeal\FatchipCTIdealIssuers')->findAll();
            //$paymentData['sofortIssuer'] = $session->offsetGet('FatchipComputopSofortIssuer');
            $paymentData['isCompany'] = !empty($userData['billingaddress']['company']);
            $paymentData['lastschriftbank'] = $this->utils->getUserLastschriftBank($userData);
            $paymentData['lastschriftiban'] = $this->utils->getUserLastschriftIban($userData);
            $paymentData['lastschriftkontoinhaber'] = $this->utils->getUserLastschriftKontoinhaber($userData);


            if ($this->utils->needSocialSecurityNumberForKlarna()) {
                $paymentData['socialsecuritynumber'] = $this->utils->getuserSSN($userData);
                $paymentData['showsocialsecuritynumber'] = true;
                $paymentData['SSNLabel'] = $this->utils->getSocialSecurityNumberLabelForKlarna($userData);
                $paymentData['SSNMaxLen'] = $this->utils->getSSNLength($userData);
            }

            if ($this->utils->needAnnualSalaryForKlarna($userData)) {
                $paymentData['showannualsalary'] = true;
                $paymentData['annualsalary'] = $this->utils->getUserAnnualSalary($userData);
            }

            $view->assign('FatchipCTPaymentData', $paymentData);

            // assign payment errors and error template to view
            $view->extendsTemplate('frontend/checkout/shipping_payment.tpl');
            // ToDo DO not Display User canceled:22730703 at least for paydirekt
            // logic shouldnt be here or in the template ...
            $view->assign('CTError', $params['CTError']);
        }


        // ToDo find a better way, it would be nice to move this to the Amazon Controller
        if ($this->utils->isAmazonPayActive()) {
            // assign plugin Config to View
            $view->assign('fatchipCTPaymentConfig', $pluginConfig);
            // extend cart and ajax cart with Amazon Button
            $view->extendsTemplate('frontend/checkout/ajax_cart_amazon.tpl');
            $view->extendsTemplate('frontend/checkout/cart_amazon.tpl');
        }

        // ToDo find a better way, it would be nice to move this to the Amazon Controller
        // ToDo refactor both methods to isPaymentactive($paymentName)
        if ($this->utils->isPaypalExpressActive()) {
            // assign plugin Config to View
            $view->assign('fatchipCTPaymentConfig', $pluginConfig);
            // extend cart and ajax cart with Amazon Button
            $view->extendsTemplate('frontend/checkout/ajax_cart_paypal.tpl');
            $view->extendsTemplate('frontend/checkout/cart_paypal.tpl');
        }

        if ($request->getActionName() == 'confirm' && $paymentName === 'fatchip_computop_easycredit') {

            $view->extendsTemplate('frontend/checkout/easycredit_confirm.tpl');

            // add easyCredit Information to view
            if ($session->offsetGet('FatchipComputopEasyCreditInformation')) {
                $view->assign('FatchipComputopEasyCreditInformation', $session->offsetGet('FatchipComputopEasyCreditInformation'));
            }
        }
    }
}
