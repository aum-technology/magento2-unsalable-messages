<?php

namespace AumTechnology\Unsaleable\Preference\Controller\Cart;

use Magento\Checkout\Model\Cart\RequestQuantityProcessor;

/**
 * Post update shopping cart.
 */
class UpdatePost extends \Magento\Checkout\Controller\Cart\UpdatePost
{
    /**
     * @var RequestQuantityProcessor
     */
    private $quantityProcessor;
    protected $moduleHelper;
    protected $moduleStatus;
    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator
     * @param \Magento\Checkout\Model\Cart $cart
     * @param RequestQuantityProcessor $quantityProcessor
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Magento\Checkout\Model\Cart $cart,
        RequestQuantityProcessor $quantityProcessor = null,
        \AumTechnology\Unsaleable\Helper\Data $moduleHelper
    ) {
        parent::__construct(
            $context,
            $scopeConfig,
            $checkoutSession,
            $storeManager,
            $formKeyValidator,
            $cart
        );

        $this->quantityProcessor = $quantityProcessor ?: $this->_objectManager->get(RequestQuantityProcessor::class);
        $this->moduleHelper = $moduleHelper;
        $this->moduleStatus = $this->moduleHelper->getModuleStatus();
    }

    /**
     * Update customer's shopping cart
     *
     * @return void
     */
    protected function _updateShoppingCart()
    {
        if ($this->moduleStatus) {
            try {
                $cartData = $this->getRequest()->getParam('cart');
                if (is_array($cartData)) {
                    if (!$this->cart->getCustomerSession()->getCustomerId() && $this->cart->getQuote()->getCustomerId()) {
                        $this->cart->getQuote()->setCustomerId(null);
                    }
                    $cartData = $this->quantityProcessor->process($cartData);
                    $cartData = $this->cart->suggestItemsQty($cartData);
                    $this->cart->updateItems($cartData)->save();
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $message = json_decode($e->getMessage(), true);
                $bool = $message['inventory_error'] ?? false;
                if (!$bool) {
                    $this->messageManager->addErrorMessage(
                        $this->_objectManager->get(\Magento\Framework\Escaper::class)->escapeHtml($e->getMessage())
                    );
                }
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('We can\'t update the shopping cart.'));
                $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->critical($e);
            }
        } else {
            parent::_updateShoppingCart();
        }
    }
}
