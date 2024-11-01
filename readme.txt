=== Simple Stripe Checkout ===
Contributors: growniche
Tags: simple, stripe, checkout
Requires at least: 4.9.13
Tested up to: 6.3.2
Stable tag: 1.1.28
License: GPL v3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==

It is a plug-in that can install the payment button of the payment platform "Stripe".
<a href="https://s-page.biz/ssc/">https://s-page.biz/ssc/</a>

== Installation ==

This plugin can be installed directly from your WordPress dashboard.

1. Go to the Plugins menu and click Add New.
2. Search for "Simple Stripe Checkout".
3. Click "Install Now" next to the "Simple Stripe Checkout" plugin.
4. Activate the plugin.
5. In the Simple Stripe Checkout menu> Preferences, set the Stripe's Public Key and Secret Key.
6. In the Simple Stripe Checkout menu> New Registration, register the product name, price, currency, provider, and button name.
7. In the Simple Stripe Checkout menu> Product List, copy the Shortcode.
8. Paste the [Shortcode] on any post or page.

== Screenshots ==

== Frequently Asked Questions ==

== Changelog ==

= 1.0.0 =
* 2020-03-01 First release
= 1.0.1 =
* 2020-04-04 Minor correction
= 1.0.2 =
* 2020-04-05 Fixed an issue where the Stripe purchase button was not displayed in WordPress 5.4
= 1.0.3 =
* 2020-04-06 Fixed redirect process to payment completion page
= 1.0.4 =
* 2020-04-10 Minor correction
= 1.1.0 =
* 2020-11-08 Supports regular payments
= 1.1.1 =
* 2020-11-15 Changed the default order of product list to descending order of product code
* 2020-11-15 Fixed so that various fixed pages can be automatically generated even if it is enabled on the site network.
= 1.1.2 =
* 2020-11-19 Fixed that the plugin cannot be enabled (an error occurs)
= 1.1.3 =
* 2020-11-21 Fixed failure to transition to the payment completion page for lump-sum payment products
= 1.1.4 =
* 2020-11-22 Changed the format of the redirect URL to the payment completion page, etc. from "Post name" to "Basic" in the permalink settings.
= 1.1.5 =
* 2020-11-24 Fixed that it was treated as a batch product when purchasing a regular payment product
= 1.1.6 =
* 2020-11-24 Added product duplication function
= 1.1.7 =
* 2020-12-19 Corrected the next scheduled withdrawal date stated in the body of the email when purchasing a regular payment product with 0 days of trial
= 1.1.8 =
* 2020-12-30 Multilingual support (Japanese)
= 1.1.9 =
* 2021-01-20 Changed Plugin URI
= 1.1.10 =
* 2021-06-06 Compatible with WordPress version 5.7.2
= 1.1.11 =
* 2021-10-02 Fixed a bug in deleting one-time payment products
= 1.1.12 =
* 2022-05-08 Add a lead to the Stripe Customer Portal
= 1.1.17 =
* 2022-05-28 Add edit field for customer portal URL and include it in the email.
= 1.1.23 =
* 2023-05-14 Adds PHP8 support. Added support for Stripe SDK10.
= 1.1.24 =
* 2023-05-14 bug fix.
= 1.1.25 =
* 2023-05-20 bug fix.
= 1.1.26 =
* 2023-11-11 added billing-cycle.
= 1.1.28 =
* 2023-12-27 bug fix.