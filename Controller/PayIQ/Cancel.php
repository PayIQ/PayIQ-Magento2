<?php

namespace PayIQ\Magento2\Controller\PayIQ;

class Cancel extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \PayIQ\Magento2\Helper\Data
     */
    protected $payiqHelper;

    /**
     * @var \PayIQ\Magento2\Logger\Logger
     */
    protected $payiqLogger;

    /**
     * Cancel constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \PayIQ\Magento2\Helper\Data $payiqHelper
     * @param \PayIQ\Magento2\Logger\Logger $payiqLogger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        \PayIQ\Magento2\Helper\Data $payiqHelper,
        \PayIQ\Magento2\Logger\Logger $payiqLogger
    ) {
        parent::__construct($context);

        $this->urlBuilder = $context->getUrl();
        $this->session = $session;
        $this->payiqHelper = $payiqHelper;
        $this->payiqLogger = $payiqLogger;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $message = __('Order canceled by user');
        $order = $this->getOrder();
        if ($order->getId()) {
            $order->cancel();
            $order->addStatusHistoryComment($message);
            $order->save();
        }

        // Restore the quote
        $this->session->restoreQuote();
        $this->messageManager->addError($message);
        $this->_redirect('checkout/cart');
    }

    /**
     * Get order object
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        $incrementId = $this->getCheckout()->getLastRealOrderId();
        $orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
        return $orderFactory->create()->loadByIncrementId($incrementId);
    }

    /**
     * Get Checkout Session
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }
}
