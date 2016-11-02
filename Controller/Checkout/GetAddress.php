<?php

namespace PayIQ\Payments\Controller\Checkout;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class GetAddress extends \Magento\Framework\App\Action\Action
{
    const XML_PATH_MODULE_DEBUG = 'payiq/ssn/debug';
    const XML_PATH_MODULE_ACCOUNTNUMBER = 'payiq/ssn/accountnumber';
    const XML_PATH_MODULE_ENCRYPTIONKEY = 'payiq/ssn/encryptionkey';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \PayIQ\Payments\Helper\Data
     */
    protected $payiqHelper;

    /**
     * @var \PayIQ\Payments\Logger\Logger
     */
    protected $payiqLogger;

    /**
     * Constructor
     * @param \Magento\Framework\App\Action\Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \PayIQ\Payments\Helper\Data $payiqHelper
     * @param \PayIQ\Payments\Logger\Logger $payiqLogger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \PayIQ\Payments\Helper\Data $payiqHelper,
        \PayIQ\Payments\Logger\Logger $payiqLogger
    )
    {
        parent::__construct($context);

        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->session = $session;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->payiqHelper = $payiqHelper;
        $this->payiqLogger = $payiqLogger;
    }

    /**
     * View CMS page action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        // Get initial data from request
        $ssn = trim($this->getRequest()->getParam('ssn'));
        if (empty($ssn)) {
            /** @var \Magento\Framework\Controller\Result\Json $json */
            $json = $this->resultJsonFactory->create();
            return $json->setData([
                'success' => false,
                'message' => __('Social security number is empty')
            ]);
        }

        $country_code = trim($this->getRequest()->getParam('country_code'));
        if (empty($country_code)) {
            /** @var \Magento\Framework\Controller\Result\Json $json */
            $json = $this->resultJsonFactory->create();
            return $json->setData([
                'success' => false,
                'message' => __('Country is empty')
            ]);
        }

        if (!in_array($country_code, ['SE', 'NO'])) {
            /** @var \Magento\Framework\Controller\Result\Json $json */
            $json = $this->resultJsonFactory->create();
            return $json->setData([
                'success' => false,
                'message' => __('Your country don\'t supported')
            ]);
        }

        // strip whitespaces from postcode to pass validation
        $postcode = preg_replace('/\s+/', '', $this->getRequest()->getParam('postcode'));
        if (empty($postcode)) {
            /** @var \Magento\Framework\Controller\Result\Json $json */
            $json = $this->resultJsonFactory->create();
            return $json->setData([
                'success' => false,
                'message' => __('Postcode is empty')
            ]);
        }

        $store = $this->storeManager->getStore();

        // Init PayIQ Environment
        $accountnumber = $this->scopeConfig->getValue(
            self::XML_PATH_MODULE_ACCOUNTNUMBER,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );

        $encryptionkey = $this->scopeConfig->getValue(
            self::XML_PATH_MODULE_ENCRYPTIONKEY,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );

        $debug = $this->scopeConfig->getValue(
            self::XML_PATH_MODULE_DEBUG,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );

        $this->payiqHelper->getPx()->setEnvironment($accountnumber, $encryptionkey, $debug);

        // Call PxOrder.GetAddressByPaymentMethod
        $params = [
            'accountNumber' => '',
            'paymentMethod' => 'PXFINANCINGINVOICE' . $country_code,
            'ssn' => $ssn,
            'zipcode' => $postcode,
            'countryCode' => $country_code,
            'ipAddress' => $this->payiqHelper->getRemoteAddr()
        ];
        $result = $this->payiqHelper->getPx()->GetAddressByPaymentMethod($params);
        if ($result['code'] !== 'OK' || $result['description'] !== 'OK' || $result['errorCode'] !== 'OK') {
            $this->payiqLogger->error('PxOrder.GetAddressByPaymentMethod', $result);

            /** @var \Magento\Framework\Controller\Result\Json $json */
            $json = $this->resultJsonFactory->create();
            return $json->setData([
                'success' => false,
                'message' => $this->payiqHelper->getVerboseErrorMessage($result)
            ]);
        }

        // Parse name field
        $name = $this->payiqHelper->getNameParser()->parse_name($result['name']);

        $data = [
            'success' => true,
            'first_name' => trim($name['fname']),
            'last_name' => trim($name['lname']),
            'address_1' => $result['streetAddress'],
            'address_2' => !empty($result['coAddress']) ? 'c/o ' . trim($result['coAddress']) : '',
            'postcode' => trim($result['zipCode']),
            'city' => trim($result['city']),
            'country' => trim($result['countryCode'])
        ];

        // Save data in Session
        $this->session->setPayexSSN($ssn);
        $this->session->setPayexSSNData($data);

        /** @var \Magento\Framework\Controller\Result\Json $json */
        $json = $this->resultJsonFactory->create();
        return $json->setData($data);
    }
}
