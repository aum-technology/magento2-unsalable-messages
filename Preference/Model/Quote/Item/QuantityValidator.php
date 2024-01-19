<?php

/**
 * Product inventory data validator
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace AumTechnology\Unsaleable\Preference\Model\Quote\Item;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\CatalogInventory\Helper\Data;
use Magento\CatalogInventory\Model\Quote\Item\QuantityValidator\Initializer\Option;
use Magento\CatalogInventory\Model\Quote\Item\QuantityValidator\Initializer\StockItem;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;

/**
 * Quote item quantity validator.
 *
 * @api
 * @since 100.0.2
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *
 * @deprecated 100.3.0 Replaced with Multi Source Inventory
 * @link https://devdocs.magento.com/guides/v2.4/inventory/index.html
 * @link https://devdocs.magento.com/guides/v2.4/inventory/inventory-api-reference.html
 */
class QuantityValidator extends \Magento\CatalogInventory\Model\Quote\Item\QuantityValidator
{
    protected array $stockErrorSkus;
    protected $request;
    protected $userMessages;
    protected $moduleHelper;
    protected $moduleStatus;
    public function __construct(
        Option $optionInitializer,
        StockItem $stockItemInitializer,
        StockRegistryInterface $stockRegistry,
        StockStateInterface $stockState,
        \Magento\Framework\App\RequestInterface $request,
        \AumTechnology\Unsaleable\Model\UserMessages $userMessages,
        \AumTechnology\Unsaleable\Helper\Data $moduleHelper
    ) {
        parent::__construct($optionInitializer, $stockItemInitializer, $stockRegistry, $stockState);
        $this->stockErrorSkus = [];
        $this->request = $request;
        $this->userMessages = $userMessages;
        $this->moduleHelper = $moduleHelper;
        $this->moduleStatus = $this->moduleHelper->getModuleStatus();
    }

    /**
     * Add error information to Quote Item
     *
     * @param \Magento\Framework\DataObject $result
     * @param Item $quoteItem
     * @return void
     */
    private function addErrorInfoToQuote($result, $quoteItem)
    {
        if ($result->getErrorCode() == "is_salable_with_reservations-not_enough_qty") {
            $this->stockErrorSkus[$quoteItem->getSku()] = '';
            $result->setQuoteMessage($this->userMessages->getUserMessage($this->userMessages::CART_SPECIFIC_MESSAGE_FOR_LOWER_QTY, "{{skus}}", array_keys($this->stockErrorSkus)));
            $messages = $quoteItem->getQuote()->getMessages();
            if (isset($messages['qty'])) {
                $messages['qty'] = null;
                $quoteItem->getQuote()->setMessages($messages);
            }
        }
        $quoteItem->addErrorInfo(
            'cataloginventory',
            Data::ERROR_QTY,
            $result->getMessage()
        );

        $quoteItem->getQuote()->addErrorInfo(
            $result->getQuoteMessageIndex(),
            'cataloginventory',
            Data::ERROR_QTY,
            $result->getQuoteMessage()
        );
    }

    /**
     * Check product inventory data when quote item quantity declaring
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function validate(Observer $observer)
    {
        if ($this->moduleStatus) {
            /* @var $quoteItem Item */
            $quoteItem = $observer->getEvent()->getItem();
            if (
                !$quoteItem ||
                !$quoteItem->getProductId() ||
                !$quoteItem->getQuote()
            ) {
                return;
            }
            $product = $quoteItem->getProduct();
            $qty = $quoteItem->getQty();

            /* @var \Magento\CatalogInventory\Model\Stock\Item $stockItem */
            $stockItem = $this->stockRegistry->getStockItem($product->getId(), $product->getStore()->getWebsiteId());
            if (!$stockItem instanceof StockItemInterface) {
                throw new LocalizedException(__('The Product stock item is invalid. Verify the stock item and try again.'));
            }

            if (($options = $quoteItem->getQtyOptions()) && $qty > 0) {
                foreach ($options as $option) {
                    $this->optionInitializer->initialize($option, $quoteItem, $qty);
                }
            } else {
                $this->stockItemInitializer->initialize($stockItem, $quoteItem, $qty);
            }

            if ($quoteItem->getQuote()->getIsSuperMode()) {
                return;
            }

            /* @var \Magento\CatalogInventory\Api\Data\StockStatusInterface $stockStatus */
            $stockStatus = $this->stockRegistry->getStockStatus($product->getId(), $product->getStore()->getWebsiteId());

            /* @var \Magento\CatalogInventory\Api\Data\StockStatusInterface $parentStockStatus */
            $parentStockStatus = false;

            /**
             * Check if product in stock. For composite products check base (parent) item stock status
             */
            if ($quoteItem->getParentItem()) {
                $product = $quoteItem->getParentItem()->getProduct();
                $parentStockStatus = $this->stockRegistry->getStockStatus(
                    $product->getId(),
                    $product->getStore()->getWebsiteId()
                );
            }

            if ($stockStatus) {
                if (
                    $stockStatus->getStockStatus() === Stock::STOCK_OUT_OF_STOCK
                    || $parentStockStatus && $parentStockStatus->getStockStatus() == Stock::STOCK_OUT_OF_STOCK
                ) {
                    $hasError = $quoteItem->getStockStateResult()
                        ? $quoteItem->getStockStateResult()->getHasError() : false;
                    if (!$hasError) {
                        $quoteItem->addErrorInfo(
                            'cataloginventory',
                            Data::ERROR_QTY,
                            __('This product is out of stock.')
                        );
                    } else {
                        $quoteItem->addErrorInfo(null, Data::ERROR_QTY);
                    }
                    if ($this->userMessages->getUserMessage($this->userMessages::CART_MESSAGE_FOR_OUT_OF_STOCK)) {
                        $quoteItem->getQuote()->addErrorInfo(
                            'stock',
                            'cataloginventory',
                            Data::ERROR_QTY,
                            __($this->userMessages->getUserMessage($this->userMessages::CART_MESSAGE_FOR_OUT_OF_STOCK))
                        );
                    }
                    return;
                } else {
                    // Delete error from item and its quote, if it was set due to item out of stock
                    $this->_removeErrorsFromQuoteAndItem($quoteItem, Data::ERROR_QTY);
                }
            }

            /**
             * Check item for options
             */
            if ($options) {
                $qty = $product->getTypeInstance()->prepareQuoteItemQty($quoteItem->getQty(), $product);
                $quoteItem->setData('qty', $qty);
                if ($stockStatus) {
                    $this->checkOptionsQtyIncrements($quoteItem, $options);
                }

                // variable to keep track if we have previously encountered an error in one of the options
                $removeError = true;
                foreach ($options as $option) {
                    $result = $option->getStockStateResult();
                    if ($result->getHasError()) {
                        $option->setHasError(true);
                        //Setting this to false, so no error statuses are cleared
                        $removeError = false;
                        $this->addErrorInfoToQuote($result, $quoteItem);
                    }
                }
                if ($removeError) {
                    $this->_removeErrorsFromQuoteAndItem($quoteItem, Data::ERROR_QTY);
                }
            } else {
                if ($quoteItem->getParentItem() === null) {
                    $result = $quoteItem->getStockStateResult();
                    if ($result->getHasError()) {
                        $this->addErrorInfoToQuote($result, $quoteItem);
                    } else {
                        $this->_removeErrorsFromQuoteAndItem($quoteItem, Data::ERROR_QTY);
                    }
                }
            }
        } else {
            parent::validate($observer);
        }
    }

    /**
     * Verifies product options quantity increments.
     *
     * @param Item $quoteItem
     * @param array $options
     * @return void
     */
    private function checkOptionsQtyIncrements(Item $quoteItem, array $options): void
    {
        $removeErrors = true;
        foreach ($options as $option) {
            $optionValue = $option->getValue();
            $optionQty = $quoteItem->getData('qty') * $optionValue;
            $result = $this->stockState->checkQtyIncrements(
                $option->getProduct()->getId(),
                $optionQty,
                $option->getProduct()->getStore()->getWebsiteId()
            );
            if ($result->getHasError()) {
                $quoteItem->getQuote()->addErrorInfo(
                    $result->getQuoteMessageIndex(),
                    'cataloginventory',
                    Data::ERROR_QTY_INCREMENTS,
                    $result->getQuoteMessage()
                );

                $removeErrors = false;
            }
        }

        if ($removeErrors) {
            // Delete error from item and its quote, if it was set due to qty problems
            $this->_removeErrorsFromQuoteAndItem(
                $quoteItem,
                Data::ERROR_QTY_INCREMENTS
            );
        }
    }
}
