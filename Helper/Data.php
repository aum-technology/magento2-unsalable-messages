<?php

namespace AumTechnology\Unsaleable\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const MODULE_STATUS = "unsaleable/general/module_status";
    public $scopeConfig;
    public $storeManager;
    public $storeId;
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->storeId = $this->storeManager->getStore()->getId();
    }
    public function getModuleStatus()
    {
        return $this->scopeConfig->getValue(
            self::MODULE_STATUS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $this->storeId
        );
    }
}
