# Billmate Payment Gateway for Magento
By Billmate AB - [https://billmate.se](https://billmate.se/ "billmate.se")

## Description

Billmate Gateway is a plugin that extends Magento, allowing your customers to get their products first and pay by invoice to Billmate later (https://www.billmate.se/). This plugin utilizes Billmate Invoice, Billmate Card, Billmate Bank and Billmate Part Payment.

## Important Note
* The automatic order activation on status change is supported from Magento version 1.7 and above.

## Installation

1. Download the latest release zip file.
2. Extract the zip file.
3. Upload the zip files contents into the Magento root.
4. Configure the general settings under "System" --> "Configuration" --> "Billmate general settings". 
5. Configure payment method specific settings under "System" --> "Configuration" --> "Payment Methods".
6. Make a test purchase for every payment method to verify that you have made the correct settings.

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
