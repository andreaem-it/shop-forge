# Changelog

## 1.12.8
* Fixed the notifications badge showing as literal `<span class="shopforge-notif-badge">1</span>` text in the account menu — the badge HTML was concatenated into the menu label, which the navigation template correctly escapes with esc_html(). The badge is now rendered as real markup by the template itself, only for the Notifications item.
* Renamed the unclear "Product Support" account menu entry to "Repairs & Warranty" (and the matching admin menu/page titles), to distinguish it from "Support & Returns" (the legal withdrawal module) and make clear what it's for.
* Fixed "Errore di rete" when submitting an RMA/repair request: the request's JS was never registered if is_account_page() didn't recognize the page at the early wp_enqueue_scripts point, silently breaking the later wp_enqueue_script()/wp_localize_script() calls made when the form actually renders — ajaxUrl ended up undefined client-side, and the POST went to a nonsense URL. Script registration is now unconditional; the same latent issue was also fixed for the returns (withdrawal) and shipment-tracking ticket scripts.

## 1.12.7
* Actually fixed the "translation loaded too early" notice: 1.12.5's approach (loading the textdomain earlier) had it backwards — WordPress wants translations loaded at init or later, and shopforge_load_modules() was calling __() (via the module registry) on plugins_loaded regardless of when load_plugin_textdomain() itself ran. Split the module registry into an untranslated shopforge_modules_registry_raw() (used by the plugins_loaded-time module loader) and a translated shopforge_modules_registry() (used everywhere labels/descriptions are actually displayed, all safely after init). load_plugin_textdomain() is back on init, as WordPress recommends.

## 1.12.6
* Found the real cause of the RMA "Invalid post type" error: the post type slug was `shopforge_rma_request`, 21 characters — one over WordPress's 20-character limit — so `register_post_type()` always failed silently (visible only with WP_DEBUG on). Renamed to `shopforge_rma` everywhere (admin screens, columns, bulk actions, metaboxes, exports, uninstall cleanup). Added a one-time migration that moves any existing RMA request posts from the old, never-actually-registered post type to the new one, so nothing already submitted by customers is lost.

## 1.12.5
* Fixed a regression from 1.12.4: deferring shopforge_load_modules() to init made module files register their own init hooks (e.g. the RMA post type) from inside an already-running init pass, which WordPress doesn't reliably re-run — the shopforge_rma_request post type silently never registered, breaking edit.php?post_type=shopforge_rma_request even with the module active and licensed. Reverted to loading modules synchronously on plugins_loaded, and instead moved load_plugin_textdomain() earlier (plugins_loaded priority 1) to fix the "translation loaded too early" notice without the nested-hook risk.

## 1.12.4
* Fixed a WordPress 6.7+ "translation loaded too early" notice: shopforge_load_modules() ran directly on plugins_loaded and immediately built the module registry (which calls __() for every label/description), before init. Deferred to init (priority 5).

## 1.12.3
* Fixed the "Open RMA" (and "Open withdrawals" / "Pending quotes") badge on the Dashboard requests widget: it always linked to those modules' pages even when disabled, causing an "Invalid post type" error for RMA. Each badge now only shows if its module is active.

## 1.12.2
* Moved the version history out of `readme.txt` into this file, keeping only the latest entry there.

## 1.12.1
* Fixed the Receipts module: it registered a "Receipts" account menu item and a /shopforge-receipts/ endpoint with no page behind it, so visiting that URL silently fell back to the dashboard. Removed — receipts are downloaded from each order's own page, not a dedicated account tab.
* Notifications now always appears as the second item in the account menu, right after Dashboard.

## 1.12.0
* Reworked 1.11.1's sub-tabs into real top-level tabs, reusing the same License/Shortcodes/Receipts tab navigation instead of a nested second-level one: Core features, Modules, Configuration, Theme and Colors are now their own pages, each with its own form and save action, so saving one section never touches another.

## 1.11.1
* The Modules settings page (Core features / Modules / Configuration / Theme / Colors) is now split into sub-tabs instead of one long scrolling page.

## 1.11.0
* New "Italian Fiscal Checkout Fields" module: Customer type (Private/Business), Tax code, VAT number, SDI code and PEC registered natively via the WooCommerce Blocks Additional Checkout Fields API — no third-party plugin needed, and the right fields are conditionally shown/required depending on the selected customer type. Only works with the block-based checkout.
* Full Italian translation for the new module (740/740 strings, 100% coverage).

## 1.10.4
* Account dashboard stat cards (Total orders / Processing / Delivered / Quotes) now use a dynamic column count instead of a fixed 4, so the row fills correctly when the Quotes module is disabled (3 cards).
* Loyalty points moved out of the stat cards into a small badge above the Log out button (star + number, "Loyalty points" label), shown only when the Loyalty module is active.

## 1.10.3
* Fixed the "Withdrawal request sent" card: the "All my returns" button was falling below the text instead of anchoring to the right. Both withdrawal cards now anchor their action button via flex + space-between on the card body.

## 1.10.2
* Fixed the "Withdrawal window expired" card: the withdrawal buttons now use the plugin's global primary color (one was hardcoded to an unrelated gray, the other was accidentally near-white with white text and effectively invisible).
* Removed a redundant wrapper and a dead CSS rule left over from that layout change; the button's right-anchoring was already handled by the card's flex layout.

## 1.10.1
* Fixed the Receipts settings tab rendering with no styling (borrowed CSS classes that only load on the Modules tab; the tab now ships its own scoped styles).
* Fixed 22 strings incorrectly translated by the automated merge (e.g. "Contact email" showing an unrelated Italian string) across the Receipts module and a few pre-existing WooCommerce template overrides.

## 1.10.0
* New PDF Receipts module: real server-generated PDF (Dompdf), 3 visual templates (Modern/Classic/Minimal), configurable company details/logo/footer note, one-click download from the order page and customer account, one-click email delivery. This is a receipt, not a fiscal invoice — no electronic-invoicing (SDI) integration.
* Italian translation completed for all new strings, 100% coverage verified against source (728/728).

## 1.9.1
* Removed all emoji from admin UI, notices and JS feedback text — plain text and native icons only.
* Completed the Italian translation: all strings introduced in 1.9.0 (Loyalty Points, order alert, dashboard widgets, Messages page) are now translated, 100% coverage verified against source.

## 1.9.0
* New Loyalty Points module: earn on completed orders (reversed on refund/cancel), redeem for a discount coupon, configurable earn rate/point value/minimum redemption.
* New admin order-edit alert for orders with open tickets, withdrawal requests or RMA.
* New WordPress Dashboard widgets: sales overview with a 7-day chart, and a customer-requests overview with recent activity.
* New unified "Messages" admin page aggregating tickets, withdrawals, RMA and quotes with filters and search.
* Notifications now also cover ticket/return/RMA status updates and wishlist back-in-stock.
* Auto-issue a store-credit coupon and set order status to Refunded when a withdrawal request is approved as refunded.
* Fixed returns modal not becoming visible, unified modal/support-panel text sizes, unified RMA form styling with the rest of the account UI.
* Fixed account navigation not stretching to full width outside the two-column layout.

## 1.8.0
* Full English base + Italian translation, translation-ready throughout.
* FontAwesome bundled locally, no external kit dependency.
* Added `uninstall.php` to clean up plugin options on removal.
* Added a themeable skin system (Clean / Boxed / Cards) selectable globally or per page.
* 17track API key is now configurable from Settings instead of hardcoded.

## 1.7.x
* Cart/checkout layout and theme-compatibility fixes.
* Quantity selector redesign.

## 1.6.0
* Added the RMA module, product-info shortcodes, unified admin page.
