# Backorder ETA Display — User Guide

A complete reference for configuring and using the module. If you're just installing, start with `README.md`.

---

## What the module actually does

In one sentence: **shows customers a clear ETA for backordered items across product page, cart, checkout, and the order confirmation email.**

In more detail:

1. **Adds a `backorder_eta` text attribute** to every product — you type whatever ETA copy you want ("Ships in 5-7 business days", "Available Dec 15", "Pre-order — January 5")
2. **Falls back to a store-wide default ETA** for products with no specific value
3. **Detects backorder status** automatically by checking stock state — out of stock, partial backorder, qty depleted with backorders enabled
4. **Renders the ETA** in four places: product page badge, cart summary, checkout summary, order email
5. **Hides automatically** when the product is genuinely in stock — no clutter

## Admin configuration

Navigate to **Stores → Configuration → eTechFlow → Backorder ETA Display**.

### General section

| Field | Default | What it does |
|---|---|---|
| **Enabled** | No | Module on/off |
| **Default ETA** | (empty) | Used when a product has no per-product `backorder_eta` value. Example: `Ships in 5-7 business days` |
| **ETA Label Prefix** | (empty) | Prepended to the default ETA only. Example: `Estimated delivery: ` → renders as `Estimated delivery: Ships in 5-7 business days`. Per-product ETAs ARE NOT prefixed (assumed to be already formatted as the merchant wants). |

### Display section

| Field | Default | What it does |
|---|---|---|
| **Show on Product Detail Page** | Yes | Render the badge under the price on the PDP |
| **Show on Cart Page** | Yes | Render the summary block at the top of the cart page |
| **Show on Checkout** | Yes | Render the summary at the top of the checkout shipping step |
| **Show in Order Confirmation Email** | Yes | Append the styled HTML section to the order confirmation email |
| **Badge Style** | warning | Colour theme: `warning` (amber), `info` (blue), `neutral` (slate-grey). Applied consistently across all four locations + email. |

### License section

| Field | What to enter |
|---|---|
| **License Key** | Paste the key from your purchase email |

## Per-product attribute

Catalog → Products → edit any product → **eTechFlow Shipping** group → **Backorder ETA** text field.

Type whatever you want:

| Use case | Example ETA value |
|---|---|
| Standard restocking delay | `Ships in 5-7 business days` |
| Specific date (pre-order) | `Available December 15` |
| Range | `Ships in 3-4 weeks` |
| Conditional | `Backorder — ships from supplier in 10-14 days` |
| Marketing-friendly | `Reserve yours — ships January 5` |

Leave it blank for products that should use your store-wide Default ETA.

## What customers see

### Product detail page

A badge appears under the price block when the product is on backorder. Layout:

```
[●]  Ships in 5-7 business days
```

On Luma — coloured pill with inline CSS, matching the configured Badge Style.

On Hyvä — Tailwind classes, rounded border-left card, inline truck SVG icon, dark-mode variants, subtle fade-in animation. Respects `prefers-reduced-motion`.

### Cart page

A summary block at the top of the cart, listing each backorder item:

```
[clock icon]  Estimated delivery times

Sterling Wireless Mouse           Ships in 5-7 business days
Anker Power Bank 20k mAh          Available December 15
```

In-stock items in the cart are not listed.

### Checkout

Same summary block as the cart, rendered at the top of the checkout page.

On Hyvä Checkout, the summary includes a dismiss button (×) — customer can hide the summary if they want, but it remembers nothing across page loads.

### Order confirmation email

A styled HTML table appended below the items list:

```
┌──────────────────────────────────────────────┐
│ Estimated delivery times                     │
├──────────────────────────────────────────────┤
│ Sterling Wireless Mouse  │ Ships in 5-7 days │
│ Anker Power Bank 20k mAh │ Available Dec 15  │
└──────────────────────────────────────────────┘
```

Uses inline styles (background, colours, borders) for compatibility with Gmail, Outlook, Apple Mail, and most other email clients that strip `<style>` tags.

## How backorder detection works

A product is considered "on backorder" when ANY of these are true:

| Condition | Detected via |
|---|---|
| Stock is marked Out of Stock | `getIsInStock() === false` |
| Backorders enabled AND qty has fallen at or below `min_qty` threshold | `getBackorders() > 0 && qty <= min_qty` |
| Cart line qty exceeds available saleable qty | `orderedQty > (stockQty - minQty)` |

Combined, this covers the realistic backorder scenarios merchants face. If you want to extend or restrict this logic, the detector lives at `Model/BackorderDetector.php` — override via Magento DI preference.

## Configurable / bundle / grouped products

Container product types (configurable, bundle, grouped) aren't directly evaluated — their children are. So if a customer has a configurable product in their cart and one of its children is backordered, the cart summary will list **the configured variant** (with its specific ETA), not the parent.

## License behaviour

Identical to all eTechFlow modules — silent no-op on invalid license, automatic bypass on dev/staging hosts. See:
- `staging.*`, `dev.*`, `*.test`, `*.local`, `*.magento.cloud`, ngrok tunnels, RFC 1918 IPs

For full list of bypass patterns and domain transfer process, see the [NextDayEligibility USER_GUIDE License section](../NextDayEligibility/docs/USER_GUIDE.md) — same model across all modules.

## Troubleshooting

### "I set a backorder_eta value but the badge doesn't appear on the PDP"

Check, in order:

1. **Is the product actually on backorder?** The badge only renders when stock detection flags it. Check:
   - Stock Item's `is_in_stock` value
   - Or backorders allowed + stock ≤ min_qty
   - Or cart qty > saleable qty (cart-side only)
2. **Is the module enabled?** Admin → Stores → Configuration → eTechFlow → Backorder ETA Display → Enabled = Yes
3. **Is `Show on Product Detail Page` toggled on?**
4. **Cache flushed?** `bin/magento cache:flush`
5. **Check license:** if you're on production with an empty/wrong key, the module silently no-ops.

### "The ETA shows on the PDP but not in the cart"

Most likely: **`Show on Cart Page` toggle is off**. Check admin display section.

If it IS on:
- Confirm the product is actually a backorder ITEM in the cart (not a different product)
- The cart's `getAllVisibleItems()` excludes deleted items and container parents — check if the item is one of those

### "The order email doesn't have the ETA section"

1. **`Show in Order Confirmation Email`** toggle on?
2. **Are the order items actually backorder items at order time?** Stock can change between cart and order. The plugin re-evaluates at email send time.
3. **Custom email template?** If you've overridden the order confirmation email template in your theme, our plugin appends after the items block — make sure your custom template still uses the standard `Items.php` block.

### "The Hyvä Checkout summary doesn't appear"

Check:
1. Hyvä Checkout is the active checkout (some Hyvä stores use the standard Magento checkout)
2. Cache flushed after install
3. `bin/magento dev:source-theme:deploy` may need to run if your Hyvä build is out of date

If the summary still doesn't appear on Hyvä Checkout, the most likely cause is Tailwind purging our utility classes during the Hyvä build. Verify your Hyvä `tailwind.config.js` `content` array includes `app/code/**/view/frontend/templates/**/*.phtml` — Hyvä's default config does, but some custom configs strip it.

## Frequently asked questions

**Can I use Magento variables in the ETA text?**
Not by default — the field is treated as plain text. If you need dynamic substitution (e.g. "Ships in {{stock_qty}} days"), email support and we'll discuss a custom extension.

**Does it work with Adobe Commerce B2B shared catalogs?**
Yes. The module operates per-product, not per-customer/segment, so shared catalogs don't affect it.

**Does it work with MSI (multi-source inventory)?**
Yes. We use `StockRegistryInterface` for stock checks, which respects MSI source resolution.

**Can I customise the ETA section HTML in the order email?**
Yes — the plugin generates HTML directly in `Plugin/OrderEmailItemsPlugin.php::renderHtml()`. Override via Magento DI preference if you need to customise.

**Does the ETA appear on category / search / listing pages?**
Not by default — only on the product detail page. The `backorder_eta` attribute is available on listing pages (`used_in_product_listing = true`) but you'd need to add a template snippet to render it.

**What if I want different ETAs per locale?**
The attribute is `SCOPE_STORE` — each store view has its own value. So set the English ETA in en_US store, French in fr_FR store, etc.

**Performance impact?**
- PDP render: ~1-2ms
- Cart render: one bulk product query (~5-15ms), not N+1 (this is the 1.0.1 fix)
- Checkout render: same
- Email send: same — one bulk query

Negligible on real traffic.

**Can I uninstall cleanly?**
Yes:
```bash
bin/magento module:disable ETechFlow_BackorderEtaDisplay
composer remove etechflow/module-backorder-eta-display
bin/magento setup:upgrade
```
The `backorder_eta` attribute remains in your DB — we don't drop it on uninstall so you don't lose data accidentally.

---

## Order email — known limitations (please verify on your store)

The order-confirmation-email integration is the highest-value but most fragile display location, because email rendering varies wildly between stores, customisations, and mail clients. We unit-test the plugin's logic (10 tests covering the gates, cart-scan, and exception safety) but **we cannot test the actual rendering for you**. Verify these on your install before relying on the email functionality in customer-facing copy:

### 1. Custom email templates

The plugin hooks `Magento\Sales\Block\Order\Email\Items::toHtml()`. If your team has customised the order-confirmation email template (`vendor/magento/module-sales/view/frontend/email/order_new.html` or your theme's override) **and replaced the `<items>` block with custom rendering that bypasses `Block\Order\Email\Items`**, the plugin still runs but its output won't appear in the email.

**To verify**: open your email template (`sales_email/order/template` config setting points at it) and confirm it still references `{{block class='Magento\Sales\Block\Order\Email\Items' area='frontend' template='Magento_Sales::email/items.phtml'...}}` or equivalent. If it does, we're good. If your team has replaced this block with a raw HTML rendering loop, you'll need to extend the custom template to include the ETA block manually.

**Workaround for custom templates**: instantiate `Plugin/OrderEmailItemsPlugin::renderHtml()` directly inside your custom template via a ViewModel, or grant DI access to `EtaResolver` and render line-item ETAs inline within your custom items loop.

### 2. Cross-client email rendering

The HTML block uses inline `<table>` styling because most email clients (Gmail, Outlook desktop, Outlook web, Apple Mail, Yahoo) strip `<style>` blocks and don't apply external stylesheets. The chosen layout has been visually verified on the Magento default email template only.

**To verify**: send a test order confirmation to addresses at the email clients your customer base actually uses:

| Client | What to check |
|---|---|
| **Gmail (web)** | Block renders, colours match your configured Badge Style, no broken column widths |
| **Outlook (desktop, Windows)** | Outlook strips a lot — verify table doesn't collapse, ETA column aligns right |
| **Outlook.com / Outlook (web)** | Generally renders well, but its dark mode auto-inverts our colours — test both light/dark |
| **Apple Mail (macOS + iOS)** | High-quality renderer; should be the cleanest |
| **Yahoo Mail / Proton Mail** | Verify table doesn't break to full-width on mobile |

If any client shows a broken render, the most likely fixes are: (a) inline `width=` attribute on the outer `<table>` to force compatibility, or (b) simplify the nested-table structure to single-level. Both are one-line changes in `Plugin/OrderEmailItemsPlugin.php::renderHtml()`.

### 3. Automated test coverage caveat

The unit tests (`Test/Unit/Plugin/OrderEmailItemsPluginTest.php`) cover:

- ✅ Module-disabled and show-in-email-disabled gates short-circuit correctly
- ✅ Empty order returns unchanged
- ✅ Non-backorder items don't trigger the block
- ✅ Backorder items without an ETA don't appear in the block
- ✅ Backorder items with an ETA produce a row with the correct name + ETA text
- ✅ Mixed cart (in-stock + backorder) only includes backorder items in the appended block
- ✅ Internal exceptions are caught and logged — the email send never crashes
- ✅ Non-string upstream results are cast safely

What unit tests **don't** cover:

- ❌ Actual HTML structure validity (no DOM assertion)
- ❌ Inline CSS rendering correctness
- ❌ Behaviour against a real Magento Items block (we mock it)
- ❌ Mail-server delivery / encoding (8-bit MIME, UTF-8 in product names, etc.)

### Quick manual smoke test (10 minutes, do this before launch)

1. Open a backorder product in admin, set Backorder ETA = `"Ships 28 May 2026"`, save
2. Set its stock to OOS with Backorders = "Allow Qty Below 0"
3. As a customer, add to cart + complete checkout with a real test address
4. Check your test SMTP catcher (Mailpit / Mailhog) — open the order confirmation email
5. Scroll past the items table — you should see an inline-styled table with heading "Estimated delivery times" and a row containing the product name + ETA
6. If you see it: good. If not: check `var/log/system.log` and `var/log/exception.log` for `ETechFlow_BackorderEtaDisplay: Order email plugin failed` errors
