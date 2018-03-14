<?php

namespace Shopware\Plugins\FatchipCTPayment\Subscribers;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Plugins\FatchipCTPayment\Util;


class FrontendRiskOnValidate implements SubscriberInterface
{

    /**
     * di container
     *
     * @var Container
     */
    private $container;

    /** @var Util $utils * */
    private $utils;

    /**
     * inject di container
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Modules_Admin_ValidateStep2Shipping_FilterResult' => 'onValidateStep2ShippingAddress',
            'Shopware_Modules_Admin_ValidateStep2_FilterResult' => 'onValidateStep2BillingAddress',
        ];
    }

    public function onValidateStep2BillingAddress(\Enlight_Hook_HookArgs $arguments)
    {
        $this->utils = $this->container->get('FatchipCTPaymentUtils');
        if (!$this->utils->addressWasAutoUpdated()) {
            $orderVars = Shopware()->Session()->sOrderVariables;
            $userData = $orderVars['sUserData'];
            $oldBillingAddress = $userData['billingaddress'];
            $customerBillingId = $userData['billingaddress']['customerBillingId'];

            //postdata contains the address the user just entered
            $postData = $arguments->get('post');

            if (!empty($customerBillingId) && $this->utils->addressChanged($postData, $oldBillingAddress)) {
                $this->utils->deleteCrifResultForId($customerBillingId, 'billing');
            }
        }
    }

    public function onValidateStep2ShippingAddress(\Enlight_Hook_HookArgs $arguments)
    {
        $this->utils = $this->container->get('FatchipCTPaymentUtils');
        if (!$this->utils->addressWasAutoUpdated()) {
            $orderVars = Shopware()->Session()->sOrderVariables;
            $userData = $orderVars['sUserData'];
            $oldShippingAddress = $userData['shippingaddress'];
            $customerShippingId = $userData['shippingaddress']['customerShippingId'];

            //postdata contains the new addressdata that the user just entered
            $postData = $arguments->get('post');

            if (!empty($customerShippingId) && $this->utils->addressChanged($postData, $oldShippingAddress)) {
                $this->deleteCrifResultForId($customerShippingId, 'shipping');
            }
        }
    }
}
