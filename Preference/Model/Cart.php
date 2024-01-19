<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace AumTechnology\Unsaleable\Preference\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Shopping cart model
 *
 * @api
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @deprecated 100.1.0 Use \Magento\Quote\Model\Quote instead
 * @see \Magento\Quote\Api\Data\CartInterface
 * @since 100.0.2
 */
class Cart extends \Magento\Checkout\Model\Cart
{
    /**
     * Shopping cart items summary quantity(s)
     *
     * @var int|null
     */
    protected $_summaryQty;

    /**
     * List of product ids in shopping cart
     *
     * @var int[]|null
     */
    protected $_productIds;

    /**
     * Core event manager proxy
     *
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $_eventManager;

    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Checkout\Model\ResourceModel\Cart
     */
    protected $_resourceCart;

    /**
     * @var Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var \Magento\CatalogInventory\Api\StockStateInterface
     */
    protected $stockState;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    protected $moduleHelper;
    protected $moduleStatus;
    /**
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Checkout\Model\ResourceModel\Cart $resourceCart
     * @param Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\CatalogInventory\Api\StockStateInterface $stockState
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param ProductRepositoryInterface $productRepository
     * @param array $data
     * @codeCoverageIgnore
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\ResourceModel\Cart $resourceCart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\CatalogInventory\Api\StockStateInterface $stockState,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        ProductRepositoryInterface $productRepository,
        \AumTechnology\Unsaleable\Helper\Data $moduleHelper
    ) {
        $this->_eventManager = $eventManager;
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->_resourceCart = $resourceCart;
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->messageManager = $messageManager;
        $this->stockRegistry = $stockRegistry;
        $this->stockState = $stockState;
        $this->quoteRepository = $quoteRepository;
        parent::__construct($eventManager, $scopeConfig, $storeManager, $resourceCart, $checkoutSession, $customerSession, $messageManager, $stockRegistry, $stockState, $quoteRepository, $productRepository);
        $this->productRepository = $productRepository;
        $this->moduleHelper = $moduleHelper;
        $this->moduleStatus = $this->moduleHelper->getModuleStatus();
    }

    /**
     * Update cart items information
     *
     * @param  array $data
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function updateItems($data)
    {
        if ($this->moduleStatus) {
            $infoDataObject = new \Magento\Framework\DataObject($data);
            $this->_eventManager->dispatch(
                'checkout_cart_update_items_before',
                ['cart' => $this, 'info' => $infoDataObject]
            );

            $qtyRecalculatedFlag = false;
            foreach ($data as $itemId => $itemInfo) {
                $item = $this->getQuote()->getItemById($itemId);
                if (!$item) {
                    continue;
                }

                if (!empty($itemInfo['remove']) || isset($itemInfo['qty']) && $itemInfo['qty'] == '0') {
                    $this->removeItem($itemId);
                    continue;
                }

                $qty = isset($itemInfo['qty']) ? (float)$itemInfo['qty'] : false;
                if ($qty > 0) {
                    $item->clearMessage();
                    $item->setHasError(false);
                    $item->setQty($qty);

                    if ($item->getHasError()) {
                        $errorInfos = $item->getErrorInfos();
                        foreach ($errorInfos as $key => $error) {
                            if ($error['origin'] == 'cataloginventory') {
                                unset($errorInfos[$key]);
                            }
                        }
                        if (empty($errorInfos)) {
                            throw new \Magento\Framework\Exception\LocalizedException(__(json_encode(['inventory_error' => true])));
                        } else {
                            throw new \Magento\Framework\Exception\LocalizedException(__($item->getMessage()));
                        }
                    }

                    if (isset($itemInfo['before_suggest_qty']) && $itemInfo['before_suggest_qty'] != $qty) {
                        $qtyRecalculatedFlag = true;
                        $this->messageManager->addNoticeMessage(
                            __('Quantity was recalculated from %1 to %2', $itemInfo['before_suggest_qty'], $qty),
                            'quote_item' . $item->getId()
                        );
                    }
                }
            }

            if ($qtyRecalculatedFlag) {
                $this->messageManager->addNoticeMessage(
                    __('We adjusted product quantities to fit the required increments.')
                );
            }

            $this->_eventManager->dispatch(
                'checkout_cart_update_items_after',
                ['cart' => $this, 'info' => $infoDataObject]
            );

            return $this;
        } else {
            return parent::updateItems($data);
        }
    }
}
