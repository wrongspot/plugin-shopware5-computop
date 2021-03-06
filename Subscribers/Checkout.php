<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

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
 * PHP version 5.6, 7.0 , 7.1
 *
 * @category   Payment
 * @package    FatchipCTPayment
 * @subpackage Subscibers
 * @author     FATCHIP GmbH <support@fatchip.de>
 * @copyright  2018 Computop
 * @license    <http://www.gnu.org/licenses/> GNU Lesser General Public License
 * @link       https://www.computop.com
 */

namespace Shopware\Plugins\FatchipCTPayment\Subscribers;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\Theme\LessDefinition;
use Shopware\Plugins\FatchipCTPayment\Util;
use Fatchip\CTPayment\CTOrder\CTOrder;

/**
 * Class Checkout
 *
 * @package Shopware\Plugins\FatchipCTPayment\Subscribers
 */
class Checkout implements SubscriberInterface
{

    /**
     * PaymentService
     * @var \Fatchip\CTPayment\CTPaymentService $service
     */
    protected $paymentService;

    /** @var Util $utils * */
    protected $utils;

    /**
     * Array containing the pluginsetting
     * @var array
     */
    protected $config;

    /**
     * FatchipCTpayment Plugin Bootstrap Class
     * @var Shopware_Plugins_Frontend_FatchipCTPayment_Bootstrap
     */
    protected $plugin;

    /**
     * return array with all subscribed events
     *
     * @return array<string,string>
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Shopware_Controllers_Frontend_Checkout::saveShippingPaymentAction::after' => 'onAfterPaymentAction',
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => 'onPostdispatchFrontendCheckout',
            'Theme_Compiler_Collect_Plugin_Less' => 'onThemeCompilerCollectPluginLess',
        );
    }

    /**
     * Checks the request for computop parameters and validates them.
     *
     * @param \Enlight_Hook_HookArgs $args
     */
    public function onAfterPaymentAction(\Enlight_Hook_HookArgs $args)
    {
        $this->utils = Shopware()->Container()->get('FatchipCTPaymentUtils');
        $subject = $args->getSubject();
        $request = $subject->Request();
        $params = $request->getParams();
        $session = Shopware()->Session();
        $userData = Shopware()->Modules()->Admin()->sGetUserData();
        // Todo check in all sw versions
        // sw 5.0
        // sw 5.1
        // sw 5.2
        // sw 5.3
        // sw 5.4
        $paymentName = $this->utils->getPaymentNameFromId($userData['additional']['payment']['id']);

        // ToDo prevent forward to checkout confirm if params are missing

        if ($request->getActionName() === 'saveShippingPayment') {
            $this->updateUserDoB($paymentName, $session->get('sUserId'), $params);
            $this->updateUserPhone($paymentName, $session->get('sUserId'), $params);
            $this->updateUserSSN($paymentName, $session->get('sUserId'), $params);
            $this->updateUserAnnualSalary($paymentName, $session->get('sUserId'), $params);
            $this->updateUserLastschriftBank($paymentName, $session->get('sUserId'), $params);
            $this->updateUserLastschriftIban($paymentName, $session->get('sUserId'), $params);
            $this->updateUserLastschriftKontoinhaber($paymentName, $session->get('sUserId'), $params);
            $this->updateUserAfterpayInstallmentIban($paymentName, $session->get('sUserId'), $params);
            $this->setAfterpayProductNrInSession($params);
            $this->setIssuerInSession($paymentName, $params);

            if ($paymentName === 'fatchip_computop_easycredit') {
                $subject->redirect(['controller' => 'FatchipCTEasyCredit', 'action' => 'gateway', 'forceSecure' => true]);
            }
        }
    }


    /**
     * 1. Assigns template variables for the ShippingPayment action
     * 2. Extends templates for AmazonPay and PaypalExpress
     * 3. Extends confirm template for Easycredit to show Easycredit conditions on confirm page
     *
     * @param \Enlight_Controller_ActionEventArgs $args
     * @return void
     */
    public function onPostdispatchFrontendCheckout(\Enlight_Controller_ActionEventArgs $args)
    {
        $this->utils = Shopware()->Container()->get('FatchipCTPaymentUtils');
        // refactor to $this->config
        $pluginConfig = Shopware()->Plugins()->Frontend()->FatchipCTPayment()->Config()->toArray();
        $this->config = Shopware()->Plugins()->Frontend()->FatchipCTPayment()->Config()->toArray();
        $this->plugin = Shopware()->Plugins()->Frontend()->FatchipCTPayment();
        $this->paymentService = Shopware()->Container()->get('FatchipCTPaymentApiClient');
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
            $paymentData['afterpayinstallmentiban'] = $this->utils->getUserAfterpayInstallmentIban($userData);


            if ($this->utils->needSocialSecurityNumberForKlarna() || isset($userData['billingaddress']['company'])) {
                $paymentData['socialsecuritynumber'] = $this->utils->getuserSSN($userData);
                $paymentData['showsocialsecuritynumber'] = true;
                $paymentData['SSNLabel'] = $this->utils->getSocialSecurityNumberLabelForKlarna($userData);
                $paymentData['SSNMaxLen'] = $this->utils->getSSNLength($userData);
            }

            if ($this->utils->needAnnualSalaryForKlarna($userData)) {
                $paymentData['showannualsalary'] = true;
                $paymentData['annualsalary'] = $this->utils->getUserAnnualSalary($userData);
            }

            $payments = $view->getAssign('sPayments');

            foreach ($payments as $index => $payment) {
                if ($payment['name'] === 'fatchip_computop_amazonpay') {
                    $amazonPayIndex = $index;
                }
                if ($payment['name'] === 'fatchip_computop_paypal_express') {
                    $paypalExpressIndex = $index;
                }
                if ($payment['name'] === 'fatchip_computop_klarna_invoice') {
                    $klarnaInvoiceIndex = $index;
                }
                if ($payment['name'] === 'fatchip_computop_klarna_installment') {
                    $klarnaInsatallmentIndex = $index;
                }

                if ($payment['name'] === 'fatchip_computop_afterpay_installment') {
                    $afterpayInstallmentIndex = $index;
                }
                if ($payment['name'] === 'fatchip_computop_afterpay_invoice') {
                    $afterpayInvoiceIndex = $index;
                }
            }

            // remove afterpay_installment if there are no installment conditions available
            if (!$this->utils->afterpayProductExistsforBasketValue($this->config['merchantID'], $userData, false)
                || !empty($userData['billingaddress']['company']))
            {
                unset($payments[$afterpayInstallmentIndex]);
            }
            if (!empty($userData['billingaddress']['company']))
            {
                unset($payments[$afterpayInvoiceIndex]);
            }

            unset ($payments[$amazonPayIndex]);
            unset ($payments[$paypalExpressIndex]);

            if ($this->utils->isKlarnaBlocked($userData)) {
                unset ($payments[$klarnaInvoiceIndex]);
                unset ($payments[$klarnaInsatallmentIndex]);
            }

            $view->assign('sPayments', $payments);
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

        if ($request->getActionName() == 'confirm' && $paymentName == 'fatchip_computop_creditcard' && $pluginConfig['creditCardMode'] == 'SILENT') {

            $view->assign('fatchipCTCreditCardMode', "1");
            // the creditcard form send all data directly to payssl.aspx
            // set the neccessary pre-encrypted fields in view
            $payment = $this->getPaymentClassForGatewayAction();
            $payment->setCapture('MANUAL');

            $shopContext = Shopware()->Container()->get('shopware_storefront.context_service')->getShopContext();
            $shopName = $shopContext->getShop()->getName();
            $payment->setOrderDesc($shopName);

            $requestParams = $payment->getRedirectUrlParams();
            unset($requestParams['Template']);
            $silentParams = $payment->prepareSilentRequest($requestParams);
            $session->offsetSet('fatchipCTRedirectParams', $requestParams);
            $view->assign('fatchipCTCreditCardSilentParams', $silentParams);
            $view->extendsTemplate('frontend/checkout/creditcard_confirm.tpl');
        }

        if ($request->getActionName() == 'confirm' && (strpos($paymentName, fatchip_computop) === 0)) {
            // check for address splitting errors and handle them here
            $util = new Util();
            $ctOrder = new CTOrder();
            try {
                $ctOrder->setBillingAddress($util->getCTAddress($userData['billingaddress']));
                $ctOrder->setShippingAddress($util->getCTAddress($userData['shippingaddress']));
            } catch (\Exception $e) {

                $ctError = [];
                $ctError['CTErrorMessage'] = 'Bei der Verarbeitung Ihrer Adresse ist ein Fehler aufgetreten <BR>';
                $ctError['CTErrorCode'] = 'Bitte prüfen Sie Straße und Hausnummer';
                //$subject->forward('shippingPayment', 'checkout', null, ['CTError' => $ctError]);
                $view->assign('CTError', $ctError);
                return;

            }
        }

        if ($request->getActionName() == 'shippingPayment' && $paymentName == 'fatchip_computop_afterpay_installment') {
            $view->assign('fatchipCTPaymentConfig', $pluginConfig);
        }

        // prevent skipping of shippingpayment
        if ($request->getActionName() == 'confirm' && $paymentName == 'fatchip_computop_afterpay_installment') {
            $session = Shopware()->Session();
            if (!$session->offsetExists('FatchipComputopAfterpayProductNr')) {
                $subject->redirect(
                    array(
                        'controller' => 'checkout',
                        'action' => 'shippingPayment',
                    )
                );
            }

        }

    }

    /**
     * Saves date of birth info from template params
     *
     * @param $paymentName
     * @param $userId
     * @param $params
     */
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

    /**
     * Saves Phone info from template params in user billing attributes
     *
     * @param $paymentName
     * @param $userId
     * @param $params
     */
    private function updateUserPhone($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_phone'])) {
            $this->utils->updateUserPhone($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_phone']
            );
        }
    }

    /**
     * Saves social security number from template params in user attributes
     * @param $paymentName
     * @param $userId
     * @param $params
     */
    private function updateUserSSN($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_socialsecuritynumber'])) {
            $this->utils->updateUserSSN($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_socialsecuritynumber']
            );
        }
    }

    /**
     * Saves annual salary from template params in user attributes
     * @param $paymentName
     * @param $userId
     * @param $params
     */
    private function updateUserAnnualSalary($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_annualsalary'])) {
            $this->utils->updateUserAnnualSalary($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_annualsalary']
            );
        }
    }

    /**
     * Saves bank info from template params in user attributes
     * @param $paymentName
     * @param $userId
     * @param $params
     */
    private function updateUserLastschriftBank($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_bank'])) {
            $this->utils->updateUserLastschriftBank($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_bank']
            );
        }
    }

    /**
     * Saves iban from template params in user attributes
     * @param $paymentName
     * @param $userId
     * @param $params
     */
    private function updateUserLastschriftIban($paymentName, $userId, $params)
    {
        $pluginConfig = Shopware()->Plugins()->Frontend()->FatchipCTPayment()->Config()->toArray();
        $isIbanAnon = $pluginConfig['lastschriftAnon'] == 'Aus' ? false : true;

        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_iban'])) {
            if (!$isIbanAnon) {
                $this->utils->updateUserLastschriftIban($userId,
                    $params['FatchipComputopPaymentData'][$paymentName . '_iban']
                );
            } elseif (preg_match('#XXXX#', $params['FatchipComputopPaymentData']['fatchip_computop_lastschrift_iban_anon'])) {
                $this->utils->updateUserLastschriftIban($userId,
                    $params['FatchipComputopPaymentData'][$paymentName . '_iban']
                );
            } else {
                $this->utils->updateUserLastschriftIban($userId,
                    $params['FatchipComputopPaymentData'][$paymentName . '_iban_anon']
                );
            }
        }
    }

    /**
     * Saves iban from template params in user attributes
     * @param $paymentName
     * @param $userId
     * @param $params
     */
    private function updateUserAfterpayInstallmentIban($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_iban'])) {
            $this->utils->updateUserAfterpayInstallmentIban($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_iban']
            );
        }
    }

    /**
     * Saves accountholder info from template params in user attributes
     * @param $paymentName
     * @param $userId
     * @param $params
     */
    private function updateUserLastschriftKontoinhaber($paymentName, $userId, $params)
    {
        if (!empty($params['FatchipComputopPaymentData'][$paymentName . '_kontoinhaber'])) {
            $this->utils->updateUserLastschriftKontoinhaber($userId,
                $params['FatchipComputopPaymentData'][$paymentName . '_kontoinhaber']
            );
        }
    }

    /**
     * Saves issuer info in session
     *
     * @param $paymentName
     * @param $params
     */
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

    /**
     * Saves Afterpay ProductNr in Session
     *
     * @param array $params
     */
    private function setAfterpayProductNrInSession($params)
    {
        $session = Shopware()->Session();
        if (!empty($params['FatchipComputopPaymentData']['fatchip_computop_afterpay_installment_productnr'])) {
            $session->offsetSet('FatchipComputopAfterpayProductNr',
                $params['FatchipComputopPaymentData']['fatchip_computop_afterpay_installment_productnr']
            );
        }
    }


    /**
     * Adds all.less to less definistion
     * @return LessDefinition
     */
    public function onThemeCompilerCollectPluginLess()
    {
        return new LessDefinition(
            [],
            [__DIR__ . '/../Views/frontend/_public/src/less/all.less']
        );
    }

    /** Duplicate methods from payment controller
     * to set pre-encrypted data into shippingpayment view
     * Helper function that creates a payment object
     * @return \Fatchip\CTPayment\CTPaymentMethodIframe
     */
    protected function getPaymentClassForGatewayAction()
    {

        $ctOrder = $this->createCTOrder();
        $router = Shopware()->Front()->Router();
        $payment = $this->paymentService->getIframePaymentClass(
            'CreditCard',
            $this->config,
            $ctOrder,
            $router->assemble(['controller' => 'FatchipCTCreditCard', 'action' => 'success', 'forceSecure' => true]),
            $router->assemble(['controller' => 'FatchipCTCreditCard', 'action' => 'failure', 'forceSecure' => true]),
            null,
            null,
            $this->getUserDataParam()
        );

        return $payment;
    }

    /**
     * Helper funciton to create a CTOrder object for the current order
     * @return CTOrder
     */
    protected function createCTOrder()
    {
        $basket = Shopware()->Modules()->Basket()->sGetBasket();
        $userData = $this->getUserData();
        $shippingCosts = Shopware()->Modules()->Admin()->sGetPremiumShippingcosts();

        $ctOrder = new CTOrder();
        $ctOrder->setAmount(($basket['AmountNumeric'] + $shippingCosts['brutto']) * 100);
        $ctOrder->setCurrency(Shopware()->Container()->get('currency')->getShortName());
        // try catch in case Address Splitter retrun exceptions
        try {
            $ctOrder->setBillingAddress($this->utils->getCTAddress($userData['billingaddress']));
            $ctOrder->setShippingAddress($this->utils->getCTAddress($userData['shippingaddress']));
        } catch (Exception $e) {
            $ctError = [];
            $ctError['CTErrorMessage'] = 'Bei der Verarbeitung Ihrer Adresse ist ein Fehler aufgetreten<BR>';
            $ctError['CTErrorCode'] = $e->getMessage();
            return $this->forward('shippingPayment', 'checkout', null, ['CTError' => $ctError]);
        }
        $ctOrder->setEmail($userData['additional']['user']['email']);
        $ctOrder->setCustomerID($userData['additional']['user']['id']);
        $ctOrder->setOrderDesc(Shopware()->Config()->shopName);
        return $ctOrder;
    }

    /**
     * gets userData array from OrderVars from Session
     * shoud be overridden in sublcasses if it is needed before an order exists
     * @return mixed
     */
    protected function getUserData()
    {
        $session = Shopware()->Session();
        $orderVars = $session->sOrderVariables;
        return $orderVars['sUserData'];
    }

    /**
     * Duplicate methods from payment controller
     * Sets the userData paramater for Computop calls to Shopware Version and Module Version
     * @return string
     */
    public function getUserDataParam()
    {
        return 'Shopware Version: ' . \Shopware::VERSION . ', Modul Version: ' . $this->plugin->getVersion();
    }

}
