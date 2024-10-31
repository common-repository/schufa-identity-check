=== Identitätscheck / Identitycheck ===
Contributors: baekerIT
Donate link: https://baeker-it.de
Tags: woocommerce, identitycheck, schufa, q-bit, tpd2
Requires at least: 2.0
Tested up to: 4.9
Stable tag: 3.1.3
Requires PHP: 5.3
WC tested up to: 3.2.5

License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This Plugin checks the Address Data of a WooCommerce Customer. If this check fails, no Order will be created.

With this Update you need to purchase a new License offered through Digistore24. In future, you will be able to manage your Purchase through Digistore24.

== Description ==

With this Update to Version 3.0, the Plugin can only be run through our Compute Center. You only need your UserID and Password from SCHUFA and your Digistore24 Order ID.

We redesigned the whole Plugin, including the Identity Check Algorithm. Every check which will be run through the SCHUFA will be stored in YOUR Database. Additionally you will get an Overview,

which will show you the Details of each Identity Check.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/schufa-identity-check` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the SCHUFA Identitätscheck -> Plugin Einstellungen screen to configure the plugin

Minimum required PHP Version: 5.3 - compatible with PHP 7
== Frequently Asked Questions ==

= Can I run this Plugin directly after Installation? =

If you do have Accessdata from SCHUFA (User ID and Password) and your Order ID from Digistore24, yes.

= Will Customers be double checked? =

No, they will only checked twice, if they changed the invoice address.

== Changelog ==

= 3.1.0 =

Only processing the Identitycheck if Customer lives in Germany

= 3.0 =

Completely Redesign and Modernizing of the Plugin

NEW FEATUERES:

    -> We reduced the time needed for the identitycheck
    -> We are now using our own WordPress Table to save our Checks
    -> We switched to Digistore24 in Ordner to manage Subscriptions
    -> We Changed from monthly and yearly Payment to three Packages
    -> You can now set the minimum values for each field even the QBIT
    -> If a Customer did not fill out any field correctly, the plugin will show him an Error Message
    -> You do not more need your own Certificate from the SCHUFA - we will take care of it


BUGFIXES:

    -> The new Algorithm will find Errors to 99.9 % and needs lower than the half time to Identify a Customer.
    -> The Manual approvement of a Single User is now two-step based.

= 2.9 =

Disabling Requests to SCHUFA and confirming the order, if Customer lives not in germany.

= 2.8 =

Bugfix in the ByPass Function

= 2.7 =

Added a Button for bypassing the Identity Check. Easily create a Customer and use the Button to set his Status as Checked

= 2.6 =

Declaration of Needed Fields changed in cause of some Fatal Errors in some cases.

= 2.5 =

Small Update for legal Reasons

= 2.4 =

Did some Cleanup workings to prevent Error Messages

= 2.1 =

Added a button in the User Profile which allows to mark Users as checked / unchecked.

= 2.0 =

Major Update:

This Version is the first, using our newly developed API

== Upgrade Notice ==

You will need to Purchase a new License from Digistore24.com - You will find direct Product Links at https://identitätscheck-plugin.de

== Screenshot ==

1. /trunk/assets/screenshot-1.png
2. /trunk/assets/screenshot-2.png
3. /trunk/assets/screenshot-3.png