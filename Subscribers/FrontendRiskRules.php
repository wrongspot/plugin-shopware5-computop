<?php

namespace Shopware\Plugins\FatchipCTPayment\Subscribers;

use Enlight\Event\SubscriberInterface;
use Fatchip\CTPayment\CTOrder\CTOrder;
use Fatchip\CTPayment\CTCrif\CRIF;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Plugins\FatchipCTPayment\Util;

/**
 * Class AddressCheck
 *
 * @package Shopware\Plugins\MoptPaymentPayone\Subscribers
 */
class FrontendRiskRules implements SubscriberInterface
{

    /**
     * di container
     *
     * @var Container
     */
    private $container;

    /** @var Util $utils * */
    private $utils;

    private $service;

    private $plugin;

    private $config;

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
            'sAdmin::executeRiskRule::replace' => 'onExecuteRiskRule',
        ];
    }

    /**
     * handle rules beginning with 'sRiskFATCHIP_COMPUTOP__'
     * returns true if risk condition is fulfilled
     * arguments: $rule, $user, $basket, $value
     *
     * * @param \Enlight_Hook_HookArgs $arguments
     */
    public function onExecuteRiskRule(\Enlight_Hook_HookArgs $arguments)
    {
        $rule = $arguments->get('rule');
        //$value contains the value that we want to compare with, as set in the SW Riskmanagement Backend Rule
        $value = $arguments->get('value');
        $basket = $arguments->get('basket');
        $user = $arguments->get('user');

        // execute parent call if rule is not Computop
        if (!$this->isComputopRiskRule($rule)) {
            $arguments->setReturn(
                $arguments->getSubject()->executeParent(
                    $arguments->getMethod(),
                    $arguments->getArgs()
                )
            );
        } else {
            $this->utils = $this->container->get('FatchipCTPaymentUtils');
            $this->service = $this->container->get('FatchipCTPaymentApiClient');
            $this->plugin = Shopware()->Plugins()->Frontend()->FatchipCTPayment();
            $this->config = $this->plugin->Config()->toArray();

            $test3 = !$this->isCrifActive($this->config);
            $test4 = !$this->isUserAvailable($user);
            if (!$this->isCrifActive($this->config) || !$this->isUserAvailable($user)) {
                $arguments->setReturn(FALSE);
                return;
            }

            if ($this->crifCheckNecessary($user['billingaddress'], 'billing')) {

                $billingAddressData = $user['billingaddress'];
                $billingAddressData['country'] = $billingAddressData['countryId'];
                $shippingAddressData = $user['shippingaddress'];
                $shippingAddressData['country'] = $billingAddressData['countryId'];

                $ctOrder = new CTOrder();
                $ctOrder->setAmount($basket['AmountNumeric'] * 100);
                $ctOrder->setCurrency(Shopware()->Container()->get('currency')->getShortName());
                $ctOrder->setBillingAddress($this->utils->getCTAddress($user['billingaddress']));
                $ctOrder->setShippingAddress($this->utils->getCTAddress($user['shippingaddress']));
                $ctOrder->setEmail($user['additional']['user']['email']);
                $ctOrder->setCustomerID($user['additional']['user']['id']);

                //TODO: Set orderDesc and Userdata
                /** @var CRIF $crif */
                $crif = $this->service->getCRIFClass($this->config, $ctOrder, 'testOrder', 'testUserData');
                $crifParams = $crif->getRedirectUrlParams();
                $crifResponse = $this->plugin->callComputopCRIFService($crifParams, $crif, 'CRIF', $crif->getCTPaymentURL());

                $status = $crifResponse->getStatus();
                $callResult = $crifResponse->getResult();
                //write the result to the session for this billingaddressID
                $crifInformation[$billingAddressData['id']] = $this->getCRIFResponseArray($crifResponse);
                //and save the resul in the billingaddress
                $this->utils->saveCRIFResultInAddress($billingAddressData['id'], 'billing', $crifResponse);
                //$util->saveCRIFResultInAddress($shippingAddressData['id'], 'shipping', $crifResponse);
                if ($this->config['bonitaetusereturnaddress']) {
                    $this->updateBillingAddressFromCrifResponse($billingAddressData['id'], $crifResponse);
                }

            } else {
                $callResult = $this->getCrifResultFromAddressArray($user['billingaddress']);
            }

            if ($this->$rule($callResult, $value)) {
                $arguments->setReturn(TRUE);
                return;
            }
        }
    }

    private function isComputopRiskRule($rule)
    {
        return (strpos($rule, 'sRiskFATCHIP_COMPUTOP__') === 0);
    }

    private function isCrifActive($config)
    {
        $test1 = isset($config['crifmethod']);
        $test2 = $config['crifmethod'] !== 'inactive';
        return isset($config['crifmethod']) && $config['crifmethod'] !== 'inactive';
    }

    private function isUserAvailable($user)
    {
        $userId = $user['additional']['user']['id'] ? $user['additional']['user']['id'] : null;
        return !empty($userId);
    }

    private function getCRIFResponseArray($crifResponseObject)
    {
        $crifResponseArray = array();
        $crifResponseArray['Code'] = $crifResponseObject->getCode();
        $crifResponseArray['Description'] = $crifResponseObject->getDescription();
        $crifResponseArray['result'] = $crifResponseObject->getResult();
        $crifResponseArray['status'] = $crifResponseObject->getStatus();

        return $crifResponseArray;
    }

    /***
     * @param $addressArray
     * @param null $type : billing or shipping
     * @return bool
     */
    private function crifCheckNecessary($addressArray, $type = null)
    {

        $crifStatus = $this->getCrifStatusFromAddressArray($addressArray);
        $crifDate = $this->getCrifDateFromAddressArray($addressArray);
        $crifResult = $this->getCrifResultFromAddressArray($addressArray);

        //check in Session if CRIF data are missing.
        if (!isset($crifResult)) {
            //If it is not in the session, we also check in the database to prevent multiple calls
            if (isset($addressArray['id'])) {
                $address = $this->utils->getCustomerAddressById($addressArray['id'], $type);
                if (!empty($address) && $attribute = $address->getAttribute()) {
                    $attributeData = Shopware()->Models()->toArray($address->getAttribute());
                    //in attributeData there are NO underscores in attribute names and Shopware ads CamelCase after fcct prefix
                    if (!isset($attributeData['fcctCrifresult']) || !isset($attributeData['fcctCrifdate'])) {
                        return true;
                    } else {
                        //write the values from the database in the addressarray
                        $addressArray['attribute']['fcct_crifresult'] = $attributeData['fcctCrifresult'];
                        $addressArray['attribute']['fcct_crifdate'] = $attributeData['fcctCrifdate'];
                    }
                } else {
                    return false;
                }
            }
        }

        //if CRIF data IS saved in both addresses, check if the are expired,
        //that means, they are older then the number of days set in Pluginsettings
        $invalidateAfterDays = $this->config['bonitaetinvalidateafterdays'];
        if (is_numeric($invalidateAfterDays) && $invalidateAfterDays > 0) {
            /** @var \DateTime $lastTimeChecked */
            $lastTimeChecked = $this->getCrifDateFromAddressArray($addressArray);

            $daysPassed = $lastTimeChecked->diff(new \DateTime('now'), true)->days;

            if ($daysPassed > $invalidateAfterDays) {
                return true;
            }
        }
        return false;
    }

    private function getCrifStatusFromAddressArray($aAddress)
    {
        if (array_key_exists('fatchipct_crifstatus', $aAddress['attributes'])) {
            // SW 5.2, SW 5.3, SW 5.4
            return $aAddress['attributes']['fatchipct_crifstatus'];
        } else if (array_key_exists('fatchipctCrifstatus', $aAddress)) {
            // SW 5.0, 5.1
            return $aAddress['fatchipctCrifstatus'];
        }
        return null;
    }

    private function getCrifResultFromAddressArray($aAddress)
    {
        if (array_key_exists('fatchipct_crifresult', $aAddress['attributes'])) {
            // SW 5.2, SW 5.3, SW 5.4
            return $aAddress['attributes']['fatchipct_crifresult'];
        } else if (array_key_exists('fatchipctCrifresult', $aAddress)) {
            // SW 5.0, 5.1
            return $aAddress['fatchipctCrifresult'];
        }
        return null;
    }

    private function getCrifDateFromAddressArray($aAddress)
    {
        if (array_key_exists('fatchipct_crifdate', $aAddress['attributes'])) {
            // SW 5.2, SW 5.3, SW 5.4
            return $aAddress['attributes']['fatchipct_crifdate'] instanceof \DateTime ?
                $aAddress['attributes']['fatchipct_crifdate'] : new \DateTime($aAddress['attributes']['fatchipct_crifdate']);
        } else if (array_key_exists('fatchipctCrifdate', $aAddress)) {
            // SW 5.0, 5.1
            return $aAddress['fatchipctCrifdate'] instanceof \DateTime ?
                $aAddress['fatchipctCrifdate'] : new \DateTime($aAddress['fatchipctCrifdate']);
        }
        return null;
    }

    /**
     * check if user score equals configured score to block payment method
     *
     * @param $scoring
     * @param $value
     * @return bool
     */
    public function sRiskFATCHIP_COMPUTOP__TRAFFIC_LIGHT_IS($scoring, $value)
    {
        return $scoring == $value; //return true if payment has to be denied
    }

    /**
     * check if user score equals not configured score to block payment method
     *
     * @param $scoring
     * @param $value
     * @return bool
     */
    public function sRiskFATCHIP_COMPUTOP__TRAFFIC_LIGHT_IS_NOT($scoring, $value)
    {
        return !$this->sRiskFATCHIP_COMPUTOP__TRAFFIC_LIGHT_IS($scoring, $value);
    }

    /***
     * @param $addressID
     * @param $crifResponse CTResponse
     */
    private function updateBillingAddressFromCrifResponse($addressID, $crifResponse)
    {
        if ($address = $this->utils->getCustomerAddressById($addressID, 'billing')) {
            //only update the address, if something changed. This check is important, because if nothing changed
            //callin persist and flush does not result in calling afterAddressUpdate and the session variable
            //fatchipComputopCrifAutoAddressUpdate woould not get cleared.
            if ($address->getFirstName() !== $crifResponse->getFirstName() ||
                $address->getLastName() !== $crifResponse->getLastName() ||
                $address->getStreet() != $crifResponse->getAddrStreet() . ' ' . $crifResponse->getAddrStreetNr() ||
                $address->getZipCode() !== $crifResponse->getAddrZip() ||
                $address->getCity() !== $crifResponse->getAddrCity()
            ) {
                $address->setFirstName($crifResponse->getFirstName());
                $address->setLastName($crifResponse->getLastName());
                $address->setStreet($crifResponse->getAddrStreet() . ' ' . $crifResponse->getAddrStreetNr());
                $address->setCity($crifResponse->getAddrCity());
                $address->setZipcode($crifResponse->getAddrZip());
                //TODO: country

                //Write to session that this address is autmatically changed, so we do not fire a second CRIF request
                $session = Shopware()->Session();
                $session->offsetSet('fatchipComputopCrifAutoAddressUpdate', $addressID);

                Shopware()->Models()->persist($address);
                Shopware()->Models()->flush();

            }
        }
    }
}