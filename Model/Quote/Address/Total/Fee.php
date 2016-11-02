<?php

namespace PayIQ\Payments\Model\Quote\Address\Total;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Tax\Model\Calculation;

class Fee extends AbstractTotal
{
    /**
     * Payment Method Codes
     *
     * @var array
     */
    protected static $allowed_methods = [
        'payiq_financing',
        'payiq_partpayment'
    ];

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutHelper;

    /**
     * @var \PayIQ\Payments\Helper\Data
     */
    protected $payiqHelper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Calculation
     */
    protected $calculationTool;

    /**
     * Constructor
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param \PayIQ\Payments\Helper\Data $payiqHelper ,
     * @param ScopeConfigInterface $scopeConfig
     * @param Calculation $calculationTool
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \PayIQ\Payments\Helper\Data $payiqHelper,
        ScopeConfigInterface $scopeConfig,
        Calculation $calculationTool
    )
    {
        $this->storeManager = $storeManager;
        $this->priceCurrency = $priceCurrency;
        $this->checkoutHelper = $checkoutHelper;
        $this->payiqHelper = $payiqHelper;
        $this->scopeConfig = $scopeConfig;
        $this->calculationTool = $calculationTool;

        $this->setCode('payiq_payment_fee');
    }

    /**
     * Collect totals process.
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Api\Data\ShippingAssignmentInterface $shippingAssignment
     * @param \Magento\Quote\Model\Quote\Address\Total $total
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    )
    {
        parent::collect($quote, $shippingAssignment, $total);

        $store = $this->storeManager->getStore($quote->getStoreId());

        /** @var \Magento\Quote\Model\Quote\Address $address */
        $address = $shippingAssignment->getShipping()->getAddress();

        // Init Totals
        $total->setBaseTotalAmount($this->getCode(), 0);
        $total->setTotalAmount($this->getCode(), 0);

        $total->setBaseTotalAmount($this->getCode() . '_tax', 0);
        $total->setTotalAmount($this->getCode() . '_tax', 0);

        $total->setBasePayexPaymentFee(0);
        $total->setPayexPaymentFee(0);

        $total->setBasePayexPaymentFeeTax(0);
        $total->setPayexPaymentFeeTax(0);

        if (!count($shippingAssignment->getItems())) {
            return $this;
        }

        // Check payment method is chosen and allowed
        try {
            $payment_method = $quote->getPayment()->getMethodInstance();
            if (!in_array($payment_method->getCode(), self::$allowed_methods)) {
                return $this;
            }

            if (!$payment_method->isActive($store->getStoreId())) {
                return $this;
            }
        } catch (\Exception $e) {
            return $this;
        }

        // Calculate Payment Fee
        $price = (float)$payment_method->getConfigData('paymentfee', $store->getStoreId());
        $tax_class = $payment_method->getConfigData('paymentfee_tax_class', $store->getStoreId());
        $fee = $this->payiqHelper->getPaymentFeePrice($price, $tax_class);
        if (abs($fee->getPaymentFeeExclTax()) === 0) {
            return $this;
        }

        // Payment Fee
        $total->setBaseTotalAmount($this->getCode(), $fee->getPaymentFeeExclTax());
        $total->setTotalAmount($this->getCode(), $this->priceCurrency->convert($fee->getPaymentFeeExclTax(), $store));

        $total->setBasePayexPaymentFee($fee->getPaymentFeeExclTax());
        $total->setPayexPaymentFee($this->priceCurrency->convert($fee->getPaymentFeeExclTax(), $store));

        $quote->setBasePayexPaymentFee($fee->getPaymentFeeExclTax());
        $quote->setPayexPaymentFee($this->priceCurrency->convert($fee->getPaymentFeeExclTax(), $store));

        // Update totals
        $total->setBaseGrandTotal($total->getGrandTotal() + $fee->getPaymentFeeExclTax());
        $total->setGrandTotal($total->getGrandTotal() + $this->priceCurrency->convert($fee->getPaymentFeeExclTax(), $store));

        // Payment Fee Taxes
        $total->setBasePayexPaymentFeeTax($fee->getPaymentFeeTax());
        $total->setPayexPaymentFeeTax($this->priceCurrency->convert($fee->getPaymentFeeTax(), $store));

        $quote->setBasePayexPaymentFeeTax($fee->getPaymentFeeTax());
        $quote->setPayexPaymentFeeTax($this->priceCurrency->convert($fee->getPaymentFeeTax(), $store));

        // Update totals
        $address->setBaseTaxAmount($address->getBaseTaxAmount() + $fee->getPaymentFeeTax());
        $address->setTaxAmount($address->getTaxAmount() + $this->priceCurrency->convert($fee->getPaymentFeeTax(), $store));

        $total->addBaseTotalAmount('tax', $fee->getPaymentFeeTax());
        $total->addTotalAmount('tax', $this->priceCurrency->convert($fee->getPaymentFeeTax(), $store));

        $total->setBaseGrandTotal($total->getGrandTotal() + $fee->getPaymentFeeTax());
        $total->setGrandTotal($total->getGrandTotal() + $this->priceCurrency->convert($fee->getPaymentFeeTax(), $store));

        // Save Applied Taxes
        if ($address->getBaseTaxAmount() > 0) {
            $this->_saveAppliedTaxes($total,
                $this->calculationTool->getAppliedRates($fee->getRateRequest()),
                $this->priceCurrency->convert($fee->getPaymentFeeTax(), $store),
                $fee->getPaymentFeeTax(),
                $this->calculationTool->getRate($fee->getRateRequest())
            );
        }

        return $this;
    }

    /**
     * Fetch (Retrieve data as array)
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @param \Magento\Quote\Model\Quote\Address\Total $total
     * @return array
     * @internal param \Magento\Quote\Model\Quote\Address $address
     */
    public function fetch(
        Quote $quote,
        Total $total
    )
    {
        return [
            [
                'code' => $this->getCode(),
                'title' => $this->getLabel(),
                'value' => (float)$total->getPayexPaymentFee(),
            ],
            [
                'code' => $this->getCode() . '_tax',
                'value' => (float)$total->getPayexPaymentFeeTax(),
            ]
        ];
    }

    /**
     * Get Payment Fee label
     *
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __('Payment fee');
    }

    /**
     * Collect applied tax rates information on address level
     *
     * @param Total $total
     * @param array $applied
     * @param float $amount
     * @param float $baseAmount
     * @param float $rate
     * @return void
     * @see \Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector::_saveAppliedTaxes
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _saveAppliedTaxes(
        Total $total,
        $applied,
        $amount,
        $baseAmount,
        $rate
    ) {
        $previouslyAppliedTaxes = $total->getAppliedTaxes();
        $process = count($previouslyAppliedTaxes);

        foreach ($applied as $row) {
            if ($row['percent'] == 0) {
                continue;
            }
            if (!isset($previouslyAppliedTaxes[$row['id']])) {
                $row['process'] = $process;
                $row['amount'] = 0;
                $row['base_amount'] = 0;
                $previouslyAppliedTaxes[$row['id']] = $row;
            }

            if ($row['percent'] > 0) {
                $row['percent'] = $row['percent'] ? $row['percent'] : 1;
                $rate = $rate ? $rate : 1;

                $appliedAmount = $amount / $rate * $row['percent'];
                $baseAppliedAmount = $baseAmount / $rate * $row['percent'];
            } else {
                $appliedAmount = 0;
                $baseAppliedAmount = 0;
                foreach ($row['rates'] as $rate) {
                    $appliedAmount += $rate['amount'];
                    $baseAppliedAmount += $rate['base_amount'];
                }
            }

            if ($appliedAmount || $previouslyAppliedTaxes[$row['id']]['amount']) {
                $previouslyAppliedTaxes[$row['id']]['amount'] += $appliedAmount;
                $previouslyAppliedTaxes[$row['id']]['base_amount'] += $baseAppliedAmount;
            } else {
                unset($previouslyAppliedTaxes[$row['id']]);
            }
        }
        $total->setAppliedTaxes($previouslyAppliedTaxes);
    }
}
