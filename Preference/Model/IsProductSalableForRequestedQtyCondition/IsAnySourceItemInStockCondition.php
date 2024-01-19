<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace AumTechnology\Unsaleable\Preference\Model\IsProductSalableForRequestedQtyCondition;

use Magento\InventorySales\Model\IsProductSalableCondition\IsAnySourceItemInStockCondition as IsAnySourceItemInStock;
use Magento\InventorySalesApi\Api\Data\ProductSalabilityErrorInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\ProductSalableResultInterface;
use Magento\InventorySalesApi\Api\Data\ProductSalableResultInterfaceFactory;

/**
 * @inheritdoc
 */
class IsAnySourceItemInStockCondition extends \Magento\InventorySales\Model\IsProductSalableForRequestedQtyCondition\IsAnySourceItemInStockCondition
{
    /**
     * @var IsAnySourceItemInStock
     */
    private $isAnySourceInStockCondition;

    /**
     * @var ProductSalabilityErrorInterfaceFactory
     */
    private $productSalabilityErrorFactory;

    /**
     * @var ProductSalableResultInterfaceFactory
     */
    private $productSalableResultFactory;
    protected $userMessages;
    protected $moduleHelper;
    protected $moduleStatus;
    /**
     * @param IsAnySourceItemInStock $isAnySourceInStockCondition
     * @param ProductSalabilityErrorInterfaceFactory $productSalabilityErrorFactory
     * @param ProductSalableResultInterfaceFactory $productSalableResultFactory
     */
    public function __construct(
        IsAnySourceItemInStock $isAnySourceInStockCondition,
        ProductSalabilityErrorInterfaceFactory $productSalabilityErrorFactory,
        ProductSalableResultInterfaceFactory $productSalableResultFactory,
        \AumTechnology\Unsaleable\Model\UserMessages $userMessages,
        \AumTechnology\Unsaleable\Helper\Data $moduleHelper
    ) {
        parent::__construct($isAnySourceInStockCondition, $productSalabilityErrorFactory, $productSalableResultFactory);
        $this->isAnySourceInStockCondition = $isAnySourceInStockCondition;
        $this->productSalabilityErrorFactory = $productSalabilityErrorFactory;
        $this->productSalableResultFactory = $productSalableResultFactory;
        $this->userMessages = $userMessages;
        $this->moduleHelper = $moduleHelper;
        $this->moduleStatus = $this->moduleHelper->getModuleStatus();
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(string $sku, int $stockId, float $requestedQty): ProductSalableResultInterface
    {
        if ($this->moduleStatus) {
            $errors = [];

            if (!$this->isAnySourceInStockCondition->execute($sku, $stockId)) {
                $data = [
                    'code' => 'is_any_source_item_in_stock-no_source_items_in_stock',
                    'message' => __($this->userMessages->getUserMessage($this->userMessages::CART_ITEM_OUT_OF_STOCK))
                ];
                $errors[] = $this->productSalabilityErrorFactory->create($data);
            }

            return $this->productSalableResultFactory->create(['errors' => $errors]);
        } else {
            return parent::execute($sku, $stockId, $requestedQty);
        }
    }
}
