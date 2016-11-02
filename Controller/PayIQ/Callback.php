<?php

namespace PayIQ\Payments\Controller\PayIQ;

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


        print_r($transactionDetails);

        switch ($params['operationtype'])
        {
            case 'authorize':

                // Payment authorized
                $message = __('Payment has been authorized');

                // Change order status
                $new_status = $method->getConfigData('order_status_authorize');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payiqHelper->getAssignedState($new_status);

                var_dump($status)->getState();
                var_dump($status)->getStatus();
                die();
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();

                // @todo Fixme: No comment
                $order->addStatusHistoryComment($message);

                // Send order notification
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }

                $this->session->getQuote()->setIsActive(false)->save();

                die('OK');

                break;
            case 'Capture':

                // Payment captured
                $message = __('Payment has been captured');

                // Change order status
                $new_status = $method->getConfigData('order_status_capture');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payiqHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();
                $order->addStatusHistoryComment($message);

                // Send order notification
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }

                // Create Invoice for Sale Transaction
                $invoice = $this->payiqHelper->makeInvoice($order, [], false, $message);
                $invoice->setTransactionId($details['transactionNumber']);
                $invoice->save();

                // Redirect to Success page
                $this->session->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                break;

                break;





            default:
                return $this->invalidCallback('Invalid opration');
                break;
        }





        die();










        $order_id = $order->getIncrementId();

        var_dump($order_id);

        if (!$order->getId()) {
            return $this->invalidLink(__('No order for processing found'));
        }

        $payment = $order->getPayment();

        $payment->setTransactionId($transactionID);

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $payment->getMethodInstance();


        $client = $this->payiqHelper->getClient($method);

        $client->setOrder($order);

        $transactionDetails = $client->GetTransactionDetails($transactionID);

        print_r($transactionDetails);

        //$captured = (bool) array_search('Capture', array_column($transactionDetails['data']['Operations']['TransactionOperation'], 'Type'));

        // Check Transaction is already registered
        $transaction = $this->transactionRepository->getByTransactionId(
            $transactionID,
            $order->getPayment()->getId(),
            $order->getId()
        );







        switch ($transactionDetails['data']['Status'])
        {
            case 'Capture':

                // Payment captured
                $message = __('Payment has been captured');

                // Change order status
                $new_status = $method->getConfigData('order_status_capture');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payiqHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();
                $order->addStatusHistoryComment($message);

                // Send order notification
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }

                // Create Invoice for Sale Transaction
                $invoice = $this->payiqHelper->makeInvoice($order, [], false, $message);
                $invoice->setTransactionId($details['transactionNumber']);
                $invoice->save();

                // Redirect to Success page
                $this->session->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                break;

                break;
            case 'Authorize':


                // Payment authorized
                $message = __('Payment has been authorized');

                // Change order status
                $new_status = $method->getConfigData('order_status_authorize');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payiqHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();

                // @todo Fixme: No comment
                $order->addStatusHistoryComment($message);

                // Send order notification
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }

                // Redirect to Success page
                $this->session->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                break;

                break;
            default:


                break;
        }






        die();

        // Check OrderRef
        $orderReference = $this->getRequest()->getParam('orderreference');
        if (empty($orderReference)) {
            $this->session->restoreQuote();
            $this->messageManager->addError(__('Order reference is empty'));
            $this->_redirect('checkout/cart');
            return;
        }

        // Load Order
        $orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
        $order =  $orderFactory->create()->loadByIncrementId($orderReference);

        $order_id = $order->getIncrementId();

        var_dump($order_id);


        if (!$order->getId()) {
            $this->session->restoreQuote();

            die('No order for processing found');
            $this->messageManager->addError(__('No order for processing found'));
            $this->_redirect('checkout/cart');
            return;
        }

        die('here');

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $order->getPayment()->getMethodInstance();

        // Init PayIQ Environment
        $accountnumber = $method->getConfigData('accountnumber');
        $encryptionkey = $method->getConfigData('encryptionkey');
        $debug = (bool)$method->getConfigData('debug');
        $this->payiqHelper->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);

        // Call PxOrder.Complete
        $params = [
            'accountNumber' => '',
            'orderReference' => $orderReference
        ];
        $details = $this->payiqHelper->getPx()->Complete($params);
        $this->payiqLogger->info('PxOrder.Complete', $details);
        if ($details['errorCodeSimple'] !== 'OK') {
            // Cancel order
            $order->cancel();
            $order->addStatusHistoryComment(__('Order automatically canceled. Failed to complete payment.'));
            $order->save();

            // Restore the quote
            $this->session->restoreQuote();

            $message = $this->payiqHelper->getVerboseErrorMessage($details);
            $this->messageManager->addError($message);
            $this->_redirect('checkout/cart');
            return;
        }

        $transaction_id = isset($details['transactionNumber']) ? $details['transactionNumber'] : null;
        $transaction_status = isset($details['transactionStatus']) ? (int)$details['transactionStatus'] : null;

        // Check Transaction is already registered
        $transaction = $this->transactionRepository->getByTransactionId(
            $transaction_id,
            $order->getPayment()->getId(),
            $order->getId()
        );

        if ($transaction) {
            $raw_details_info = $transaction->getAdditionalInformation(Transaction::RAW_DETAILS);
            if (is_array($raw_details_info) && in_array($transaction_status, [0, 3, 6])) {
                // Redirect to Success Page
                $this->payiqLogger->info('Transaction already paid: Redirect to success page', [$order_id]);
                $this->session->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                return;
            }

            // Restore the quote
            $this->session->restoreQuote();

            $this->messageManager->addError(__('Payment failed'));
            $this->_redirect('checkout/cart');
        }

        // Register Transaction
        $order->getPayment()->setTransactionId($transaction_id);
        $transaction = $this->payiqHelper->addPaymentTransaction($order, $details);

        // Set Last Transaction ID
        $order->getPayment()->setLastTransId($transaction_id)->save();

        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        $transaction_status = isset($details['transactionStatus']) ? (int)$details['transactionStatus'] : null;
        switch ($transaction_status) {
            case 1:
            case 3:
                // Payment authorized
                $message = __('Payment has been authorized');

                // Change order status
                $new_status = $method->getConfigData('order_status_authorize');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payiqHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();

                // @todo Fixme: No comment
                $order->addStatusHistoryComment($message);

                // Send order notification
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }

                // Redirect to Success page
                $this->session->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                break;
            case 0:
            case 6:
                // Payment captured
                $message = __('Payment has been captured');

                // Change order status
                $new_status = $method->getConfigData('order_status_capture');

                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payiqHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->save();
                $order->addStatusHistoryComment($message);

                // Send order notification
                try {
                    $this->orderSender->send($order);
                } catch (\Exception $e) {
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }

                // Create Invoice for Sale Transaction
                $invoice = $this->payiqHelper->makeInvoice($order, [], false, $message);
                $invoice->setTransactionId($details['transactionNumber']);
                $invoice->save();

                // Redirect to Success page
                $this->session->getQuote()->setIsActive(false)->save();
                $this->_redirect('checkout/onepage/success');
                break;
            case 2:
            case 4:
            case 5:
                if ($transaction_status === 2) {
                    $message = __('Detected an abnormal payment process (Transaction Status: %1).', $transaction_status);
                } elseif ($transaction_status === 4) {
                    $message = __('Order automatically canceled.');
                } else {
                    $message = $this->payiqHelper->getVerboseErrorMessage($details);
                }

                $order->cancel();
                $order->addStatusHistoryComment($message);
                $order->save();

                // Restore the quote
                $this->session->restoreQuote();

                $this->messageManager->addError($message);
                $this->_redirect('checkout/cart');
                break;
            default:
                // Invalid transaction status
                $message = __('Invalid transaction status.');

                // Restore the quote
                $this->session->restoreQuote();

                $this->messageManager->addError($message);
                $this->_redirect('checkout/cart');
                return;
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
