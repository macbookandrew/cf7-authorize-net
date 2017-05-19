=== Contact Form 7 to Authorize.net ===
Contributors: macbookandrew
Tags: contact form, contact form 7, cf7, contactform7, forms, form, payment, processing, credit card, merchant, Authorize.net, Visa, MasterCard, American Express
Donate link: https://cash.me/$AndrewRMinionDesign
Requires at least: 4.3
Tested up to: 4.7.2
Stable tag: 1.1.3
License: GPL2

Adds Authorize.net support to Contact Form 7 forms

== Description ==
Adds Authorize.net support to Contact Form 7 forms, adding the capability to match specific form fields to Authorize.net payment fields.

=== Notes ===
Some fields are required by Authorize.net for the payment to go through:
    - Credit Card Number
    - Expiration Month
    - Expiration Year
    - CVV Code
    - Total Amount

Other fields may be required based on your Authorize.net settings:
    - Zip code
    - Billing address

A couple of fields have default values if you don’t explicitly set them (tip: use hidden fields if you don’t need them publicly available):
    - Order Description will default to the name of the contact form
    - Country will default to US

== Installation ==
1. Install the plugin
1. Go to Settings > CF7→Authorize.net to set your API keys
1. Edit a form and go to the “Authorize.net” tab to set the payment settings

== Changelog ==

= 1.1.3 =
 - Stop processing if field is set to be ignored

= 1.1.2 =
 - Fix function name conflict

= 1.1.1 =
 - Fix issue with all fields not being disabled if the form is ignored

= 1.1 =
 - Add support for shipping and order information
 - Fix billing information issues

= 1.0 =
 - First stable version
