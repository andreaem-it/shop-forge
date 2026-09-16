=== ShopForge ===
Contributors: andreaemili
Tags: woocommerce, account, wishlist, returns, rma
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 9.9
Stable tag: 1.13.3
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
* **Notifications** — an in-account notification center fed by real events (order status, tickets, returns, RMA, quotes, back-in-stock wishlist items).
* **Loyalty Points** — customers earn points on completed orders (reversed if later refunded/cancelled) and redeem them for a discount coupon. Earn rate, point value and minimum redemption are configurable.
* **PDF Receipts** — generates a real server-side PDF receipt for each order (not a fiscal invoice), with a choice of visual templates, your logo, company details and a footer note. Downloadable from the order page and the customer account, with one-click email delivery.
* **Italian Fiscal Checkout Fields** — adds Customer type (Private/Business), Tax code, VAT number, SDI code and PEC to the block checkout, natively (WooCommerce Blocks Additional Fields API). Fields are shown and required based on the selected customer type. Classic (shortcode) checkout is not covered.

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

= Admin tools =

* **Open-requests alert** — editing an order shows a banner if it has open support tickets, withdrawal requests or RMA, linking straight to each one.
* **Dashboard widgets** — two widgets on the native WordPress Dashboard: a sales overview (orders/revenue, 7-day chart) and a customer-requests overview (open counts + recent activity across tickets, withdrawals, RMA and quotes).
* **Messages** — a single admin page aggregating every customer request (tickets, withdrawals, RMA, quotes) with type/status filters and search, each row linking to where it is actually managed. Data stays where it already lives; this is an overview, not a new store.

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

= 1.19.0 =
* New Gutenberg blocks and Elementor widgets for the main product shortcodes (price VAT box, delivery estimate, buy now, stock status, product FAQ) — usable directly from the block/Elementor inserter instead of pasting shortcodes.
* New "Export/Import" settings tab: download all ShopForge settings as JSON and re-import them on another site (license excluded).
* Wishlist: added a public share link (read-only, no login required) per customer.
* REST API: new `shopforge/v1/wishlist` (read/add/remove) and `shopforge/v1/rma` (read-only, own requests) endpoints.
* Support screens (RMA, returns, quotes, receipts) now check a filterable `shopforge_support_capability` instead of hard-coded `manage_woocommerce`, so a site can grant support access without full store-management rights.
* Added `wpml-config.xml` and a Polylang `pll_copy_post_metas` filter so product FAQ/compatibility/datasheet fields are recognized as translatable.
* Added a minimal assert-based test for the loyalty points/redeem-value math (`tests/test-loyalty-math.php`).

= 1.18.1 =
* Customizer: added top/right/bottom(/left) margin controls for the wishlist button, add-to-cart button and quantity selector (can be negative, useful to nudge vertical alignment).
* Fixed the wishlist button's `margin-top: 10px` being hard-coded with no override, and added `align-self: center` to the three controlled buttons so they line up on the same row even when heights differ (e.g. "Auto" width mode).

= 1.18.0 =
* Customizer: added height, min-width and font-size controls for the wishlist button and the "Add to cart" button, and height/width/font-size for the quantity selector's +/- buttons (0 = no override, theme default kept).

= 1.17.1 =
* Customizer: the three control groups are now collapsible sections (closed by default) instead of one long stacked list — clicking an element in the live preview (wishlist button, add-to-cart button, quantity selector) opens and scrolls to its matching section instead of the click triggering the real action (add to cart / toggle wishlist).

= 1.17.0 =
* Customizer: added color overrides (background/icon/text) and a real "stacked" width mode (forces its own row via JS, not just a wider box) for the wishlist button, plus the same width modes and colors/corner-radius controls for the "Add to cart" button and the quantity selector.
* Customizer UI translated to Italian.

= 1.16.0 =
* New "Customizer" settings tab: live preview of the actual product page (real iframe, not a mock) with hover-highlighted containers (quantity selector, wishlist button, add-to-cart button/form) and live controls for the wishlist button's width/gap/label.
* Fixed the wishlist button on the product page forcing `width: 100%`, which broke layout in flex rows (e.g. The7's quantity + wishlist + add-to-cart row) — now configurable from the Customizer, default "auto".

= 1.15.1 =
* `[wc_price_iva_box]` AJAX endpoint now returns an error instead of a silent empty box when the product/variation id is invalid.
* Clamped `_shopforge_delivery_days` to a non-negative value on save (a manually forged negative value could have pushed delivery estimates into the past).

= 1.15.0 =
* New "Integrations" settings tab: toggle The7 and Elementor-specific compatibility workarounds on/off (account sidebar full-width, quantity-button forwarding, empty-widget hiding). Both enabled by default.

= 1.14.3 =
* Fixed the "-" quantity button not working: it was the visually-hidden duplicate button that had the actual decrement logic, so native `.minus`/`.plus` clicks are now forwarded to it instead of hiding it outright.

= 1.14.2 =
* `[wc_price_iva_box]` now also recalculates on quantity change (unit price × qty), not just on variation change.
* Hid duplicate `.qty-minus`/`.qty-plus` buttons inside `.quantity.buttons_added`, keeping only WooCommerce's native `.minus`/`.plus`.

= 1.14.1 =
* Version bump to force cache/asset refresh (no functional change).

= 1.14.0 =
* Fixed `[wc_price_iva_box]` not updating when a product variation is selected: it now refreshes via AJAX on variation change.
* Hid the redundant `.woocommerce-variation-price` next to the `[wc_price_iva_box]` box.

= 1.13.3 =
* Zeroed out the theme's default `padding-right: 60px` on the account sidebar, which left an empty 60px gap inside the box before its right edge.

= 1.13.2 =
* Fixed the account sidebar width for real this time: the theme/WooCommerce ships a default `float: left; width: 300px` for the same selector, which wins over our layout when the flex parent context doesn't apply on a given theme, ignoring our flex-basis entirely. Neutralized the float and made our width authoritative with `!important`, on both the desktop and mobile rule.

= 1.13.1 =
* Widened the account sidebar (260px → 280px): with longer labels like "Repairs & Warranty" ("Riparazioni e Garanzia" in Italian), the previous width truncated the text with an ellipsis instead of showing it in full.

= 1.13.0 =
* Unified the Dashboard's "recent orders" mini-table with the real Orders page: uppercase spaced column headers and a pill-style "View" button, instead of a plain text link.
* Renamed the "Support & Returns" account menu entry to "Requests" — next to "Repairs & Warranty" (RMA) the old name was a confusing near-duplicate. The section page title was renamed to match.

= 1.12.10 =
* Fixed the active account-menu item's blue highlight not filling the full width of the row — it inherited its background from a theme/WooCommerce default with a different box model than our full-width flex link, leaving a visible gap on the right. Now set explicitly.

= 1.12.9 =
* Actually fixed "Errore di rete" on RMA submission (1.12.8's fix wasn't enough): the script's data (ajaxUrl, nonce) was never printed at all on the reporting site, because it relied on wp_footer() firing — which some page templates (builders, custom canvas templates) never call, silently dropping any script queued with in_footer. The RMA request/message form scripts, the withdrawal (recesso) modal script, and the support-ticket script are now all printed inline at the point they're needed, independent of wp_head()/wp_footer().
* Fixed the Notifications menu item text wrapping onto two lines when the unread badge is shown.

= 1.12.8 =
* Fixed the notifications badge showing as literal `<span class="shopforge-notif-badge">1</span>` text in the account menu — the badge HTML was concatenated into the menu label, which the navigation template correctly escapes with esc_html(). The badge is now rendered as real markup by the template itself, only for the Notifications item.
* Renamed the unclear "Product Support" account menu entry to "Repairs & Warranty" (and the matching admin menu/page titles), to distinguish it from "Support & Returns" (the legal withdrawal module) and make clear what it's for.
* Fixed "Errore di rete" when submitting an RMA/repair request: the request's JS was never registered if is_account_page() didn't recognize the page at the early wp_enqueue_scripts point, silently breaking the later wp_enqueue_script()/wp_localize_script() calls made when the form actually renders — ajaxUrl ended up undefined client-side, and the POST went to a nonsense URL. Script registration is now unconditional; the same latent issue was also fixed for the returns (withdrawal) and shipment-tracking ticket scripts.

= 1.12.7 =
* Actually fixed the "translation loaded too early" notice: 1.12.5's approach (loading the textdomain earlier) had it backwards — WordPress wants translations loaded at init or later, and shopforge_load_modules() was calling __() (via the module registry) on plugins_loaded regardless of when load_plugin_textdomain() itself ran. Split the module registry into an untranslated shopforge_modules_registry_raw() (used by the plugins_loaded-time module loader) and a translated shopforge_modules_registry() (used everywhere labels/descriptions are actually displayed, all safely after init). load_plugin_textdomain() is back on init, as WordPress recommends.

= 1.12.6 =
* Found the real cause of the RMA "Invalid post type" error: the post type slug was `shopforge_rma_request`, 21 characters — one over WordPress's 20-character limit — so `register_post_type()` always failed silently (visible only with WP_DEBUG on). Renamed to `shopforge_rma` everywhere (admin screens, columns, bulk actions, metaboxes, exports, uninstall cleanup). Added a one-time migration that moves any existing RMA request posts from the old, never-actually-registered post type to the new one, so nothing already submitted by customers is lost.

= 1.12.5 =
* Fixed a regression from 1.12.4: deferring shopforge_load_modules() to init made module files register their own init hooks (e.g. the RMA post type) from inside an already-running init pass, which WordPress doesn't reliably re-run — the shopforge_rma_request post type silently never registered, breaking edit.php?post_type=shopforge_rma_request even with the module active and licensed. Reverted to loading modules synchronously on plugins_loaded, and instead moved load_plugin_textdomain() earlier (plugins_loaded priority 1) to fix the "translation loaded too early" notice without the nested-hook risk.

= 1.12.4 =
* Fixed a WordPress 6.7+ "translation loaded too early" notice: shopforge_load_modules() ran directly on plugins_loaded and immediately built the module registry (which calls __() for every label/description), before init. Deferred to init (priority 5).

= 1.12.3 =
* Fixed the "Open RMA" (and "Open withdrawals" / "Pending quotes") badge on the Dashboard requests widget: it always linked to those modules' pages even when disabled, causing an "Invalid post type" error for RMA. Each badge now only shows if its module is active.

= 1.12.2 =
* Moved the version history out of this file into `CHANGELOG.md`, keeping only the latest entry here.

= 1.12.1 =
* Fixed the Receipts module: it registered a "Receipts" account menu item and a /shopforge-receipts/ endpoint with no page behind it, so visiting that URL silently fell back to the dashboard. Removed — receipts are downloaded from each order's own page, not a dedicated account tab.
* Notifications now always appears as the second item in the account menu, right after Dashboard.

Full version history: see `CHANGELOG.md` in the plugin root.
