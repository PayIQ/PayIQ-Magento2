<?php

namespace PayIQ\Magento2\Block\Info;

use Magento\Framework\View\Element\Template;

class PayIQ extends \PayIQ\Magento2\Block\Info\AbstractInfo
{
    /**
     * @var string
     */
    protected $_template = 'PayIQ_Magento2::info/payiq.phtml';

    /**
     * @var array
     */
    protected $transactionFields = [
        'PayIQ_Magento2 Payment Method' => ['paymentMethod', 'cardProduct'],
        'Masked Number' => ['maskedNumber', 'maskedCard'],
        //'Bank Hash' => ['BankHash', 'csId', 'panId'],
        'Bank Reference' => ['bankReference'],
        'Authenticated Status' => ['AuthenticatedStatus', 'authenticatedStatus'],
        'Transaction Ref' => ['transactionRef'],
        'PayIQ_Magento2 Transaction Number' => ['transactionNumber'],
        'PayIQ_Magento2 Transaction Status' => ['transactionStatus'],
        'Transaction Error Code' => ['transactionErrorCode'],
        'Transaction Error Description' => ['transactionErrorDescription'],
        'Transaction ThirdParty Error' => ['transactionThirdPartyError']
    ];

    /**
     * Render as PDF
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('PayIQ_Magento2::info/pdf/payiq.phtml');
        return $this->toHtml();
    }
}

