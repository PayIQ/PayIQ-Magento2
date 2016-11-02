<?php

namespace PayIQ\Payments\Controller\PayIQ;

use Magento\Sales\Model\Order\Payment\Transaction;

class Success extends \Magento\Framework\App\Action\Action
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
     * @var \Magento\Sales\Api\TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * Success constructor.
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \PayIQ\Payments\Helper\Data $payiqHelper
     * @param \PayIQ\Payments\Logger\Logger $payiqLogger
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        \PayIQ\Payments\Helper\Data $payiqHelper,
        \PayIQ\Payments\Logger\Logger $payiqLogger,
        \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    )
    {
        parent::__construct($context);

        $this->urlBuilder = $context->getUrl();
        $this->session = $session;
        $this->payiqHelper = $payiqHelper;
        $this->payiqLogger = $payiqLogger;
        $this->transactionRepository = $transactionRepository;
        $this->orderSender = $orderSender;
    }

    public function invalidLink( $text = null )
    {
        if(!$text)
        {
            $text = __('Invalid link');
        }

        die($text);
        $this->session->restoreQuote();
        $this->messageManager->addError($text);
        $this->_redirect('checkout/cart');
        return;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $params = $this->getRequest()->getParams();

        /*
        Array (
        [sessionid] => d0f9c638-3d2e-440e-87a1-0c2e737f62eb
        [orderreference] => 000000003
        [orderref] => 000000003
        [transactionid] => 84fb3f9a-1d58-486b-a18f-413d5abb859e
        )
        */

        // Check params
        $fieldsToCheck = [
            'orderref'          => __('Order reference'),
            'orderreference'    => __('Order reference'),
            'sessionid'         => __('Session ID'),
            'transactionid'     => __('Transaction ID'),
        ];

        foreach( $fieldsToCheck as $field => $fieldName ) {

            if (empty($params[$field])) {
                //TODO: Log this
                return $this->invalidLink();
                //return $this->invalidLink( __(sprintf('%s is empty', $fieldName)));
            }
        }

        $transactionID = $params['transactionid'];

        // Load Order
        $orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
        $order =  $orderFactory->create()->loadByIncrementId($params['orderreference']);

        $order_id = $order->getIncrementId();

        if (!$order->getId()) {
            return $this->invalidLink(__('No order for processing found'));
        }

        $payment = $order->getPayment();

        $payment->setTransactionId($transactionID);

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $payment->getMethodInstance();

        $client = $this->payiqHelper->getClient($method);

        $client->setOrder($order);

        // Check Transaction is already registered
        $transaction = $this->transactionRepository->getByTransactionId(
            $transactionID,
            $order->getPayment()->getId(),
            $order->getId()
        );

        $capturenow = $method->getConfigData('capturenow');

        $this->session->getQuote()->setIsActive(false)->save();
        $this->_redirect('checkout/onepage/success');
        return;
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
