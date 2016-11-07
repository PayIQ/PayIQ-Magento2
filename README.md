# PayIQ Payments for Magento 2

The official PayIQ Extension for Magento2

# Install


### Install with composer

1. In the command line, go to the root directory of your Magento installation

2. Install the module and it's dependencies with this command:
	```bash    
	composer require payiq/payiq-magento2
	```

3. Enter following commands to enable module:

    ```bash
    php bin/magento module:enable PayIQ --clear-static-content && php bin/magento setup:upgrade && php bin/magento cache:clean
    ```
    This will enable the module, run the required install scripts and clear the cache.

    If you run Magneto in production mode, you will also need to recompile:
    ```bash
    php bin/magento setup:di:compile
    ```

4. Login into the Magneto admin to enable and configure the module. You do this under Stores > Configuration > Sales > Payment Methods > PayIQ Payments.

# Changelog

### 1.0.0
First public release. Currently in beta.