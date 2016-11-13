<?php

namespace PayIQ\Magento2\Block\Sales\Creditmemo;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PayIQ\Magento2\Model\Fee\Config;
use Magento\Framework\DataObject;

class Fee extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Config
     */
    protected $feeConfig;

    /**
     * Fee constructor.
     * @param Template\Context $context
     * @param array $data
     * @param Config $feeConfig
     */
    public function __construct(
        Template\Context $context,
        array $data,
        Config $feeConfig
    )
    {
        parent::__construct($context, $data);

        $this->scopeConfig = $context->getScopeConfig();
        $this->feeConfig = $feeConfig;
    }

    /**
     * @return $this
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();
        $source = $parent->getSource();

        if ($source->getBasePayexPaymentFee() > 0) {
            if ($this->displaySalesFeeBoth()) {
                $parent->addTotal(
                    new DataObject([
                        'code' => 'payiq_payment_fee_with_tax',
                        'strong' => false,
                        'value' => $source->getPayexPaymentFee() + $source->getPayexPaymentFeeTax(),
                        'label' => __('Payment Fee') . ' ' . __('(Incl.Tax)'),
                    ]),
                    'grand_total'
                );

                $parent->addTotal(
                    new DataObject([
                        'code' => 'payiq_payment_fee',
                        'strong' => false,
                        'value' => $source->getPayexPaymentFee(),
                        'label' => __('Payment Fee') . ' ' . __('(Excl.Tax)'),
                    ]),
                    'payiq_payment_fee_with_tax'
                );
            } elseif ($this->displaySalesFeeInclTax()) {
                $parent->addTotal(
                    new DataObject([
                        'code' => 'payiq_payment_fee_with_tax',
                        'strong' => false,
                        'value' => $source->getPayexPaymentFee() + $source->getPayexPaymentFeeTax(),
                        'label' => __('Payment Fee') . ' ' . __('(Incl.Tax)'),
                    ]),
                    'grand_total'
                );
            } else {
                $parent->addTotal(
                    new DataObject([
                        'code' => 'payiq_payment_fee',
                        'strong' => false,
                        'value' => $source->getPayexPaymentFee(),
                        'label' => __('Payment Fee') . ' ' . __('(Excl.Tax)'),
                    ]),
                    'grand_total'
                );
            }
        }

        return $this;
    }


    /**
     * Check if display sales prices fee included and excluded tax
     * @return mixed
     */
    public function displaySalesFeeBoth()
    {
        return $this->feeConfig->displaySalesFeeBoth($this->getParentBlock()->getOrder()->getStore());
    }

    /**
     * Check if display sales prices fee included tax
     * @return mixed
     */
    public function displaySalesFeeInclTax()
    {
        return $this->feeConfig->displaySalesFeeInclTax($this->getParentBlock()->getOrder()->getStore());
    }
}
