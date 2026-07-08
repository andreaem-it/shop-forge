=== ShopForge ===
Contributors: andreaemili
Tags: woocommerce, account, wishlist, returns, rma
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 9.9
Stable tag: 1.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Modular WooCommerce plugin: redesigned account area, shipment tracking, wishlist, returns/RMA, quotes, notifications and theme skins.

== Description ==

ShopForge extends WooCommerce with a set of independent modules and a
redesigned account area. Every module can be enabled or disabled on its own,
so you only load the code and CSS you actually use.

= Core features =

* **Account area styles** — card-based dashboard, order list, status badges.
* **Shop/catalog styles** — restyled cart and checkout (classic and block checkout).
* **Custom colors** — a full color palette editable from the settings screen, injected as CSS variables.
* **Custom dashboard** — replaces the default WooCommerce dashboard with order stats, a tracking widget and address cards.
* **Themes (skins)** — three ready-made layouts (Clean, Boxed, Cards) selectable globally or per page (cart / checkout / account / other).

= Modules =

* **Shipment tracking** — tracking widget on the order page, powered by the 17track API (a free API key is required, see Settings).
* **Wishlist** — customers save favorite products and find them in their account.
* **Quotes** — customers request a custom quote for a list of products; the shop owner replies from a dedicated admin page.
* **Support & Returns** — a two-step withdrawal ("recesso") flow compliant with EU consumer law, with an automatic confirmation email.
* **Product Support (RMA)** — structured repair / replacement / refund requests per order line, with a message thread, CSV export, print view and stats.
* **Notifications** — an in-account notification center fed by real events (order status, tickets, returns, quotes).

= Shortcodes =

* `[shopforge_variation_description]` — shows the description of the selected product variation.
* `[wc_price_iva_box]` — price box with tax included/excluded.
* `[data_consegna_prodotto]` — estimated delivery date based on business days and public holidays.
* `[buy_now_button]` — adds to cart and redirects straight to checkout.
* `[stock_status_text]` — availability label with a colored dot.
* `[product_faq]` — product FAQ, managed from a metabox on the product edit screen.
* `[product_compatibility]` — compatibility list, managed from a metabox.
* `[product_datasheets]` — PDF datasheets, managed from a metabox.

Full attribute reference is available in-app under ShopForge → Shortcodes.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/shopforge`, or install the zip through **Plugins → Add New → Upload Plugin**.
2. Activate the plugin. WooCommerce must be active.
3. Go to **ShopForge** in the admin menu and enter your license key under the **License** tab.
4. Enable the modules and features you need under the **Modules** tab.
5. Optionally pick a theme (skin) and customize colors in the same screen.
6. If you enable **Shipment tracking**, get a free API key at 17track.net and paste it into the *17track API key* field under Configuration.

== Frequently Asked Questions ==

= Does this work with the WooCommerce block-based cart and checkout? =

Yes. The catalog/shop styles cover both the classic shortcode-based cart/checkout and the block-based ones, and the skin system applies to both.

= Can I use only some modules? =

Yes, every module and core feature has its own toggle under ShopForge → Modules. Disabled modules load no code, no CSS, and register no endpoints.

= Does the plugin add its own tracking service? =

No, shipment tracking relies on 17track.net's public API. You need to register your own free API key on their site and paste it in ShopForge → Modules → Configuration.

= Is the plugin translation-ready? =

Yes, all strings are wrapped for translation with the `shopforge` text domain. An Italian translation is bundled; a `.pot` file is included under `languages/` for adding more.

== Changelog ==

= 1.8.0 =
* Full English base + Italian translation, translation-ready throughout.
* FontAwesome bundled locally, no external kit dependency.
* Added `uninstall.php` to clean up plugin options on removal.
* Added a themeable skin system (Clean / Boxed / Cards) selectable globally or per page.
* 17track API key is now configurable from Settings instead of hardcoded.

= 1.7.x =
* Cart/checkout layout and theme-compatibility fixes.
* Quantity selector redesign.

= 1.6.0 =
* Added the RMA module, product-info shortcodes, unified admin page.
