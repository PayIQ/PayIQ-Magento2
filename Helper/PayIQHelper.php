<?php

namespace PayIQ\Magento2\Helper;

error_reporting(E_ALL);
ini_set("display_errors", 1);

use PayIQ\PHP\AbstractPayIQHelper;
use Magento\Framework\DataObject;

class PayIQHelper extends AbstractPayIQHelper
{
    protected $payment = null;
    protected $paymentMethod = null;
    protected $currentStore = null;
    protected $taxHelper = null;
    //protected $_storeManager;

    public function __construct(
        //\Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Tax\Helper\Data $taxHelper
    )
    {
        $this->currentStore = $storeManager->getStore();
        $this->taxHelper = $taxHelper;

        $this->sharedSecret = '';
        $this->serviceName = '';

        parent::__construct();
    }

    public function setOrder( $order )
    {
        // Magento\Sales\Model\Order
        $this->order = $order;

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $this->paymentMethod = $order->getPayment()->getMethodInstance();
    }

    public function setPayment( \Magento\Payment\Model\InfoInterface $payment )
    {
        $this->payment = $payment;
    }

    public function getLanguage( )
    {
        $language = $this->paymentMethod->getConfigData('language');
        if (empty($language)) {
            /** @var \Magento\Framework\ObjectManagerInterface $om */
            $om = \Magento\Framework\App\ObjectManager::getInstance();

            /** @var \Magento\Framework\Locale\Resolver $resolver */
            $resolver = $om->get('Magento\Framework\Locale\Resolver');
            $locale = $resolver->getLocale();

            /** @var \PayIQ\Magento2\Model\Config\Source\Language $language */
            $language = $om->get('PayIQ\Magento2\Model\Config\Source\Language');
            $languages = $language->toOptionArray();
            foreach ($languages as $key => $value) {
                if (str_replace('_', '-', $locale) === $value['value']) {
                    return $value['value'];
                }
            }

            // Use en-US as default language
            return 'en-US';
        }
    }


    public function getOrderId()
    {
        return $this->order->getIncrementId();
    }
    public function getTransactionId()
    {
        echo get_class($this->payment);
        $transaction = $this->payment->getAuthorizationTransaction();

        echo get_class($transaction);
        echo '9999';
        return $transaction->getTxnId();
    }
    public function getSubscriptionId()
    {

    }
    public function getOrderTotal()
    {
        return $this->order->getGrandTotal();
    }
    public function getOrderCurrency()
    {
        return $this->order->getOrderCurrency()->getCurrencyCode();
    }
    public function getOrderItems()
    {
        $orderItems = $this->order->getAllVisibleItems();

        $orderItemData = [];

        foreach( $orderItems as $orderItem )
        {
            $orderItemData[] = [
                'Description'   => $orderItem->getName(),
                'SKU'           => $orderItem->getSku(),
                'Quantity'      => (int) $orderItem->getQtyOrdered(),
                'UnitPrice'     => $this->formatPrice($orderItem->getPriceInclTax()),
                //'UnitPrice'     => $this->formatPrice($orderItem->getPrice()),
            ];

        }

        if (!$this->order->getIsVirtual()) {
            $shippingCost = $this->order->getShippingInclTax();

            if( $shippingCost > 0 )
            {
                $orderItemData[] = [
                    'Description'   => $this->order->getShippingDescription(),
                    'SKU'           => $this->order->getShippingMethod(),
                    //'Quantity'      => $orderItem->getQtyInvoiced(),
                    'Quantity'      => 1,
                    'UnitPrice'     => $this->formatPrice($shippingCost),
                ];

            }
        }

        // add Discount
        if (abs($this->order->getDiscountAmount()) > 0) {
            $discountData = $this->getOrderDiscountData();
            $discountInclTax = $discountData->getDiscountInclTax();
            //$discountExclTax = $discountData->getDiscountExclTax();
            //$discountVatAmount = $discountInclTax - $discountExclTax;
            //$discountVatPercent = (($discountInclTax / $discountExclTax) - 1) * 100;

            $lines[] = [
                'Description'   => $this->order->getDiscountDescription(),
                'SKU'           => 'discount',
                //'Quantity'      => $orderItem->getQtyInvoiced(),
                'Quantity'      => 1,
                'UnitPrice'     => $this->formatPrice(-1 * $discountInclTax),
            ];
        }

        return $orderItemData;
    }
    function getOrderReference() {
        return $this->order->getIncrementId();
        //return 'order_'.$this->order->getIncrementId();
    }
    public function getCustomerId()
    {

    }
    public function getCustomerReference()
    {

    }

    function getTransactionSettings() {

        $order_id = $this->order->getId();

        $data = [
            'AutoCapture'       => 'true',  //( isset( $options ) ? 'true' : 'false' ),
            'CallbackUrl'       => $this->currentStore->getUrl('payiq/payiq/callback', [
                //'_secure' => $this->getRequest()->isSecure()
            ]),
            'CreateSubscription' => 'false',
            'DirectPaymentBank' => '',
            'FailureUrl'        => $this->currentStore->getUrl('payiq/payiq/failure', [
                //'_secure' => $this->getRequest()->isSecure()
            ]),
            //Allowed values: Card, Direct, NotSet
            'PaymentMethod'     => 'NotSet',
            'SuccessUrl'        => $this->currentStore->getUrl('payiq/payiq/success', [
                //'_secure' => $this->getRequest()->isSecure()
            ]),
        ];

        return $data;
    }

    function getOrderInfo() {

        $data = [
            'OrderReference' => $this->getOrderReference(),
            'OrderItems' => $this->getOrderItems(),
            'Currency' => $this->getOrderCurrency(),
            // Optional alphanumeric string to indicate the transaction category.
            // Enables you to group and filter the transaction log and reports based on a custom criterion of your choice.
            //'OrderCategory' => '',
            // Optional order description displayed to end‐user on the payment site.
            'OrderDescription' => '',
        ];

        return $data;
    }



    /*
    static function getGatewayOption( $key ) {

    }
    */



    /**
     * Gets the total discount from Order
     * inkl. and excl. tax
     * Data is returned as a Varien_Object with these data-keys set:
     *   - discount_incl_tax
     *   - discount_excl_tax
     * @param \Magento\Sales\Model\Order $order
     * @return \Magento\Framework\DataObject
     */
    public function getOrderDiscountData()
    {
        $discountIncl = 0;
        $discountExcl = 0;

        // find discount on the items
        foreach ($this->order->getItemsCollection() as $item) {
            /** @var \Magento\Sales\Model\Order\Item $item */
            if (!$this->taxHelper->priceIncludesTax()) {
                $discountExcl += $item->getDiscountAmount();
                $discountIncl += $item->getDiscountAmount() * (($item->getTaxPercent() / 100) + 1);
            } else {
                $discountExcl += $item->getDiscountAmount() / (($item->getTaxPercent() / 100) + 1);
                $discountIncl += $item->getDiscountAmount();
            }
        }

        // find out tax-rate for the shipping
        if ((float)$this->order->getShippingInclTax() && (float)$this->order->getShippingAmount()) {
            $shippingTaxRate = $this->order->getShippingInclTax() / $this->order->getShippingAmount();
        } else {
            $shippingTaxRate = 1;
        }

        // get discount amount for shipping
        $shippingDiscount = (float)$this->order->getShippingDiscountAmount();

        // apply/remove tax to shipping-discount
        if (!$this->taxHelper->priceIncludesTax()) {
            $discountIncl += $shippingDiscount * $shippingTaxRate;
            $discountExcl += $shippingDiscount;
        } else {
            $discountIncl += $shippingDiscount;
            $discountExcl += $shippingDiscount / $shippingTaxRate;
        }

        $return = new DataObject;
        return $return->setDiscountInclTax($discountIncl)->setDiscountExclTax($discountExcl);
    }





}
