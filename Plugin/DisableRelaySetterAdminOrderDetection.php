<?php

namespace ShoppingFeed\Colissimo\Plugin;

use LaPoste\Colissimo\Observer\SetRelayInformationOrder;
use Magento\Framework\Event\Observer;

class DisableRelaySetterAdminOrderDetection
{
    public function aroundExecute(SetRelayInformationOrder $subject, callable $proceed, Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $hasReplacedOrderRemoteIp = false;

        // The Colissimo module does not use the relay data of the checkout session if the order has no remote IP.
        if ($order && ($order->getRemoteIp() === null)) {
            $order->setRemoteIp('127.0.0.1');
            $hasReplacedOrderRemoteIp = true;
        }

        $result = $proceed($observer);

        if ($hasReplacedOrderRemoteIp) {
            $order->setRemoteIp(null);
        }

        return $result;
    }
}