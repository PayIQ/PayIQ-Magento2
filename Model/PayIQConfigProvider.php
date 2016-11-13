<?php

namespace PayIQ\Magento2\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Directory\Helper\Data;

class PayIQConfigProvider implements ConfigProviderInterface
{

    /**
     * @var \Magento\Framework\App\State
     */
    protected $_appState;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_session;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface $config
     */
    protected $_config;

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param PaymentHelper $paymentHelper
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Checkout\Model\Session $session,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        PaymentHelper $paymentHelper,
        \Magento\Framework\Locale\ResolverInterface $localeResolver,
        \Magento\Framework\App\Config\ScopeConfigInterface $config
    ) {
        $this->_appState = $context->getAppState();
        $this->_session = $session;
        $this->_storeManager = $storeManager;
        $this->_paymentHelper = $paymentHelper;
        $this->_localeResolver = $localeResolver;
        $this->_config = $config;
    }


    public function getConfig()
    {
        $config = [
            'payment' => [
                \PayIQ\Magento2\Model\Method\PayIQ::METHOD_CODE => []
            ]
        ];

        /** @var \PayIQ\Magento2\Model\Method\PayIQ $method */
        $method = $this->_paymentHelper->getMethodInstance(\PayIQ\Magento2\Model\Method\PayIQ::METHOD_CODE);
        if ($method->isAvailable()) {
            $config['payment'] [\PayIQ\Magento2\Model\Method\PayIQ::METHOD_CODE]['redirectUrl'] = $method->getCheckoutRedirectUrl();
        }

        return $config;
    }
}
