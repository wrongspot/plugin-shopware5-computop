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

class CheckoutFilterPayments implements SubscriberInterface
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
        $subject = $args->getSubject();
        $view = $subject->View();
        $request = $subject->Request();
        $response = $subject->Response();

        if (!$request->isDispatched() || $response->isException()) {
            return;
        }

        if ($request->getActionName() == 'shippingPayment') {

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
                    $klarnaInstallmentIndex = $index;
                }
            }

            // AmazonPay and Paypal Express are never shown in payment list
            unset ($payments[$amazonPayIndex]);
            unset ($payments[$paypalExpressIndex]);


            $userData = Shopware()->Modules()->Admin()->sGetUserData();
            if ($this->utils->isKlarnaBlocked($userData)) {
                unset ($payments[$klarnaInvoiceIndex]);
                unset ($payments[$klarnaInstallmentIndex]);
            }

            $view->assign('sPayments', $payments);
        }
    }
}
