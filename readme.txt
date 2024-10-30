=== PortOne for WooCommerce ===
Contributors: chaifinport
Tags: chaipay, chaiport, payments, woocommerce, ecommerce, portone
Requires at least: 3.9.2
Tested up to: 6.5.2
Stable tag: 3.0.0
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows you to use PortOne payment platform with the WooCommerce plugin.

== Description ==

This is the official PortOne payments plugin for WooCommerce. Allows you to accept credit cards, debit cards and wallet payments with the WooCommerce plugin.

It uses a seamless integration, allowing the customer to pay on your website without being redirected away. This works across all browsers, and is compatible with the latest WooCommerce.

This plugin supports both, legacy and the new Blocks based checkout.

This is compatible with WooCommerce>=2.4. It has been tested upto the 8.7.0 WooCommerce release.

== Installation ==

1. Install the plugin from the WordPress Plugin Directory, search for PortOne Payment Plugin.
2. To use this plugin correctly, you need to be able to make network requests. Please make sure that you have the php-curl extension installed.
3. Please set the Permalink to "Post Name" in WP Dashboard->Settings->Permalinks, the plugin webhooks and redirects won't work if the permalink is not set as required.

== Dependencies ==

1. WordPress v3.9.2 and later
2. Woocommerce v2.6 and later
3. PHP v5.6.0 and later
4. php-curl extension

== Configuration ==

1. Get your API Keys at Merchant Portal
2. After installing you need to activate PortOne plugin from plugins page in your WordPress dashboard
3. Go to <b>Setting</b> in WooCommerce page of your dashboard and click on <b>Payments</b> tab
4. Enable the PortOne Plugin and then click on <b>Manage</b>
5. You need to check the <b>Enable PortOne Gateway</b>
6. Enter the keys from step 1 and click on save
    ```
    Publishable Key ---> PortOne Key
    Private Key     ---> PortOne Secure Secret Key
    ```
7. You'll have to add the webhook URL given in the settings to the Merchant Portal Webhooks section
8. You've successfully integrated PortOne Plugin on your store.

== Important Links ==

[API Docs](https://docs.portone.cloud/)
[Merchant portal](https://admin.portone.cloud/)