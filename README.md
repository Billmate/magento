# Billmate Payment Gateway for Magento
By Billmate AB - [https://billmate.se](https://billmate.se/ "billmate.se")

## Description

Billmate Gateway is a plugin that extends Magento, allowing your customers to get their products first and pay by invoice to Billmate later (https://www.billmate.se/). This plugin utilizes Billmate Invoice, Billmate Card, Billmate Bank and Billmate Part Payment.

## Important Note
* The automatic order activation on status change is supported from Magento version 1.7 and above.

## Installation

Read following information to install the plugin.

* XXX
* XXX

* You will find four plugins, billmateinvoice, billmatepartpayment, billmatecardpay & billmatebank
* Extract zip file under prestashop_root/modules

## Known issues
* Magento version 1.6 does not support different VAT rates on product the correct way and therefor Billmate Payment Gateway does not support it.


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

### 2.0 (2015-04-22)
Total rewrite of the plugin. The plugin now works with Billmate API version 2.0, http://billmate.se/api-integration/
Total of XX issues closed and XX commits.

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
