<?php

namespace PayIQ\Magento2\Observer;

use Magento\Framework\Event\ObserverInterface;

class SalesOrderInvoiceSaveAfter implements ObserverInterface
{
    /** @var \PayIQ\Magento2\Helper\Data */
    protected $payiqHelper;

    /**
     * @param \PayIQ\Magento2\Helper\Data $payiqHelper
     */
    public function __construct(
        \PayIQ\Magento2\Helper\Data $payiqHelper
    ) {
        $this->payiqHelper = $payiqHelper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $invoice->getOrder();

        /** @var  $method */
        $method = $order->getPayment()->getMethodInstance();

        // Check is order has been placed using PayIQ
        if (strpos($method->getCode(), 'payiq_') === false) {
            return $this;
        }

        // is Captured
        if (!$order->getPayment()->getIsTransactionPending()) {
            // Load Invoice transaction Data
            if (!$invoice->getTransactionId()) {
                return $this;
            }

            $transactionId = $invoice->getTransactionId();
            $details = $method->fetchTransactionInfo($order->getPayment(), $transactionId);

            if (!isset($details['transactionStatus'])) {
                return $this;
            }

            // Get Order Status
            if (in_array((int)$details['transactionStatus'], [0, 6])) {
                // For Capture
                $new_status = $method->getConfigData('order_status_capture');
                $message = __('Payment has been captured');
            } elseif ((int)$details['transactionStatus'] === 3) {
                // For Authorize
                $new_status = $method->getConfigData('order_status_authorize');
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
            $order->save();
        }

        return $this;
    }
}
