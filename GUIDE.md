# Using the BitPay plugin for VirtueMart

## Prerequisites

* Last Version Tested: Joomla 2.5.20 VirtueMart 2.6.6

You must have a BitPay merchant account to use this plugin.  It's free to [sign-up for a BitPay merchant account](https://bitpay.com/start).

## Installation
The Joomla Extension Manager expects a zip file for installation. You can download this zip file from the [most recent release](https://github.com/bitpay/virtuemart-plugin/releases/latest) on the release page of this repository. Otherwise, the contents of the zip file can be found in the upload subdirectory. Create a zip file of everything in the upload directory and then follow the configuration instructions below.
 
## Configuration
1. Go to Extensions -> Extension Manager -> Install
2. Browse and select the zip file, click Upload & Install.
3. Go to Manage, and find the plugin under "VM Payment - BitPay", and make sure that the plugin is enabled.
4. Go to Components -> VirtueMart and click on Payment Methods.
5. Click New and type in the information, selecting "VM Payment - BitPay" as Payment Method. Be sure to select "Yes" in the publish section. Click save.
6. Create an Legacy API Key in your BitPay Merchant account at https://bitpay.com.
7. Select the configuration tab for the payment method that you just created, and enter your API Key from step 6.
8. Select your network: livenet for real bitcoin, testnet for test bitcoin. Please double check that the website that you received your API Key from corresponds to the chosen network. 
9. Click save and close.
