# Billmate Payment Gateway for Magento
By Billmate AB - [https://billmate.se](https://billmate.se/ "billmate.se")

## Documentation
[Installation manual in English](http://billmate.se/plugins/manual/Installation_Manual_Magento_Billmate.pdf)

[Installation manual in Swedish](http://billmate.se/plugins/manual/Installationsmanual_Magento_Billmate.pdf)

## Description

Billmate Gateway is a plugin that extends Magento, allowing your customers to get their products first and pay by invoice to Billmate later (https://www.billmate.se/). This plugin utilizes Billmate Invoice, Billmate Card, Billmate Bank and Billmate Part Payment.

## Important Note
* The automatic order activation on status change is supported from Magento version 1.7 and above.
* When updating to 3.0 please delete folder /app/code/community/Billmate/Common as this is moved to local.

## COMPATIBILITY Magento versions
1.7 - 1.9.3

# Supported Checkouts
* Templates Master Firecheckout. 
* Streamcheckout.
* Idev - One step checkout.
* Standard multi and onestepcheckout.

## Installation

1. Download the latest release zip file.
2. Extract the zip file.
3. Upload the zip files contents into the Magento root.
4. Go To "System" -> "Cache Management" -> "Clear All Caches".
5. Configure the general settings under "System" --> "Configuration" --> "Billmate general settings". 
6. If you get 404 error when heading to the general settings. Log out of admin and log in again.
7. Configure payment method specific settings under "System" --> "Configuration" --> "Payment Methods".
8. Make a test purchase for every payment method to verify that you have made the correct settings.

[Link to our Configuration Manual](https://billmate.se/plugins/manual/Installation_Manual_Magento_Billmate.pdf)

## Known issues
* Magento version 1.6 does not support different VAT rates on product the correct way and therefor Billmate Payment Gateway does not support it.
* I entered the correct credentials but still get error code "9011 Invalid credentials". This could be due to that the current store config is not correct. [Follow this guide to resolve it.](https://github.com/Billmate/magento/wiki/Common-issues,errors-&-solutions#user-content-cant-place-orders-even-though-you-have-entered-the-correct-credentials)


##How to place Billmate logo on your site.
Copy the code below for the size that fits your needs.

###Large

<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_l.png" alt="Billmate Payment Gateway" /></a>

`<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_l.png" alt="Billmate Payment Gateway" /></a>`

###Medium

<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_m.png" alt="Billmate Payment Gateway" /></a>

`<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_m.png" alt="Billmate Payment Gateway" /></a>`

###Small

<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_s.png" alt="Billmate Payment Gateway" /></a>

`<a href="http://billmate.se"><img src="https://billmate.se/billmate/logos/billmate_cloud_s.png" alt="Billmate Payment Gateway" /></a>`

## Changelog

### 3.3.3 (2019-05-16)
* Fix - Custom pay uses Billmate's new logos

### 3.2.2 (2019-04-29)
* Feature - Settings for Terms and Privacy Policy pages

### 3.2.1 (2019-04-24)
* Fix - Added payment plan id to requested data for Partpayment method.

### 3.2.0 (2019-04-02)
* Feature - Added ability to install via modman.
* Feature - Changed payment method logic to use joined payment method logic.
* Fix - Some refactoring done for cleaner code.
* Fix - Cleaned up admin panel and removed options that are no longer in use.
* Fix - Locked checkout iframe when shipping methods are changing.
* Fix - Refund Swish payments.
* Fix - Default shipping method.
* Fix - Custom pay did not work while checkout was active.

### 3.1.0 (2018-05-28)
* Feature - Settings for Terms and Privacy Policy pages

### 3.0.8 (2017-07-24)
* Fix - Sometimes orderstatus gets blank.

### 3.0.6 (2017-07-20)
* Fix - Escape quotation chars. 
* Fix - Change the behaviour with shipping discounts.
* Fix - Tax calculation when prices are set including tax.
* Fix - Coupon Code validation messages.

### 3.0.5 (2017-04-18)
* Enhancement - Tweaked the amounts of updating checkout.
* Enhancement - Common callback.

### 3.0.4 (2017-03-22)
* Enhancement - Improved thank you page.
* Enhancemnet - Improved scrollfocus.

### 3.0.3 (2017-03-20)
* Fixes for better checkout flow. 

### 3.0.2 (2017-02-13)
* Fix - Javascript on Postmessage fix.
* Fix - Empty array as Shipping.

### 3.0.1 (2017-02-10)
* Fix - Callback/Accept - Card and bankpayment.

### 3.0 (2017-02-08)
* First version of Billmate Checkout.

### 2.2.3(2017-02-07)
* Enhancement - Added possibility to add other statuses to check against Billmate.

### 2.2.2(2016-12-06)
* Enhancement - Improved addressvalidation. 
* Enhancement - Improved status handling by cron to fetch statuses from Billmate.

### 2.2.1(2016-11-23)
* Fix - Bundle product calculation. 
* Enhancement - Option to choose if invoice fee should be shown incl tax in checkout/cart or not.

### 2.2(2016-07-14)
* Enhancement - Improved card and bank logic for creating order after valid/pending payment. 
* Compatibility - Enterprise version.


### 2.1.9 (2016-02-17)
* Fix - Improved multicurrency calculations.
* Fix - Multistore settings fixed.

### 2.1.8 (2016-01-29)
* Fix - Multiple order mails. 
* Fix - Observer for Cancel order.

### 2.1.7 (2016-01-08)
*Fix - Shipping taxrate calculation.

### 2.1.6 (2015-12-30)
* Fix - Callback issues.

### 2.1.5
* Compatibility - Mail queue after Magento 1.9.1
* Enhancement - Use of magento getReservedOrderId to send order numbers.

### 2.1.4
* Fix - Company Firecheckout
* Enhancement - Cleaned up logging.

### 2.1.3
* Fix - Company that is logged in. 


### 2.1.2 (2015-11-16)

* Fix - Multiple order emails. 

### 2.1.1 (2015-11-13)
* Fix - No redirect in callback process. 

### 2.1 (2015-10-07)
* Fix - Canceled order issue.
* Enhancement - Its possible to set a invoice logo per store or website.
* Fix - Street validation issue.
* Fix - Some class name changes.


### 2.0.9 (2015-08-17)
1 commit

* Firecheckout - Validate SSN when customer is logged in.

### 2.0.8 (2015-08-13)
2 issues closed 5 commits

* Fix - Company name billmatepopup
* Translation - SSN validation

### 2.0.7 (2015-08-03)
2 issues closed 2 commits

* Fix - Change our Ajax function to be compatible with different blocks that are added f.ex live chats
* Translation - Improved translation

### 2.0.6 (2015-07-16)
Clean up
1 issue closed 1 commit

* Clean up - Not needed stuff.

### 2.0.5 (2015-07-14)
Minor bug fixes
1 issue closed 3 commits

* Fix - Cart contents remains in cart if Customer click back button in card and bankpayment

### 2.0.4 (2015-06-22)
Minor bug fixes
3 issue closed 11 commits

* Fix - Personnumber in session to persist if customer Reloads checkout
* Fix - Updated Version number
* Fix - Use Iso codes instead of Country names
* Fix - Order Confirm email, card and bankpay
* Fix - Logged In customer and getAddress FireCheckout

### 2.0.3 (2015-06-03)
Minor bug fixes
1 issue closed and 2 commits

* Fix - An issue when using HTTPS in the checkout is now fixed.

### 2.0.2 (2015-05-29)
Minor bug fixes
0 issues closed and 5 commits

* Fix - Improvement for how http & https work with the redirection. POST or GET.
* Fix - Getadress works for person again (bug introduced in 2.0.1)

### 2.0.2 (2015-05-29)
Minor bug fixes
0 issues closed and 5 commits

* Fix - Improvement for how http & https work with the redirection. POST or GET.
* Fix - Getadress works for person again (bug introduced in 2.0.1)

### 2.0.1 (2015-05-26)
Minor bug fixes and improvements.
2 issues closed and 17 commits

* Fix - Get adress with company now works as it should.
* Feature - Invoice fee is dynamically displayed in the checkout in the title.

### 2.0 (2015-05-11)
Total rewrite of the plugin. The plugin now works with Billmate API version 2.0, [http://billmate.se/api-integration/](http://billmate.se/api-integration/ "http://billmate.se/api-integration/").
Total of 82 issues closed and 154 commits.

* Feature - Manually fetch new PClasses
* Feature - Automaticlly fetch new PClasses once a week and if they are out of date.
* Feature - Possibility to include a special CSS file for the Billmate Module.
* Feature - Out of the box support for Magento standard checkout and FireCheckout.
* Feature - Possibility to use getadress for Magento standard checkout and FireCheckout.
* Feature - Autoactive the order in Billamte Online when you invoice the order in Magento backend.
* Feature - Validate Billmate Credentials in the common module
* Fix - Keeps cart contents after a cart content has been canceled.
* Fix - Now uses best practice for install scripts.
* Fix - Changed the way billmatepopup.js is included.
* Fix - Improved translations for English and Swedish.
* Fix - Removed Billmate as a Payment Method for orders created manually through the Magento backend since it's not supported.
* Fix - Orders containing articles with different VAT rates are now handeld correctly.
* Tweak – Optimized the rounding function.
* Tweak – ID & Secret are now in a commonmodule, only need to enter them once.
* Tweak - Improved commenting on orders.
