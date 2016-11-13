<?php
namespace PayIQ\Magento2\Controller\Transaction;

use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

/**
 * Class Index
 * Provide TC URL http://testsite.local/payiq/transaction
 * @see http://www.payiqpim.com/quick-guide/9-transaction-callback/
 * @package PayIQ\Magento2\Controller\Transaction
 */
class Index extends \Magento\Framework\App\Action\Action
{
    /** @var array PayIQ TC Spider IPs */
    static protected $_allowed_ips = [
        '82.115.146.170', // Production
        '82.115.146.10' // Test
    ];

    /**
     * @var \PayIQ\Magento2\Helper\Data
     */
    protected $payiqHelper;

    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $rawResultFactory;

    /**
     * Constructor
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Controller\Result\RawFactory $rawResultFactory
     * @param \PayIQ\Magento2\Helper\Data $payiqHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\RawFactory $rawResultFactory,
        \PayIQ\Magento2\Helper\Data $payiqHelper
    )
    {
        parent::__construct($context);
        $this->rawResultFactory = $rawResultFactory;
        $this->payiqHelper = $payiqHelper;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        // Init Logger
        $writer = new Stream(BP . '/var/log/payiq_tc.log');
        $logger = new Logger();
        $logger->addWriter($writer);

        $remote_addr = $this->payiqHelper->getRemoteAddr();
        $logger->info('Start Transaction Callback process', [$remote_addr]);

        /** @var \Magento\Framework\Controller\Result\Raw $result */
        $result = $this->rawResultFactory->create();

        // Check is PayIQ Request
        if (!in_array($remote_addr, self::$_allowed_ips)) {
            $logger->err('Access denied for this request. It\'s not PayIQ Spider.', [$remote_addr]);
            $result->setStatusHeader('403', '1.1', 'Access denied. Accept PayIQ Transaction Callback only.');
            $result->setContents('Error: Access denied. Accept PayIQ Transaction Callback only.');
            return $result;
        }

        // Check Post Fields
        if (count($this->getRequest()->getParams()) === 0) {
            $logger->err('Empty request received');
            $result->setStatusHeader('500', '1.1', 'FAILURE');
            $result->setContents('FAILURE');
            return $result;
        }

        // Log requested params for Debug
        $logger->info('Requested Params', $this->getRequest()->getParams());

        // Detect Payment Method of Order
        $order_id = $this->getRequest()->getParam('orderId');
        if (empty($order_id)) {
            $logger->err('Param orderId is undefined');
            $result->setStatusHeader('500', '1.1', 'FAILURE');
            $result->setContents('FAILURE');
            return $result;
        }

        $orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');

        /** @var \Magento\Sales\Model\Order $order */
        $order = $orderFactory->create()->loadByIncrementId($order_id);
        if (!$order->getId()) {
            $logger->err('Order don\'t exists in store', [$order_id]);
            $result->setStatusHeader('500', '1.1', 'FAILURE');
            $result->setContents('FAILURE');
            return $result;
        }

        // Check Payment Method
        /** @var \Magento\Payment\Model\Method\AbstractMethod $payment_method */
        $payment_method = $order->getPayment()->getMethodInstance();
        $payment_method_code = $payment_method->getCode();
        if (strpos($payment_method_code, 'payiq_') === false) {
            $logger->err('Unsupported payment method', [$payment_method_code]);
            $result->setStatusHeader('500', '1.1', 'FAILURE');
            $result->setContents('FAILURE');
            return $result;
        }

        // Get Account Details
        $accountNumber = $payment_method->getConfigData('accountnumber');
        $encryptionKey = $payment_method->getConfigData('encryptionkey');
        $debug = (bool)$payment_method->getConfigData('debug');

        // Check Requested Account Number
        if ($this->getRequest()->getParam('accountNumber') !== $accountNumber) {
            $logger->err('Can\'t to get API details of merchant account', [$this->getRequest()->getParam('accountNumber')]);
            $result->setStatusHeader('500', '1.1', 'FAILURE');
            $result->setContents('FAILURE');
            return $result;
        }

        // Get Transaction Details
        $transactionId = $this->getRequest()->getParam('transactionNumber');

        // Lookup Transaction
        $transactionRepository = $this->_objectManager->get('Magento\Sales\Model\Order\Payment\Transaction\Repository');

        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $transactionRepository->getByTransactionId(
            $transactionId,
            $order->getPayment()->getId(),
            $order->getId()
        );

        if (!$transaction) {
            $logger->info('Transaction already processed', [$transactionId]);
            $result->setStatusHeader('500', '1.1', 'FAILURE');
            $result->setContents('FAILURE');
            return $result;
        }

        // Init PayIQ Environment
        $this->payiqHelper->getPx()->setEnvironment($accountNumber, $encryptionKey, $debug);

        // Call PxOrder.GetTransactionDetails2
        $params = [
            'accountNumber' => '',
            'transactionNumber' => $transactionId,
        ];
        $details = $this->payiqHelper->getPx()->GetTransactionDetails2($params);
        if ($details['code'] !== 'OK' || $details['errorCode'] !== 'OK') {
            $logger->err('Failed to get transaction details', $details);
            $result->setStatusHeader('500', '1.1', 'FAILURE');
            $result->setContents('FAILURE');
            return $result;
        }

        $order_id = $details['orderId'];
        $transaction_status = (int)$details['transactionStatus'];

        $logger->info('Incoming transaction', [$transactionId]);
        $logger->info('Transaction Status', [$transaction_status]);
        $logger->info('OrderId', [$order_id]);

        // Get Order Status from External Payment Module
        $order_status_authorize = $payment_method->getConfigData('order_status_authorize');
        $order_status_capture = $payment_method->getConfigData('order_status_capture');

        // Register Transaction
        $order->getPayment()->setTransactionId($transactionId);
        $transaction = $this->payiqHelper->addPaymentTransaction($order, $details);
        if (!$transaction) {
            $logger->err('Failed to save transaction', [$transactionId]);
            $result->setStatusHeader('500', '1.1', 'FAILURE');
            $result->setContents('FAILURE');
            return $result;
        }

        // Set Last Transaction ID
        $order->getPayment()->setLastTransId($transactionId)->save();

        // Check Order and Transaction Result
        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        switch ($transaction_status) {
            case 0:
            case 1:
            case 3:
            case 6:
                // Complete order
                $logger->info('Action: Complete order', [$order_id]);

                // Call PxOrder.Complete
                $params = [
                    'accountNumber' => '',
                    'orderRef' => $this->getRequest()->getParam('orderRef'),
                ];
                $result = $this->payiqHelper->getPx()->Complete($params);
                if ($result['errorCodeSimple'] !== 'OK') {
                    $logger->err('Failed to complete payment', $result);
                    $result->setStatusHeader('500', '1.1', 'FAILURE');
                    $result->setContents('FAILURE');
                    return $result;
                }

                // Verify transaction status
                if ((int)$result['transactionStatus'] !== $transaction_status) {
                    $logger->err('Failed to complete payment. Transaction status is different');
                    $result->setStatusHeader('500', '1.1', 'FAILURE');
                    $result->setContents('FAILURE');
                    return $result;
                }

                // Select Order Status
                if (in_array($transaction_status, [0, 6])) {
                    $new_status = $order_status_capture;
                    $message = __('Payment has been captured');
                } elseif ($transaction_status === 3 || (isset($result['pending']) && $result['pending'] === 'true')) {
                    $new_status = $order_status_authorize;
                    $message = __('Payment has been authorized');
                } else {
                    $new_status = $order->getStatus();
                    $message = '';
                }

                // Change order status
                /** @var \Magento\Sales\Model\Order\Status $status */
                $status = $this->payiqHelper->getAssignedState($new_status);
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->addStatusHistoryComment($message, $status->getStatus());

                // Create Invoice for Sale Transaction
                if (in_array($transaction_status, [0, 6])) {
                    $invoice = $this->payiqHelper->makeInvoice($order, [], false);
                    $invoice->setTransactionId($transactionId);
                    $invoice->save();
                }

                $order->save();

                // Send order notification
                $orderSender = $this->_objectManager->get('\Magento\Sales\Model\Order\Email\Sender\OrderSender');
                try {
                    $orderSender->send($order);
                } catch (\Exception $e) {
                    $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                }
                break;
            case 2:
                // Create CreditMemo
                $logger->info('Action: Create CreditMemo', [$order_id]);
                if ($order->hasInvoices() && $order->canCreditmemo()) {
                    $credit_amount = (float)($details['creditAmount'] / 100);

                    // Try to find Invoice to refund
                    // @todo We support full refund currently
                    $founded = false;
                    $invoices = $order->getInvoiceCollection();
                    foreach ($invoices as $invoice) {
                        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
                        if ($invoice->getGrandTotal() === $credit_amount) {
                            // Create Credit Memo
                            $creditmemoLoader = $this->_objectManager->get('Magento\Sales\Controller\Adminhtml\Order\CreditmemoLoader');
                            $creditmemoLoader->setOrderId($order->getId());
                            $creditmemoLoader->setInvoiceId($invoice->getId());
                            $creditmemo = $creditmemoLoader->load();

                            $creditmemo->addComment(
                                __('Credit Memo created using Transaction Callback'),
                                true,
                                true
                            );

                            $creditmemoManagement = $this->_objectManager->create(
                                'Magento\Sales\Api\CreditmemoManagementInterface'
                            );
                            $creditmemoManagement->refund($creditmemo, false, false);

                            $logger->info('Credit Memo has been created', [$order_id, $invoice->getIncrementId()]);
                            $founded = true;
                            break;
                        }
                    }

                    if (!$founded) {
                        $logger->err('Can\'t to find invoice to refund', [$order_id, $credit_amount]);
                    }
                }
                break;
            case 4:
            case 5:
                // Change Order Status to Canceled
                $logger->info('Action: Cancel order', [$order_id]);
                if (!$order->isCanceled() && !$order->hasInvoices()) {
                    $order->cancel();
                    $order->addStatusHistoryComment(__('Order canceled by Transaction Callback'));
                    $order->save();
                    //$order->sendOrderUpdateEmail(true, $message);

                    $logger->info('Order has been canceled', [$order_id]);
                }
                break;
            default:
                $logger->err('Unknown Transaction Status', [$order_id]);
                $result->setStatusHeader('500', '1.1', 'FAILURE');
                $result->setContents('FAILURE');
                return $result;
        }

        // Show "OK"
        $logger->info('Transaction Callback OK', [$order_id]);
        $result->setStatusHeader('200', '1.1', 'OK');
        $result->setContents('OK');
        return $result;
    }
}
