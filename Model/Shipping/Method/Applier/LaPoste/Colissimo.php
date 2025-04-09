<?php

namespace ShoppingFeed\Colissimo\Model\Shipping\Method\Applier\LaPoste;

use LaPoste\Colissimo\Helper\Data as LpcHelper;
use LaPoste\Colissimo\Model\Carrier\Colissimo as ColissimoCarrier;
use LaPoste\Colissimo\Model\RelaysWebservice\RelaysApi as LpcRelayApi;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use ShoppingFeed\Colissimo\Helper\Country as CountryHelper;
use ShoppingFeed\Colissimo\Model\Shipping\Method\Applier\Config\LaPoste\ColissimoInterface as ConfigInterface;
use ShoppingFeed\Manager\Api\Data\Marketplace\Order\AddressInterface as MarketplaceAddressInterface;
use ShoppingFeed\Manager\Api\Data\Marketplace\OrderInterface as MarketplaceOrderInterface;
use ShoppingFeed\Manager\Model\Shipping\Method\AbstractApplier;
use ShoppingFeed\Manager\Model\Shipping\Method\Applier\Result;
use ShoppingFeed\Manager\Model\Shipping\Method\Applier\ResultFactory;

/**
 * @method ConfigInterface getConfig()
 */
class Colissimo extends AbstractApplier
{
    const LPC_API_ENDPOINT_FIND_PICKUP_POINT_BY_ID = 'findPointRetraitAcheminementByID';

    const CONFIG_PATH_LPC_CONNECTION_MODE = 'lpc_general/connectionMode';
    const CONFIG_PATH_LPC_API_KEY = 'lpc_general/api_key';
    const CONFIG_PATH_LPC_ACCOUNT_NUMBER = 'lpc_general/id_webservices';
    const CONFIG_PATH_LPC_ACCOUNT_PASSWORD = 'lpc_general/pwd_webservices';
    const CONFIG_PATH_LPC_PARENT_ACCOUNT_ID = 'lpc_general/parent_id_webservices';
    const CONFIG_PATH_LPC_PREPARATION_DELAY = 'lpc_labels/averagePreparationDelay';

    const SESSION_KEY_LPC_PICKUP_POINT_DATA = 'lpc_relay_information';

    const COUNTRY_PICKUP_NETWORK_CODES = [
        'BE' => [ 'R12' ],
        'DE' => [ 'R03', 'X00' ],
        'ES' => [ 'R03', 'X00' ],
        'GB' => [ 'R03' ],
        'LU' => [ 'R03' ],
        'NL' => [ 'R03', 'X00' ],
    ];

    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var LpcHelper
     */
    private $lpcHelper;

    /**
     * @var CountryHelper
     */
    private $countryHelper;

    /**
     * @param ConfigInterface $config
     * @param ResultFactory $resultFactory
     * @param DataObjectFactory $dataObjectFactory
     * @param CheckoutSession $checkoutSession
     * @param LpcHelper $lpcHelper
     * @param CountryHelper $countryHelper
     */
    public function __construct(
        ConfigInterface $config,
        ResultFactory $resultFactory,
        DataObjectFactory $dataObjectFactory,
        CheckoutSession $checkoutSession,
        LpcHelper $lpcHelper,
        CountryHelper $countryHelper
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->checkoutSession = $checkoutSession;
        $this->lpcHelper = $lpcHelper;
        $this->countryHelper = $countryHelper;
        parent::__construct($config, $resultFactory);
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return 'LaPoste Colissimo';
    }

    /**
     * @param string $countryId
     * @return bool
     */
    private function isPickupDeliveryAvailableForCountry($countryId)
    {
        return ('FR' === $countryId) || isset(static::COUNTRY_PICKUP_NETWORK_CODES[$countryId]);
    }

    /**
     * @param string $pickupPointId
     * @param string $countryId
     * @param int $storeId
     * @return DataObject|null
     */
    private function getPickupPointDataById($pickupPointId, $countryId, $storeId)
    {
        $pickupPointData = null;
        $networkCodes = ('FR' === $countryId) ? [ null ] : static::COUNTRY_PICKUP_NETWORK_CODES[$countryId];

        $payload = [
            'id' => $pickupPointId,
            'langue' => $countryId,
        ];

        $soapClient = new \SoapClient(LpcRelayApi::API_RELAYS_WSDL_URL);

        $connectionMode = $this->lpcHelper->getAdvancedConfigValue(
            static::CONFIG_PATH_LPC_CONNECTION_MODE
        );

        if ('api' === $connectionMode) {
            $payload['apikey'] = $this->lpcHelper->getAdvancedConfigValue(
                static::CONFIG_PATH_LPC_API_KEY
            );
        } else {
            $payload['password'] = $this->lpcHelper->getAdvancedConfigValue(
                static::CONFIG_PATH_LPC_ACCOUNT_PASSWORD
            );

            $payload['accountNumber'] = $this->lpcHelper->getAdvancedConfigValue(
                static::CONFIG_PATH_LPC_ACCOUNT_NUMBER
            );
        }

        $parentAccountId = $this->lpcHelper->getAdvancedConfigValue(
            static::CONFIG_PATH_LPC_PARENT_ACCOUNT_ID
        );

        if (!empty($parentAccountId)) {
            $payload['codTiersPourPartenaire'] = $parentAccountId;
        }

        $preparationDelay = (int) $this->lpcHelper->getAdvancedConfigValue(
            static::CONFIG_PATH_LPC_PREPARATION_DELAY
        );

        $estimatedShippingDate = new \DateTime();
        $estimatedShippingDate->add(new \DateInterval('P' . $preparationDelay . 'D'));

        $payload['date'] = $estimatedShippingDate->format('d/m/Y');

        foreach ($networkCodes as $networkCode) {
            try {
                $payload['reseau'] = $networkCode;

                $result = $soapClient->__soapCall(
                    static::LPC_API_ENDPOINT_FIND_PICKUP_POINT_BY_ID,
                    [ array_filter($payload) ]
                );

                $result = (array) json_decode(json_encode($result), true);
                $name = trim($result['return']['pointRetraitAcheminement']['nom'] ?? '');
                $type = trim($result['return']['pointRetraitAcheminement']['typeDePoint'] ?? '');;

                if (!empty($name) && !empty($type)) {
                    $pickupPointData = $this->dataObjectFactory->create();

                    $pickupPointData->setData(
                        [
                            'name' => $name,
                            'type' => $type,
                        ]
                    );

                    break;
                }
            } catch (\Exception $e) {
                $pickupPointData = null;
            }
        }

        return $pickupPointData;
    }

    /**
     * @param MarketplaceAddressInterface $address
     * @return string|null
     */
    private function getAddressPickupPointId(MarketplaceAddressInterface $address)
    {
        return (
            empty($pickupPointId = trim($address->getRelayPointId()))
            && empty($pickupPointId = trim($address->getMiscData()))
        )
            ? null
            : $pickupPointId;
    }

    public function applyToQuoteShippingAddress(
        MarketplaceOrderInterface $marketplaceOrder,
        MarketplaceAddressInterface $marketplaceShippingAddress,
        QuoteAddress $quoteShippingAddress,
        DataObject $configData
    ) {
        $config = $this->getConfig();

        $methodCode = null;
        $storeId = $quoteShippingAddress->getQuote()->getStoreId();
        $countryId = strtoupper($quoteShippingAddress->getCountryId());
        $postcode = trim($quoteShippingAddress->getPostcode());

        $additionalData = [];
        $availableProducts = $this->countryHelper->getAvailableProductsForDestination($countryId, $postcode);

        if ($config->isPickupPointDeliveryEnabled($configData)
            && $this->isPickupDeliveryAvailableForCountry($countryId)
            && isset($availableProducts[ColissimoCarrier::CODE_SHIPPING_METHOD_RELAY])
            && !empty($pickupPointId = $this->getAddressPickupPointId($marketplaceShippingAddress))
            && ($pickupPointData = $this->getPickupPointDataById($pickupPointId, $countryId, $storeId))
        ) {
            $additionalData['product_code'] = $pickupPointData->getData('type');
            $additionalData['pickup_point_id'] = $pickupPointId;
            $additionalData['pickup_point_name'] = $pickupPointData->getData('name');
            $methodCode = ColissimoCarrier::CODE_SHIPPING_METHOD_RELAY;
        }

        if (empty($methodCode)) {
            $homeDeliveryMethodCodes = [];

            if ($config->isHomeDeliveryWithoutSignatureEnabled($configData)) {
                $homeDeliveryMethodCodes[] = ColissimoCarrier::CODE_SHIPPING_METHOD_DOMICILE_SS;
            }

            if ($config->isHomeDeliveryWithSignatureEnabled($configData)) {
                $homeDeliveryMethodCodes[] = ColissimoCarrier::CODE_SHIPPING_METHOD_DOMICILE_AS;
            }

            if ($config->isInternationalExpertDeliveryEnabled($configData)) {
                $homeDeliveryMethodCodes[] = ColissimoCarrier::CODE_SHIPPING_METHOD_EXPERT;
            }

            foreach ($homeDeliveryMethodCodes as $testedMethodCode) {
                if (isset($availableProducts[$testedMethodCode])) {
                    $methodCode = $testedMethodCode;

                    try {
                        $additionalData['product_code'] = $this->countryHelper->getProductCodeForDestination(
                            $methodCode,
                            $countryId,
                            $postcode,
                            false
                        );

                        break;
                    } catch (\Exception $e) {
                    }
                }
            }
        }

        $result = null;

        if (!empty($methodCode)) {
            $result = $this->applyCarrierMethodToQuoteShippingAddress(
                ColissimoCarrier::CODE,
                $methodCode,
                $marketplaceOrder,
                $quoteShippingAddress,
                $configData
            );
        }

        if ($result instanceof Result) {
            $result->setAdditionalData($additionalData);
        }

        return $result;
    }

    public function commitOnQuoteShippingAddress(
        QuoteAddress $quoteShippingAddress,
        Result $result,
        DataObject $configData
    ) {
        $this->checkoutSession->setData(static::SESSION_KEY_LPC_PICKUP_POINT_DATA, []);

        if ($result->getMethodCode() === ColissimoCarrier::CODE_SHIPPING_METHOD_RELAY) {
            $additionalData = $result->getAdditionalData();

            if (isset($additionalData['pickup_point_id']) && isset($additionalData['product_code'])) {
                // The Colissimo module will not apply the pickup point data if any of the values is empty.
                $company = trim($quoteShippingAddress->getCompany());

                if (empty($company)) {
                    if (isset($additionalData['pickup_point_name'])) {
                        $company = $additionalData['pickup_point_name'];
                    } else {
                        $company = '__';
                    }
                }

                $this->checkoutSession->setData(
                    static::SESSION_KEY_LPC_PICKUP_POINT_DATA,
                    [
                        'id' => $additionalData['pickup_point_id'],
                        'type' => $additionalData['product_code'],
                        'name' => $company,
                        'address' => $quoteShippingAddress->getStreet(),
                        'post_code' => $quoteShippingAddress->getPostcode(),
                        'city' => $quoteShippingAddress->getCity(),
                        'country' => $quoteShippingAddress->getCountryId(),
                    ]
                );
            }
        }

        return $this;
    }
}
