<?php

namespace PayIQ\Magento2\Model\Config\Source;

class Language implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => '', 'label' => __('Detect automatically')],
            ['value' => 'en-US', 'label' => __('English')],
            ['value' => 'sv-SE', 'label' => __('Swedish')],
            ['value' => 'nb-NO', 'label' => __('Norway')],
            ['value' => 'da-DK', 'label' => __('Danish')],
            ['value' => 'fi-FI', 'label' => __('Finnish')],
        ];
    }
}
