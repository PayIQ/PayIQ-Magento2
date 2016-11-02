<?php

namespace PayIQ\Payments\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\DB\Adapter\AdapterInterface;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * Installs DB schema for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;

        $installer->startSetup();

        // Table quote
        $columns = [
            'payiq_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee',
            ],
            'payiq_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee Tax',
            ],
            'base_payiq_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payments Fee',
            ],
            'base_payiq_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payment Fee Tax',
            ]
        ];

        $sales_order = $installer->getTable('quote');
        $connection = $installer->getConnection();
        foreach ($columns as $name => $column) {
            $connection->addColumn($sales_order, $name, $column);
        }

        // Table quote_address
        $columns = [
            'payiq_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee',
            ],
            'payiq_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee Tax',
            ],
            'base_payiq_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payments Fee',
            ],
            'base_payiq_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payment Fee Tax',
            ]
        ];

        $sales_order = $installer->getTable('quote_address');
        $connection = $installer->getConnection();
        foreach ($columns as $name => $column) {
            $connection->addColumn($sales_order, $name, $column);
        }

        // Table sales_order
        $columns = [
            'payiq_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee',
            ],
            'payiq_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee Tax',
            ],
            'base_payiq_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payments Fee',
            ],
            'base_payiq_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payment Fee Tax',
            ],
            'payiq_payment_fee_invoiced' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee (Invoiced)',
            ],
            'payiq_payment_fee_tax_invoiced' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee Tax (Invoiced)',
            ],
            'base_payiq_payment_fee_invoiced' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payments Fee (Invoiced)',
            ],
            'base_payiq_payment_fee_tax_invoiced' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payments Fee Tax (Invoiced)',
            ],
            'payiq_payment_fee_refunded' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee (Refunded)',
            ],
            'payiq_payment_fee_tax_refunded' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee Tax (Refunded)',
            ],
            'base_payiq_payment_fee_refunded' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payments Fee (Refunded)',
            ],
            'base_payiq_payment_fee_tax_refunded' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payments Fee Tax (Refunded)',
            ],
        ];

        $sales_order = $installer->getTable('sales_order');
        $connection = $installer->getConnection();
        foreach ($columns as $name => $definition) {
            $connection->addColumn($sales_order, $name, $definition);
        }

        // Table sales_invoice
        $columns = [
            'payiq_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee',
            ],
            'payiq_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee Tax',
            ],
            'base_payiq_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payments Fee',
            ],
            'base_payiq_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ PaymentFee Tax',
            ],
        ];

        $sales_order = $installer->getTable('sales_invoice');
        $connection = $installer->getConnection();
        foreach ($columns as $name => $definition) {
            $connection->addColumn($sales_order, $name, $definition);
        }

        // Table sales_creditmemo
        $columns = [
            'payiq_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee',
            ],
            'payiq_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'PayIQ Payments Fee Tax',
            ],
            'base_payiq_payment_fee' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payments Fee',
            ],
            'base_payiq_payment_fee_tax' => [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                'length' => '12,4',
                'nullable' => false,
                'default' => '0.0000',
                'comment' => 'Base PayIQ Payment Fee Tax',
            ],
        ];

        $sales_order = $installer->getTable('sales_creditmemo');
        $connection = $installer->getConnection();
        foreach ($columns as $name => $definition) {
            $connection->addColumn($sales_order, $name, $definition);
        }

        $installer->endSetup();
    }
}
