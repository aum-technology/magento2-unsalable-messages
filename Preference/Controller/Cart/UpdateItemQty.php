<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace AumTechnology\Unsaleable\Preference\Controller\Cart;

use Magento\Checkout\Model\Cart\RequestQuantityProcessor;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;

/**
 * UpdateItemQty ajax request
 */
class UpdateItemQty extends \Magento\Checkout\Controller\Cart\UpdateItemQty
{

    /**
     * @var RequestQuantityProcessor
     */
    private $quantityProcessor;

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;
    protected $userMessages;
    protected $moduleHelper;
    protected $moduleStatus;
    /**
     * UpdateItemQty constructor
     *
     * @param Context $context
     * @param RequestQuantityProcessor $quantityProcessor
     * @param FormKeyValidator $formKeyValidator
     * @param CheckoutSession $checkoutSession
     * @param Json $json
     * @param LoggerInterface $logger
     */

    public function __construct(
        Context $context,
        RequestQuantityProcessor $quantityProcessor,
        FormKeyValidator $formKeyValidator,
        CheckoutSession $checkoutSession,
        Json $json,
        LoggerInterface $logger,
        \AumTechnology\Unsaleable\Model\UserMessages $userMessages,
        \AumTechnology\Unsaleable\Helper\Data $moduleHelper
    ) {
        $this->quantityProcessor = $quantityProcessor;
        $this->formKeyValidator = $formKeyValidator;
        $this->checkoutSession = $checkoutSession;
        $this->json = $json;
        $this->logger = $logger;
        $this->userMessages = $userMessages;
        parent::__construct($context, $quantityProcessor, $formKeyValidator, $checkoutSession, $json, $logger);
        $this->moduleHelper = $moduleHelper;
        $this->moduleStatus = $this->moduleHelper->getModuleStatus();
    }

    /**
     * Controller execute method
     *
     * @return void
     */
    public function execute()
    {
        if ($this->moduleStatus) {
            try {
                $stockErrorMessages = [];
                $this->validateRequest();
                $this->validateFormKey();

                $cartData = $this->getRequest()->getParam('cart');

                $this->validateCartData($cartData);

                $cartData = $this->quantityProcessor->process($cartData);
                $quote = $this->checkoutSession->getQuote();

                $response = [];
                foreach ($cartData as $itemId => $itemInfo) {
                    $item = $quote->getItemById($itemId);
                    $qty = isset($itemInfo['qty']) ? (float) $itemInfo['qty'] : 0;
                    if ($item) {
                        try {
                            $this->updateItemQuantity($item, $qty);
                        } catch (LocalizedException $e) {
                            $errorInfos = $item->getErrorInfos();
                            foreach ($errorInfos as $key => $error) {
                                if ($error['origin'] == 'cataloginventory') {
                                    unset($errorInfos[$key]);
                                }
                            }
                            if (!empty($errorInfos)) {
                                $response[] = [
                                    'error' => $e->getMessage(),
                                    'itemId' => $itemId
                                ];
                            } else {
                                $stockErrorMessages[$item->getSku()] = $e->getMessage();
                                $response[] = [
                                    'error' => $this->getStockErrorMessage($stockErrorMessages),
                                    'itemId' => $itemId
                                ];
                            }
                        }
                    }
                }

                $this->jsonResponse(count($response) ? json_encode($response) : '');
            } catch (\Exception $e) {
                $this->logger->critical($e->getMessage());
                $this->jsonResponse('Something went wrong while saving the page. Please refresh the page and try again.');
            }
        } else {
            parent::execute();
        }
    }
    public function getStockErrorMessage(iterable $messages)
    {
        $html = "<div id='stock-error-message'>";
        $html .= "<div class='message error'><span>" . $this->userMessages->getUserMessage($this->userMessages::REUPDATE_CART) . "<span></div><br/>";
        foreach ($messages as $sku => $error) {
            $html .= "<div>$sku -> $error</div>";
        }
        $html .= "</dv>";
        return $html;
    }
    /**
     * Updates quote item quantity.
     *
     * @param Item $item
     * @param float $qty
     * @return void
     * @throws LocalizedException
     */
    private function updateItemQuantity(Item $item, float $qty)
    {
        if ($qty > 0) {
            $item->clearMessage();
            $item->setHasError(false);
            $item->setQty($qty);

            if ($item->getHasError()) {
                throw new LocalizedException(__($item->getMessage()));
            }
        }
    }

    /**
     * JSON response builder.
     *
     * @param string $error
     * @return void
     */
    private function jsonResponse(string $error = '')
    {
        $this->getResponse()->representJson(
            $this->json->serialize($this->getResponseData($error))
        );
    }

    /**
     * Returns response data.
     *
     * @param string $error
     * @return array
     */
    private function getResponseData(string $error = ''): array
    {
        $response = ['success' => true];

        if (!empty($error)) {
            $response = [
                'success' => false,
                'error_message' => $error,
            ];
        }

        return $response;
    }

    /**
     * Validates the Request HTTP method
     *
     * @return void
     * @throws NotFoundException
     */
    private function validateRequest()
    {
        if ($this->getRequest()->isPost() === false) {
            throw new NotFoundException(__('Page Not Found'));
        }
    }

    /**
     * Validates form key
     *
     * @return void
     * @throws LocalizedException
     */
    private function validateFormKey()
    {
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            throw new LocalizedException(
                __('Something went wrong while saving the page. Please refresh the page and try again.')
            );
        }
    }

    /**
     * Validates cart data
     *
     * @param array|null $cartData
     * @return void
     * @throws LocalizedException
     */
    private function validateCartData($cartData = null)
    {
        if (!is_array($cartData)) {
            throw new LocalizedException(
                __('Something went wrong while saving the page. Please refresh the page and try again.')
            );
        }
    }
}
