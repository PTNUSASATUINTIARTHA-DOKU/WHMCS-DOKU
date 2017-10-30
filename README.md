# WHMCS DOKU HOSTED PAYMENT MODULE #

DOKU ❤️   WHMCS ! Let your WHMCS store integrated with DOKU  payment gateway.

## Description ##

DOKU payment gateway is an online payment gateway. We strive to make payments simple for both the merchant and customers. 
With this plugin you can accept online payment on your WHMCS using DOKU payment gateway.

### Minimum Requirements ###

- PHP version 5.5.x or greater
- MySQL version 5.0 or greater

### Installation ###

1. Download the modules from this repository.
2. Extract Whmcs-master.zip file you have previously downloaded.
3. Upload & merged module folder that you have extracted into your WHMCS directory.

## Installation & Configuration ##

1. Access your WHMCS admin page.
2. Go to menu Setup -> Payments -> Payment Gateways.
3. There are will be 2 payment type show up there in your payment gateway settings
* DOKU - Standard Payment if you wanted standard payment
* DOKU - Subscription if you wanted to use Subscription

Choose which you preferred as your payment method

4. Then choose Setup -> Payments -> Payment Gateways -> Manage Existing Gateways
5. Fill the input as instructed on the screen. 
6. Click Save Changes

#### NOTES ####

* Please note this our payment modules are not using `composer.json` 
* Add the url on the admin panel to cron on your WHMCS for check status payment process.
* In order to use this module please register at DOKU Website - https://www.doku.com to get an API. 

Further information about DOKU, please check our website on  DOKU Website at www.doku.com