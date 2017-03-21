<?php

namespace PayIQ\Magento2\Controller\PayIQ;

use Magento\Sales\Model\Order\Payment\Transaction;

class Callback extends \Magento\Framework\App\Action\Action
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
     * @param \PayIQ\Magento2\Helper\Data $payiqHelper
     * @param \PayIQ\Magento2\Logger\Logger $payiqLogger
     * @param \Magento\Sales\Api\TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        \PayIQ\Magento2\Helper\Data $payiqHelper,
        \PayIQ\Magento2\Logger\Logger $payiqLogger,
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

    public function invalidCallback( $text = 'Unspecified error' )
    {
        header('HTTP/1.1 400 Bad Request');
        echo "Invalid callback: ".$text;
        die();
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

        //print_r($params);
        /*
        Example input data:

        Array
        (
            [servicename] => ServiceName01
            [transactionid] => ebb0553b-dff6-4902-8e8b-39264b5999f4
            [orderreference] => 000000005
            [authorizedamount] => 11800
            [settledamount] => 0
            [currency] => SEK
            [operationamount] => 11800
            [operationtype] => authorize
            [message] =>
            [customername] => Valid Testcard
            [paymentmethod] => card
            [directbank] =>
            [subscriptionid] =>
            [checksum] => e849128c8d5ff72ad1c475797182c481
        )
        */

        // Check params
        $fieldsToCheck = [
            'servicename'       => __(''),
            'transactionid'     => __('Transaction ID'),
            'orderreference'    => __('Order reference'),
            'authorizedamount'  => __(''),
            //'settledamount'     => __(''),
            'currency'          => __(''),
            'operationtype'     => __(''),
            //'message'           => __(''),
            //'customername'      => __(''),
            'paymentmethod'     => __(''),
            //'directbank'        => __(''),
            //'subscriptionid'    => __(''),
            'checksum'          => __('')
        ];

        foreach( $fieldsToCheck as $fieldKey => $fieldText ) {

            if (!isset($params[$fieldKey])) {
                //TODO: Log this
                return $this->invalidCallback('Invalid fields');
            }
        }

        $transactionID = $params['transactionid'];

        // Load Order
        $orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
        $order =  $orderFactory->create()->loadByIncrementId($params['orderreference']);


        if (!$order->getId()) {
            return $this->invalidCallback('No order for processing found');
        }

        $payment = $order->getPayment();
        $payment->setTransactionId($transactionID);

        // Get payment method object
        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $payment->getMethodInstance();

        $client = $this->payiqHelper->getClient($method);
        $client->setOrder($order);

        // Validate checksum
        $checksumValid = $client->validateChecksum($params, $params['checksum']);

        if ( $checksumValid !== true ) {

            return $this->invalidCallback('Invalid checksum');
        }

        // Check Transaction is already registered
        $transaction = $this->transactionRepository->getByTransactionId(
            $transactionID,
            $order->getPayment()->getId(),
            $order->getId()
        );

        // Get transaction details from PayIQ API
        $transactionDetails = $client->GetTransactionDetails($transactionID);

        switch ($params['operationtype'])
        {
            case 'authorize':

                // Payment authorized
                $message = __('Payment has been authorized');

                // Change order status
                $new_status = $method->getConfigData('order_status_authorize');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payiqHelper->getAssignedState($new_status);

                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());

                $order->addStatusHistoryComment($message);
                $order->save();

                // Send order notification
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }

                $this->session->getQuote()->setIsActive(false)->save();

                die('OK');

                break;
            case 'capture':

                // Payment captured
                $message = __('Payment has been captured');

                // Change order status
                $new_status = $method->getConfigData('order_status_capture');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payiqHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->addStatusHistoryComment($message);
                $order->save();

                // Send order notification
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }

                // Create Invoice for Sale Transaction
                $invoice = $this->payiqHelper->makeInvoice($order, [], false, $message);
                $invoice->setTransactionId($transactionID);
                $invoice->save();

                // Redirect to Success page
                $this->session->getQuote()->setIsActive(false)->save();

                die('OK');

                break;

            default:
                return $this->invalidCallback('Invalid opration: '.$params['operationtype']);
                break;
        }

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
