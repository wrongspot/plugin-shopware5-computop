<?php

namespace Shopware\Plugins\FatchipCTPayment\Subscribers;

use Enlight\Event\SubscriberInterface;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Plugins\FatchipCTPayment\Util;
use Shopware\Models\Customer\Address;


class FrontendRiskAfterAddressUpdate implements SubscriberInterface
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
        // $this->>container->get doesnt work here?
        $this->utils = Shopware()->Container()->get('FatchipCTPaymentUtils');
    }

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware\Models\Customer\Address::postUpdate' => 'afterAddressUpdate',
        ];
    }

    /***
     * @param \Enlight_Hook_HookArgs $args
     *
     * Fired after a user updates an address in SW >=5.2 If a CRIF result is available, it will be
     * deleted
     */
    public function afterAddressUpdate(\Enlight_Hook_HookArgs $args)
    {
        if (!$this->utils->addressWasAutoUpdated()) {
            /** @var Address $address */
            $address = $args->getEntity();
            $this->utils->deleteCrifFResult($address);
        }
    }
}
