# Backorder ETA Display for Magento 2

**Show customers when their backordered items will actually ship.**

Turns "this item is on backorder" from a customer-service nightmare into a clear, dated promise. Renders on product page, cart, checkout, and order confirmation email.

---

## What's new

The full version history lives in `CHANGELOG.md`. Highlights:

- **v1.1.0** — Module Status banner at the top of admin (shows whether the module is actually active), inline tooltips on every field, `backorder_eta` attribute now visible + filterable in the product grid, expanded i18n CSV, stale install guides removed.
- **v1.0.2** — Production Environment toggle for non-standard dev domains.

---

## What it does

Magento's default backorder message tells customers nothing — just "available for backorder", no date. Customers either don't notice (then complain when packages are late) or they cancel rather than wait an unknown amount of time. This module:

- Adds a per-product `backorder_eta` text attribute ("Ships in 5-7 business days", "Available Dec 15", "Pre-order — January 5")
- Falls back to a configurable store-wide default ETA if a product has no specific value
- Renders the ETA in four places: product detail page badge, cart summary, checkout summary, order confirmation email
- Three colour styles to match your store branding: warning (amber), info (blue), neutral (grey)
- Fully Hyvä compatible with dark-mode support

## Requirements

| | |
|---|---|
| **Magento** | Open Source 2.4.4+ OR Adobe Commerce 2.4.4+ |
| **PHP** | 8.1, 8.2, 8.3, or 8.4 |
| **Compatible themes** | Luma (default) + Hyvä + Hyvä Checkout |

## Installation

### Option A — Composer (recommended)

```bash
composer require etechflow/module-backorder-eta-display:^1.0
bin/magento module:enable ETechFlow_BackorderEtaDisplay
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Option B — Manual (from zip)

1. Unzip `etechflow-module-backorder-eta-display-1.0.1.zip` into:
   ```
   <magento-root>/app/code/ETechFlow/BackorderEtaDisplay/
   ```
   **Directory MUST be `ETechFlow` (capital E, T, F) — case-sensitive on Linux.**

2. Enable and set up:
   ```bash
   bin/magento module:enable ETechFlow_BackorderEtaDisplay
   bin/magento setup:upgrade
   bin/magento setup:di:compile
   bin/magento cache:flush
   ```

## After install — quick start

### 1. License key

Admin → **Stores → Configuration → eTechFlow → Backorder ETA Display → License**

Paste the key from your purchase email. Skip this on dev/staging — see USER_GUIDE for the auto-bypass patterns.

### 2. Enable + set defaults

Same admin page:

- **General → Enabled = Yes**
- **General → Default ETA** = "Ships in 5-7 business days" (or whatever your default backorder window is)
- **General → ETA Label Prefix** = "Estimated delivery: " (or leave blank if you prefer the raw ETA)
- **Display → Badge Style** = warning / info / neutral (pick the colour that matches your branding)
- **Display → Show on PDP / Cart / Checkout / Order Email** = all Yes (or pick the ones you want)

Save Config.

### 3. (Optional) Customise per product

Edit any product → eTechFlow Shipping → **Backorder ETA** field. Type whatever ETA you want for that specific product (e.g. "Available December 15" for a pre-order). Save.

Products with no custom ETA fall back to your store-wide default.

## What customers will see

| Where | Renders as |
|---|---|
| Product detail page (PDP) | Badge below price with truck icon + ETA text |
| Cart page | Summary block at top listing each backorder item + its ETA |
| Checkout shipping step | Same summary block |
| Order confirmation email | Styled HTML table appended below items list |

The module only renders when a product is genuinely on backorder AND has a non-empty ETA. In-stock products show nothing.

## Documentation

| File | Read when |
|---|---|
| `README.md` (this file) | First — overview + install |
| `docs/installation_guide.html` | Detailed install walkthrough with screenshots |
| `docs/USER_GUIDE.md` | Configuration + usage + troubleshooting |
| `CHANGELOG.md` | What changed in each version |
| `LICENSE.txt` | Licence terms |

## Support

- **Email:** support@etechflow.com — typically responds within one business day
- **Website:** https://etechflow.com

## License

Proprietary — see `LICENSE.txt`. Licensed per Magento installation. Unlimited dev/staging environments included.
