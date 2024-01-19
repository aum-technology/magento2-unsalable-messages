<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace AumTechnology\Unsaleable\Preference\Model\IsProductSalableForRequestedQtyCondition;

use Magento\InventoryReservationsApi\Model\GetReservationsQuantityInterface;
use Magento\InventorySalesApi\Model\GetSalableQtyInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\InventorySalesApi\Api\Data\ProductSalableResultInterface;
use Magento\InventorySalesApi\Api\Data\ProductSalableResultInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\ProductSalabilityErrorInterfaceFactory;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;

/**
 * @inheritdoc
 */
class IsSalableWithReservationsCondition extends \Magento\InventorySales\Model\IsProductSalableForRequestedQtyCondition\IsSalableWithReservationsCondition
{
    /**
     * @var GetStockItemDataInterface
     */
    private $getStockItemData;

    /**
     * @var GetReservationsQuantityInterface
     */
    private $getReservationsQuantity;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * @var ProductSalabilityErrorInterfaceFactory
     */
    private $productSalabilityErrorFactory;

    /**
     * @var ProductSalableResultInterfaceFactory
     */
    private $productSalableResultFactory;

    /**
     * @var GetSalableQtyInterface
     */
    private $getProductQtyInStock;
    protected $moduleHelper;
    protected $moduleStatus;
    protected $userMessages;
    /**
     * @param GetStockItemDataInterface $getStockItemData
     * @param GetReservationsQuantityInterface $getReservationsQuantity
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     * @param ProductSalabilityErrorInterfaceFactory $productSalabilityErrorFactory
     * @param ProductSalableResultInterfaceFactory $productSalableResultFactory
     * @param GetSalableQtyInterface $getProductQtyInStock
     * 
     */
    public function __construct(
        GetStockItemDataInterface $getStockItemData,
        GetReservationsQuantityInterface $getReservationsQuantity,
        GetStockItemConfigurationInterface $getStockItemConfiguration,
        ProductSalabilityErrorInterfaceFactory $productSalabilityErrorFactory,
        ProductSalableResultInterfaceFactory $productSalableResultFactory,
        GetSalableQtyInterface $getProductQtyInStock,
        \AumTechnology\Unsaleable\Model\UserMessages $userMessages,
        \AumTechnology\Unsaleable\Helper\Data $moduleHelper
    ) {
        parent::__construct($getStockItemData, $getReservationsQuantity, $getStockItemConfiguration, $productSalabilityErrorFactory, $productSalableResultFactory, $getProductQtyInStock);
        $this->getStockItemData = $getStockItemData;
        $this->getReservationsQuantity = $getReservationsQuantity;
        $this->getStockItemConfiguration = $getStockItemConfiguration;
        $this->productSalabilityErrorFactory = $productSalabilityErrorFactory;
        $this->productSalableResultFactory = $productSalableResultFactory;
        $this->getProductQtyInStock = $getProductQtyInStock;
        $this->userMessages = $userMessages;
        $this->moduleHelper = $moduleHelper;
        $this->moduleStatus = $this->moduleHelper->getModuleStatus();
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(string $sku, int $stockId, float $requestedQty): ProductSalableResultInterface
    {
        if ($this->moduleStatus) {
            $stockItemData = $this->getStockItemData->execute($sku, $stockId);
            if (null === $stockItemData) {
                $errors = [
                    $this->productSalabilityErrorFactory->create([
                        'code' => 'is_salable_with_reservations-no_data',
                        'message' => __('The requested sku is not assigned to given stock')
                    ])
                ];
                return $this->productSalableResultFactory->create(['errors' => $errors]);
            }

            $qtyLeftInStock = $this->getProductQtyInStock->execute($sku, $stockId);
            $isEnoughQty = bccomp((string)$qtyLeftInStock, (string)$requestedQty, 4) >= 0;

            if (!$isEnoughQty) {
                $errors = [
                    $this->productSalabilityErrorFactory->create([
                        'code' => 'is_salable_with_reservations-not_enough_qty',
                        'message' => __($this->userMessages->getUserMessage($this->userMessages::CART_ITEM_SPECIFIC_MESSAGE, "{{qty}}", $qtyLeftInStock))
                    ])
                ];
                return $this->productSalableResultFactory->create(['errors' => $errors]);
            }
            return $this->productSalableResultFactory->create(['errors' => []]);
        } else {
            return parent::execute($sku, $stockId, $requestedQty);
        }
    }
}
