<?php

namespace ShoppingFeed\Colissimo\Helper;

use LaPoste\Colissimo\Helper\CountryOffer as LpcCountryHelper;

class Country extends LpcCountryHelper
{
    const KEY_HOME_WITH_SIGNATURE = 'home_with_signature';
    const KEY_HOME_WITHOUT_SIGNATURE = 'home_without_signature';
    const KEY_PICKUP_POINT = 'pickup_point';
    const KEY_INTERNATIONAL_EXPERT = 'international_expert';

    /**
     * @param string $countryId
     * @param string $postcode
     * @return array
     */
    public function getAvailableProductsForDestination($countryId, $postcode)
    {
        return array_filter($this->getProductInfoForDestination($countryId, $postcode));
    }
}
