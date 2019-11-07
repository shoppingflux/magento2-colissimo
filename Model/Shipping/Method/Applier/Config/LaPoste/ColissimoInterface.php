<?php

namespace ShoppingFeed\Colissimo\Model\Shipping\Method\Applier\Config\LaPoste;

use Magento\Framework\DataObject;
use ShoppingFeed\Manager\Model\Shipping\Method\Applier\ConfigInterface;

interface ColissimoInterface extends ConfigInterface
{
    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isPickupPointDeliveryEnabled(DataObject $configData);

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isHomeDeliveryWithoutSignatureEnabled(DataObject $configData);

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isHomeDeliveryWithSignatureEnabled(DataObject $configData);

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isInternationalExpertDeliveryEnabled(DataObject $configData);
}
