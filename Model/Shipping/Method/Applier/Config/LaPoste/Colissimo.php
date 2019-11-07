<?php

namespace ShoppingFeed\Colissimo\Model\Shipping\Method\Applier\Config\LaPoste;

use Magento\Framework\DataObject;
use ShoppingFeed\Manager\Model\Config\Field\Checkbox;
use ShoppingFeed\Manager\Model\Shipping\Method\Applier\AbstractConfig;

class Colissimo extends AbstractConfig implements ColissimoInterface
{
    const KEY_IS_PICKUP_POINT_DELIVERY_ENABLED = 'is_pickup_point_delivery_enabled';
    const KEY_IS_HOME_DELIVERY_WITHOUT_SIGNATURE_ENABLED = 'is_home_delivery_without_signature_enabled';
    const KEY_IS_HOME_DELIVERY_WITH_SIGNATURE_ENABLED = 'is_home_delivery_with_signature_enabled';
    const KEY_IS_INTERNATIONAL_EXPERT_DELIVERY_ENABLED = 'is_international_expert_delivery_enabled';

    protected function getBaseFields()
    {
        $homeDeliveryCheckedNotice = __(
            'Applied when the shipping address is compatible. Enabled options above have priority.'
        );

        $homeDeliveryUncheckedNotice = __(
            'Enable to apply when the shipping address is compatible. Enabled options above have priority.'
        );

        return array_merge(
            array(
                $this->fieldFactory->create(
                    Checkbox::TYPE_CODE,
                    [
                        'name' => self::KEY_IS_PICKUP_POINT_DELIVERY_ENABLED,
                        'isRequired' => true,
                        'label' => __('Enable Pickup Point Delivery'),
                        'checkedNotice' => __('Applied when a valid pickup point ID is available.'),
                        'uncheckedNotice' => __('Enable to apply when a valid pickup point ID is available.'),
                        'isCheckedByDefault' => true,
                        'sortOrder' => 100,
                    ]
                ),
                $this->fieldFactory->create(
                    Checkbox::TYPE_CODE,
                    [
                        'name' => self::KEY_IS_HOME_DELIVERY_WITHOUT_SIGNATURE_ENABLED,
                        'isRequired' => true,
                        'label' => __('Enable Home Delivery Without Signature'),
                        'checkedNotice' => $homeDeliveryCheckedNotice,
                        'uncheckedNotice' => $homeDeliveryUncheckedNotice,
                        'isCheckedByDefault' => true,
                        'sortOrder' => 200,
                    ]
                ),
                $this->fieldFactory->create(
                    Checkbox::TYPE_CODE,
                    [
                        'name' => self::KEY_IS_HOME_DELIVERY_WITH_SIGNATURE_ENABLED,
                        'isRequired' => true,
                        'label' => __('Enable Home Delivery With Signature'),
                        'checkedNotice' => $homeDeliveryCheckedNotice,
                        'uncheckedNotice' => $homeDeliveryUncheckedNotice,
                        'isCheckedByDefault' => true,
                        'sortOrder' => 300,
                    ]
                ),
                $this->fieldFactory->create(
                    Checkbox::TYPE_CODE,
                    [
                        'name' => self::KEY_IS_INTERNATIONAL_EXPERT_DELIVERY_ENABLED,
                        'isRequired' => true,
                        'label' => __('Enable International Expert Delivery'),
                        'checkedNotice' => $homeDeliveryCheckedNotice,
                        'uncheckedNotice' => $homeDeliveryUncheckedNotice,
                        'isCheckedByDefault' => true,
                        'sortOrder' => 400,
                    ]
                ),
            ),
            parent::getBaseFields()
        );
    }

    /**
     * @return string
     */
    protected function getBaseDefaultCarrierTitle()
    {
        return 'LaPoste';
    }

    /**
     * @return string
     */
    protected function getBaseDefaultMethodTitle()
    {
        return 'Colissimo';
    }

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isPickupPointDeliveryEnabled(DataObject $configData)
    {
        return (bool) $this->getFieldValue(static::KEY_IS_PICKUP_POINT_DELIVERY_ENABLED, $configData);
    }

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isHomeDeliveryWithoutSignatureEnabled(DataObject $configData)
    {
        return (bool) $this->getFieldValue(static::KEY_IS_HOME_DELIVERY_WITHOUT_SIGNATURE_ENABLED, $configData);
    }

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isHomeDeliveryWithSignatureEnabled(DataObject $configData)
    {
        return (bool) $this->getFieldValue(static::KEY_IS_HOME_DELIVERY_WITH_SIGNATURE_ENABLED, $configData);
    }

    /**
     * @param DataObject $configData
     * @return bool
     */
    public function isInternationalExpertDeliveryEnabled(DataObject $configData)
    {
        return (bool) $this->getFieldValue(static::KEY_IS_INTERNATIONAL_EXPERT_DELIVERY_ENABLED, $configData);
    }
}
