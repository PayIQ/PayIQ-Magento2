<?php

namespace PayIQ\Payments\Controller\PayIQ;

class Redirect extends \Magento\Framework\App\Action\Action
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
     * @var \PayIQ\Payments\Helper\Data
     */
    protected $payiqHelper;

    /**
     * @var \PayIQ\Payments\Logger\Logger
     */
    protected $payiqLogger;

    /**
     * Redirect constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \PayIQ\Payments\Helper\Data $payiqHelper
     * @param \PayIQ\Payments\Logger\Logger $payiqLogger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        \PayIQ\Payments\Helper\Data $payiqHelper,
        \PayIQ\Payments\Logger\Logger $payiqLogger
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
        // Load Order
        $order = $this->getOrder();


        /*
        var_dump($order->getId());
        var_dump($order);
        */


        if (!$order->getId()) {
            $this->session->restoreQuote();
            $this->messageManager->addError(__('No order for processing found'));

            $this->_redirect('checkout/cart');
            return;
        }


        $order_id = $order->getIncrementId();

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $order->getPayment()->getMethodInstance();

        // Init PayIQ API Client
        $client = $this->payiqHelper->getClient($method);

        $client->setOrder($order);

        $response = $client->prepareSession();

        if( $response['status'] == 'ok' && isset( $response['data']['RedirectUrl'] ) ) {

            // Set Pending Payment status
            $order->addStatusHistoryComment(__('The customer was redirected to PayIQ.'), \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $order->save();

            // Redirect to PayIQ
            $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setUrl($response['data']['RedirectUrl']);
            return $resultRedirect;
        }

        /*
        print_r($response);

        echo 'API Error: '.print_r($response['error'], true) . PHP_EOL . PHP_EOL .
            'REQUEST:' . PHP_EOL . $client->getLastRequest()  . PHP_EOL .
            'RESPONSE:' . PHP_EOL . $client->getLastResponse()  . PHP_EOL . PHP_EOL;
        die();
        */
        //$message = $this->payiqHelper->getVerboseErrorMessage($result);

        $message = 'API Error. Check error log';

        // Cancel order
        $order->cancel();
        $order->addStatusHistoryComment($message, \Magento\Sales\Model\Order::STATE_CANCELED);
        $order->save();

        // Restore the quote
        $this->session->restoreQuote();
        $this->messageManager->addError($message);

        /*
        TODO: fix logging
        $this->payiqHelper->logger->error(
            'API Error: '.print_r($response['error'], true) . PHP_EOL . PHP_EOL .
            'REQUEST:' . PHP_EOL . $client->getLastRequest()  . PHP_EOL .
            'RESPONSE:' . PHP_EOL . $client->getLastResponse()  . PHP_EOL . PHP_EOL
        );
        */

        $this->_redirect('checkout/cart');

        return;
    }

    /**
     * Get order object
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        $incrementId = $this->getCheckout()->getLastRealOrderId();

        /*
        var_dump($incrementId);
        //var_dump($this->_checkoutSession->getLastRealOrderId());

        var_dump($this->getCheckout()->getLastRealOrder()->getId());
        var_dump($this->getCheckout()->getLastRealOrder());

        //var_dump(Mage::getSingleton('checkout/session')->getLastRealOrderId());

        */


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