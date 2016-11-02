<?php


namespace PayIQ\Payments\Helper;


use DOMDocument;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

//use PayIQ\P

class Data extends AbstractHelper
{
    const MODULE_NAME = 'payiq';

    protected $_client = null;
    protected $context = null;

    protected $PayIQHelper = null;
    protected $order = null;
    protected $storeManager;
    protected $taxHelper;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Payment\Model\Config $config,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Framework\App\ProductMetadata $productMetadata,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {

        parent::__construct($context);

        $this->context = $context;
        $this->storeManager = $storeManager;
        $this->taxHelper = $taxHelper;

        //$this->_encryptor = $encryptor;
        //$this->_config = $config;
        //$this->_moduleList = $moduleList;
        //$this->_orderConfig = $orderConfig;

        $this->orderStatusCollectionFactory = $orderStatusCollectionFactory;
        //$this->invoiceService = $invoiceService;
        //$this->invoiceSender = $invoiceSender;

        //$this->taxHelper = $taxHelper;
        //$this->productMetadata = $productMetadata;





    }

    public function getStoreId()
    {
        return $this->storeManager->getStore()->getId();
    }

    /**
     * Retrieve information from payment configuration
     * @param $field
     * @param $paymentMethodCode
     * @param $storeId
     * @param bool|false $flag
     * @return bool|mixed
     */
    public function getConfigData($field, $paymentMethodCode, $storeId = null, $flag = false)
    {
        $path = 'payment/' . $paymentMethodCode . '/' . $field;

        if( !$storeId )
        {
            $storeId = $this->getStoreId();
        }

        if (!$flag) {
            return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }

    public function getClient( \Magento\Payment\Model\Method\AbstractMethod $method = null)
    {
        if (!$this->PayIQHelper) {
            $this->PayIQHelper = new PayIQHelper(
                $this->storeManager,
                $this->taxHelper
            );
        }

        if( $method ) {
            $serviceName = $this->getConfigData('service_name', self::MODULE_NAME);
            $sharedSecret = $this->getConfigData('shared_secret', self::MODULE_NAME);
            $debug = (bool)$this->getConfigData('debug', self::MODULE_NAME);

            /*
            var_dump($serviceName);
            var_dump($sharedSecret);
            var_dump($debug);
            */

            $this->PayIQHelper->setEnvironment($serviceName, $sharedSecret, $debug);
        }

        return $this->PayIQHelper;
    }


    public function getOrderBy(  )
    {

    }


    /**
     * Get Assigned State
     * @param $status
     * @return \Magento\Framework\DataObject
     */
    public function getAssignedState($status) {
        $collection = $this->orderStatusCollectionFactory->create()->joinStates();
        $status = $collection->addAttributeToFilter('main_table.status', $status)->getFirstItem();
        return $status;
    }

    public function addPaymentTransaction(\Magento\Sales\Model\Order $order, array $details = [])
    {
        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = null;

        /* Transaction statuses: 0=Sale, 1=Initialize, 2=Credit, 3=Authorize, 4=Cancel, 5=Failure, 6=Capture */
        $transaction_status = !empty($details['Status']) ? $details['Status'] : 'undefined';
        switch ($transaction_status) {
            case 1:
                // From PayEx PIM:
                // "If PxOrder.Complete returns transactionStatus = 1, then check pendingReason for status."
                // See http://www.payexpim.com/payment-methods/paypal/
                if ($details['pending'] === 'true') {
                    $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_AUTH, null, true);
                    $transaction->setIsClosed(0);
                    $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                    $transaction->save();
                    break;
                }

                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_PAYMENT, null, true);
                $transaction->setIsClosed(0);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 3:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_AUTH, null, true);
                $transaction->setIsClosed(0);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 0:
            case 6:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_CAPTURE, null, true);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 2:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_REFUND, null, true);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 4:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_VOID, null, true);
                $transaction->isFailsafe(true)->close(false);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            case 5:
                $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_PAYMENT, null, true);
                $transaction->setAdditionalInformation(Transaction::RAW_DETAILS, $details);
                $transaction->save();
                break;
            default:
                // Invalid transaction status
        }

        return $transaction;
    }



}
