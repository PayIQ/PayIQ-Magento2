<?php

namespace PayIQ\Magento2\Model\Config\Source;

class Operation implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'AUTHORIZATION', 'label' => __('Authorize')],
            ['value' => 'SALE', 'label' => __('Sale')]
        ];
    }
}
