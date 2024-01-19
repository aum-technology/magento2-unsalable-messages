<?php

declare(strict_types=1);

namespace AumTechnology\Unsaleable\Model;

class UserMessages
{
    public $scopeConfig;
    public $storeManager;
    public $storeId;
    const CART_ITEM_SPECIFIC_MESSAGE = 'unsaleable/messages/cart_item_specific_message';
    const CART_SPECIFIC_MESSAGE_FOR_LOWER_QTY = 'unsaleable/messages/cart_specific_message_for_lower_qty';
    const CART_ITEM_OUT_OF_STOCK = 'unsaleable/messages/cart_item_out_of_stock';
    const REUPDATE_CART = 'unsaleable/messages/reupdate_cart';
    const CART_MESSAGE_FOR_OUT_OF_STOCK = 'unsaleable/messages/cart_message_for_out_of_stock';

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->storeId = $this->storeManager->getStore()->getId();
    }
    public function getUserMessage(string $configPath, $pattern = "", $variable = null, $scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId = null)
    {
        return str_replace($pattern, (string)(gettype($variable) == "array" ? implode(', ', $variable) : $variable), (string)__($this->scopeConfig->getValue(
            $configPath,
            $scope,
            $storeId ?? $this->storeId
        )));
    }
}
