<?php

namespace PayIQ\Payments\Block\Info;

use Magento\Framework\View\Element\Template;

class PayIQ extends \PayIQ\Payments\Block\Info\AbstractInfo
{
    /**
     * @var string
     */
    protected $_template = 'PayIQ::info/payiq.phtml';

    /**
     * @var array
     */
    protected $transactionFields = [
        'PayIQ Payment Method' => ['paymentMethod', 'cardProduct'],
        'Masked Number' => ['maskedNumber', 'maskedCard'],
        //'Bank Hash' => ['BankHash', 'csId', 'panId'],
        'Bank Reference' => ['bankReference'],
        'Authenticated Status' => ['AuthenticatedStatus', 'authenticatedStatus'],
        'Transaction Ref' => ['transactionRef'],
        'PayIQ Transaction Number' => ['transactionNumber'],
        'PayIQ Transaction Status' => ['transactionStatus'],
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
        $this->setTemplate('PayIQ::info/pdf/payiq.phtml');
        return $this->toHtml();
    }
}

