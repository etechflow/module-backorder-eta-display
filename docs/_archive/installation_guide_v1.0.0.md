---
title: "Backorder ETA Display for Magento 2"
subtitle: "Installation Guide — Version 1.0.0"
author: "eTechFlow Pvt. Ltd."
date: "2026"
---

# Backorder ETA Display for Magento 2

**Module:** ETechFlow_BackorderEtaDisplay
**Version:** 1.0.0
**Compatibility:** Magento Open Source & Adobe Commerce 2.4.4 – 2.4.8
**PHP:** 8.1, 8.2, 8.3
**License:** Proprietary
**Support:** info@etechflow.com

---

> **[INSERT IMAGE: 00-cover-logo.png]**
> *Designer note: place the eTechFlow logo on the cover page, centered, with brand purple background. Source file: `_shared_assets/logo.png`*

---

## Hyvä Theme Compatibility

✅ **Fully compatible with both Hyvä Theme and Hyvä Checkout.**

- Under Luma or Hyvä Theme with standard Magento Checkout, the badge renders via self-contained phtml + scoped CSS.
- Under Hyvä Checkout (Magewire-based), an automatically-activated Tailwind + Alpine.js variant takes over for the checkout summary.

| Component | Luma | Hyvä Theme + standard | Hyvä Theme + Hyvä Checkout |
|---|---|---|---|
| Product page badge | ✅ | ✅ | ✅ |
| Cart page summary | ✅ | ✅ | ✅ |
| Checkout step summary | ✅ | ✅ | ✅ Tailwind + Alpine |
| Order confirmation email | ✅ | ✅ | ✅ |

---

## Table of Contents

1. About this Extension
2. System Requirements
3. Pre-Installation Checklist
4. Installation — Method A: Manual Upload
5. Installation — Method B: Composer
6. Where to Find the Configuration in the Magento Admin
7. Configuration Settings — Field by Field
8. Setting a Per-Product ETA
9. How the ETA Shows to Customers
10. Order Confirmation Email
11. Using This Extension With Other eTechFlow Modules
12. Uninstallation
13. Troubleshooting
14. Support & Contact

---

## 1. About this Extension

**Backorder ETA Display** shows expected restock or shipping ETAs for backorder items wherever a customer encounters the product — on the product detail page, on the shopping cart, at checkout, and in the order confirmation email.

Vague messaging like "Out of Stock" loses sales. Specific messaging like *"Ships in 5–7 business days"* converts those sales and reduces support tickets from customers asking "when will my order ship?"

### Key Features

- Per-product `backorder_eta` attribute — write the exact wording shown to customers
- Module-wide default ETA fallback for products without a specific value
- Three badge styles: Warning (amber), Info (blue), Neutral (grey)
- Configurable label prefix (e.g. "Ships in", "Available in")
- Four display locations, each toggle-able: product page, cart, checkout, email
- Hyvä Checkout (Magewire) integration with Tailwind + Alpine.js
- Backorder detection mirrors Magento's three-case rule (out of stock, depleted with backorders, partial stock)
- Per-store-view configuration

---

## 2. System Requirements

| Component | Supported Version |
|---|---|
| Magento Open Source / Adobe Commerce | 2.4.4, 2.4.5, 2.4.6, 2.4.7, 2.4.8 |
| PHP | 8.1, 8.2, 8.3 |
| Composer | 2.x (only required for Method B) |
| MySQL / MariaDB | As required by your Magento version |

---

## 3. Pre-Installation Checklist

Complete these steps before extracting any files.

**Step 1 — Back up your Magento installation.** Take a full backup of `app/code`, `app/etc`, and your database.

**Step 2 — Set Magento to developer or default mode.**
```
php bin/magento deploy:mode:show
php bin/magento deploy:mode:set developer
```

**Step 3 — Disable cache before installing.**
```
php bin/magento cache:disable
```

**Step 4 — Verify maintenance access.** You need SSH or terminal access to the server.

> **Note:** Schedule the install during a low-traffic window or enable maintenance mode (`php bin/magento maintenance:enable`).

---

## 4. Installation — Method A: Manual Upload

**Step 1 — Unpack the archive.** Extract `etechflow-module-backorder-eta-display-1.0.0.zip` on your local machine.

**Step 2 — Create the module directory.**
```
mkdir -p app/code/ETechFlow
```

**Step 3 — Upload.** Upload the `BackorderEtaDisplay` folder into `app/code/ETechFlow/`.

**Step 4 — Enable the module.**
```
php bin/magento module:enable ETechFlow_BackorderEtaDisplay
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

> **Done. Skip ahead to section 6.**

---

## 5. Installation — Method B: Composer

**Step 1 — Add the repository (skip if already configured).**
```
composer config repositories.etechflow composer https://repo.etechflow.com
```

**Step 2 — Require the package.**
```
composer require etechflow/module-backorder-eta-display:^1.0
```

**Step 3 — Run the same enable / upgrade commands.**
```
php bin/magento module:enable ETechFlow_BackorderEtaDisplay
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

---

## 6. Where to Find the Configuration in the Magento Admin

### Step 1 — Open the Stores menu

In the left sidebar of the admin, click the **Stores** icon.

> **[INSERT IMAGE: 01-stores-menu.png]**
> *Caption: The Stores menu opens a flyout panel showing Settings, Inventory, and other groups.*

### Step 2 — Click "Configuration"

Inside the Stores flyout, under **Settings**, click **Configuration**.

> **[INSERT IMAGE: 02-configuration-link.png]**
> *Caption: Configuration link highlighted in the Stores menu.*

### Step 3 — Find the eTechFlow group

Scroll the left-side accordion until you see **ETECHFLOW** and click it to expand.

> **[INSERT IMAGE: 03-config-sidebar-etechflow.png]**
> *Caption: ETECHFLOW expanded with the three module entries.*

### Step 4 — Click "Backorder ETA Display"

> **[INSERT IMAGE: 04-etechflow-expanded-eta.png]**
> *Caption: Backorder ETA Display highlighted in the left sidebar.*

### Step 5 — You are now on the configuration page

The right pane shows three collapsible groups: **License**, **General Settings**, **Display Locations**.

> **[INSERT IMAGE: 05-eta-config-full.png]**
> *Caption: Full configuration page with all three groups expanded.*

---

## 7. Configuration Settings — Field by Field

### 7.1 License

| Field | Description |
|---|---|
| **License Key** | Paste the license key delivered with your purchase. The key is bound to your Magento base URL host. Development hosts (`.test`, `.local`, `localhost`) bypass licensing automatically. |

### 7.2 General Settings

| Field | Description | Default |
|---|---|---|
| **Enable Module** | Master on/off switch. | Yes |
| **Default ETA Text** | Shown when a product has no per-product ETA. | `5-7 business days` |
| **Label Prefix (Default ETA Only)** | Prepended to the Default ETA. Per-product values are shown verbatim. | `Ships in` |

### 7.3 Display Locations

| Field | Description | Default |
|---|---|---|
| **Show on Product Page** | Render the ETA badge below the price on the product detail page. | Yes |
| **Show on Cart Page** | Show backorder ETA summary above the shopping cart items. | Yes |
| **Show on Checkout** | Show the same summary above the checkout shipping form. | Yes |
| **Include in Order Confirmation Email** | Append an ETA panel under the order items in the confirmation email. | Yes |
| **Badge Style** | Visual style: Warning (amber), Info (blue), Neutral (grey). | Warning |

> **[INSERT IMAGE: 06-eta-display-locations.png]**
> *Caption: The Display Locations section with all four toggles and the badge style selector.*

---

## 8. Setting a Per-Product ETA

For products that have a specific ETA different from the default, open the product in **Catalog → Products** and find the **Backorder ETA** field under the **eTechFlow Shipping** attribute group.

Enter the **complete message** you want customers to see:

- `Ships December 15`
- `Available in 2 weeks`
- `Pre-order — ships January 5`

Per-product values are shown to the customer **verbatim, with no label prefix added**, so you control the exact wording.

If left empty, the module falls back to the **Default ETA Text** + **Label Prefix** from Stores → Configuration.

> **[INSERT IMAGE: 07-product-edit-backorder-eta.png]**
> *Caption: The Backorder ETA field on the product edit page, under the eTechFlow Shipping group.*

| Attribute value | Default ETA | Label Prefix | What customer sees |
|---|---|---|---|
| `(empty)` | `5-7 business days` | `Ships in` | Ships in 5-7 business days |
| `(empty)` | `2 weeks` | `Available in` | Available in 2 weeks |
| `Ships December 15` | (any) | (any) | Ships December 15 |

---

## 9. How the ETA Shows to Customers

### 9.1 Product detail page

When a customer visits a backorder product, the ETA badge renders directly below the price.

> **[INSERT IMAGE: 08-storefront-product-page-badge.png]**
> *Caption: Product detail page with the amber "Ships in 5-7 business days" badge below the price.*

### 9.2 Shopping cart

When a customer reaches the cart with at least one backorder item, a summary panel appears above the cart items listing each backorder item with its ETA.

> **[INSERT IMAGE: 09-storefront-cart-summary.png]**
> *Caption: Shopping cart with the amber "Shipping notes for backorder items" panel above the line items.*

### 9.3 Checkout step

The same summary appears above the checkout address form, so the ETA is visible right before the customer pays.

> **[INSERT IMAGE: 10-storefront-checkout-summary.png]**
> *Caption: Checkout shipping step with the ETA summary panel above the address form.*

---

## 10. Order Confirmation Email

When **Include in Order Confirmation Email** is enabled, the module appends a backorder ETA panel underneath the order items table in the email.

The ETA shown matches whatever was configured at order time (per-product attribute or default ETA + prefix).

> **[INSERT IMAGE: 11-order-email-eta.png]**
> *Caption: Order confirmation email with the amber ETA panel below the order items table.*

---

## 11. Using This Extension With Other eTechFlow Modules

This module pairs naturally with the eTechFlow backorder-management suite:

### With ETechFlow_BackorderShippingRestrictor

If both modules are installed, they independently detect the same backorder state — no duplication. Each maintains its own purpose:

- **Backorder ETA Display** tells customers *when* their order will ship.
- **Backorder Shipping Restrictor** removes express shipping methods for backorder carts.

We recommend installing both for the complete backorder UX. The module's `composer.json` includes a `suggest` entry for this pairing.

### With ETechFlow_NextDayEligibility

When all three modules are installed, the customer experience becomes:

1. Product page shows **Standard Delivery Only** badge (NextDayEligibility) + **Ships in 5-7 days** badge (this module)
2. Cart page shows the ETA summary
3. Checkout step shows the ETA summary, removes express methods (Restrictor), and shows the unavailability notice
4. Order email confirms the ETA per backorder item

All three modules work standalone — they enhance each other when combined but don't require each other.

---

## 12. Uninstallation

### Remove the product attribute (optional)

```
php bin/magento eav:attribute:remove backorder_eta
```

### Remove the module

```
php bin/magento module:disable ETechFlow_BackorderEtaDisplay
composer remove etechflow/module-backorder-eta-display   # Composer install only
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

If installed manually, also delete the directory:
```
rm -rf app/code/ETechFlow/BackorderEtaDisplay
```

---

## 13. Troubleshooting

### ETA badge does not appear on product page
- Hard-refresh the page (Cmd+Shift+R or Ctrl+Shift+R) — your browser may have cached the old page
- Run `php bin/magento cache:flush`
- Verify the module is enabled and **Show on Product Page = Yes**
- Confirm the product is actually on backorder (out of stock with backorders allowed, or qty <= min_qty)

### Cart summary does not appear
- Verify **Show on Cart Page = Yes**
- Confirm at least one cart item is on backorder
- Check `var/log/exception.log` for module-related errors

### Checkout summary does not appear
- Verify **Show on Checkout = Yes**
- Hard-refresh the checkout page
- Some custom checkout themes may not include the standard `content` container; in that case, the summary appears at the top of the page rather than above the address form

### Order email does not include the ETA
- Verify **Include in Order Confirmation Email = Yes**
- Place a test order and check the email after a few seconds — Magento queues outbound emails
- For local testing, use Mailpit/Mailhog or the email log to inspect the rendered HTML

### Per-product ETA is shown twice with the prefix
- Per-product values are shown **verbatim**. If your value is `Ships December 15`, the customer sees exactly that — no "Ships in" is prepended. If you want the prefix, leave the per-product field empty and rely on the Default ETA + Label Prefix combination.

---

## 14. Support & Contact

| | |
|---|---|
| **Email** | info@etechflow.com |
| **Website** | https://etechflow.com |
| **Module** | ETechFlow_BackorderEtaDisplay 1.0.0 |

### When reporting an issue, please include:

- Magento version and edition (Open Source / Commerce)
- PHP version (`php -v`)
- Module version (from `composer.json` or `etc/module.xml`)
- The relevant excerpt from `var/log/exception.log` or `var/log/system.log`
- Steps to reproduce the issue

---

> © 2026 eTechFlow Pvt. Ltd. — All rights reserved. This document and the associated extension are licensed under the eTechFlow Proprietary License.
