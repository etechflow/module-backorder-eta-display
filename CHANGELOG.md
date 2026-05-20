# Changelog — Backorder ETA Display

All notable changes to this module. Adheres to [Semantic Versioning](https://semver.org/).

---

## [1.2.1] — 2026-05-18 — Friendly-language pass

Customer-facing copy now leads with shopper-friendly phrasing instead of trade jargon. "Backorder ETA" was the right phrase for the original spec but "restock date" / "available date" / "temporarily sold out" lands better with actual shoppers. Drop-in upgrade — no behaviour change, just labels and copy.

### Changed

- **Product attribute label**: `Backorder ETA` → **`Restock Date`** (the field merchants fill in on every product edit page). Renamed via a new idempotent `RenameBackorderEtaToRestockDate` data patch — existing installs automatically pick up the new label on next `setup:upgrade`. The DB column `backorder_eta` is unchanged (renaming would break every install's data).
- **Admin config labels** in `Stores → Configuration → eTechFlow → Backorder ETA Display`:
  - `Default ETA Text` → `Default Restock Date`
  - `Label Prefix (Default ETA Only)` → `Label Prefix (default text only)`
  - Every admin help-note rewritten to say *"sold-out / backorder products"* instead of just *"backorder products"*
- **Marketing README** (`marketing/BackorderEtaDisplay/README.md`) rewritten end-to-end:
  - Tagline now leads with *"Turn 'out of stock' into 'ships in 2 weeks'"*
  - Top of file states the module's customer-facing positioning is "Restock Date Display" even though the internal name stays as-is
  - Adobe Marketplace listing title proposal updated to *"Restock Date Display (for backorder / pre-order items)"*
  - `backorder` kept in tags + secondary mentions for SEO discoverability (merchants do search for that term)
- **FAQ snippet** updated so "What if the product isn't on backorder?" becomes "What if the product is in stock?"

### Unchanged (deliberately)

- **Storefront templates** — the customer-visible badge and summary blocks already used shopper-friendly phrasing (the heading reads *"Estimated delivery times"*, the badge text is the merchant-typed restock date directly). No template changes.
- **Internal class names, file paths, namespaces, composer package name** (`etechflow/module-backorder-eta-display`) — renaming would break backward-compat for everyone who installed via composer.
- **The EAV attribute code** (`backorder_eta`) — DB column name; renaming breaks every install's stored data.

### Schema

One new idempotent data patch: `Setup/Patch/Data/RenameBackorderEtaToRestockDate.php`. Updates `eav_attribute.frontend_label` and `note` for the `backorder_eta` attribute. Idempotent — re-running `setup:upgrade` is a no-op. Dependency declared on `AddBackorderEtaAttribute` so the rename only fires after the original attribute exists.

---

## [1.2.0] — 2026-05-18 — STR-parity quality pass

After v1.1.0 reached UX parity with NDE, the v1.2.0 work brings BED's overall quality bar to match STR's recent v1.1.0 release: a verify CLI, deeper test coverage, 8-locale i18n stubs, a troubleshooting doc, performance numbers, and an honest pricing pivot. The module's runtime behaviour is unchanged from v1.1.0 — every change in this release is in the surrounding infrastructure that makes BED easier to install, debug, translate, and trust.

### Added

- **`bin/magento etechflow:bed:verify`** — headless 12-step end-to-end verifier matching the pattern from STR and NDE. Exercises Config + EtaResolver (per-product / default-fallback / empty / display-prefix) + BackorderDetector (in-stock / out-of-stock / backorder-allowed-depleted / container types skipped) against the LIVE DB. Self-contained: seeds a test product, runs checks, cleans up via raw DB delete (skips ProductRepository to avoid url_rewrite orphans).
- **`docs/bed-troubleshooting.md`** — pre-emptive support: 10+ common BED tickets with diagnostic ladders ("ETA doesn't show on PDP" / "ETA shows on in-stock product" / "Default ETA shows when I set a per-product ETA" / "Hyvä Checkout doesn't show the ETA but Luma does" / "License inactive" / etc.).
- **`docs/performance.md` — Module: BED section.** Documents per-method latencies: `EtaResolver::resolve` at 1.4 µs/call, `BackorderDetector::isBackordered` at 8.9 µs/call. A 50-product catalog page renders all its BED badges in ~0.5 ms of CPU — <2% of a typical Magento page-render budget.
- **8 locale starter CSVs** — de_DE / fr_FR / es_ES / it_IT / nl_NL / pt_BR / ja_JP / zh_Hans_CN. ~20 high-confidence translations per locale covering admin labels, badge style names, and the most common storefront strings (Ships in / Backorder ETA / Dismiss / etc.). Each file opens with a `_DRAFT_NOTICE_<LOCALE>` sentinel in the target language documenting draft status. Help-note long-form text remains in English (Magento falls back to source string) — same strategy as STR's i18n stubs.
- **4 new unit-test classes — `BackorderDetectorTest`, `ConfigTest`, `Model/Source/BadgeStyleTest`, `ViewModel/HyvaCheckoutEtaTest`** — closes the coverage gap for the engine + Config + ViewModel that the existing `EtaResolverTest` + `LicenseValidatorTest` + `OrderEmailItemsPluginTest` didn't cover. Test count went from 63 to 106 (+43 tests, +77 assertions).

### Changed

- **Pricing pivot $295 → $99/yr (single-SKU model).** BED has a much smaller feature surface than NDE / STR (4 user-visible features, 1 EAV attribute, vs STR's full rate-engine surface). The earlier $295 was anchored to NDE's tier; honest feature-for-feature comparison puts BED at roughly 1/3 of STR's $179. Renewal $49/yr (50%). 3-module bundle $379/yr. See `marketing/BackorderEtaDisplay/README.md` for full pricing rationale.
- **CHANGELOG entry replaced** with the proper `[1.2.0]` release block (was sitting in `[Unreleased]`).

### Test count + coverage

- Unit tests: 63 → 106 (+43)
- Assertions: 80 → 157 (+77)
- `etechflow:bed:verify` 12/12 steps pass end-to-end against the live DB
- PHPStan level 4 workspace-clean

### Schema

No schema changes. The `backorder_eta` EAV attribute + the
`AddBackorderEtaAttribute` / `EnableBackorderEtaGridColumn` setup
patches from v1.1.0 are unchanged.

### Compatibility

Unchanged from v1.1.0: Magento Open Source 2.4.4+ / Adobe Commerce
2.4.4+ / PHP 8.1-8.4 / Hyvä Theme + Hyvä Checkout native.

---

---

## [1.1.0] — 2026-05-15

### Added — UX overhaul (parity with NextDayEligibility v1.3.0)

- **Module Status banner** at the top of the admin config section. Six states with plain-language explanations and what-to-do guidance:
  - ✅ Module is active (green) — licence valid + module enabled
  - ⚪ Licence valid, module is disabled (grey) — Enable Module toggled off
  - ⚠️ Licence key missing (amber) — production host, no key entered
  - ⚠️ Licence key invalid for this host (amber) — key entered but wrong for the current domain
  - ℹ️ Dev host bypass active (blue) — current host matches a dev pattern (`*.test`, `localhost`, `staging.*`, etc.)
  - ℹ️ Production Environment = No (blue) — toggle off, licence not enforced
  Removes the "I installed it but nothing's happening" mystery for first-time installers.
- **Inline tooltips on every admin field** — every comment now explicitly explains what Yes vs No does, what each badge style option means, and what each display-location toggle controls. Merchants no longer need to read external docs to configure correctly.
- **`backorder_eta` attribute now visible AND filterable in the product grid.** Merchants can scan the catalog at a glance for which products have ETAs set vs missing. New `EnableBackorderEtaGridColumn` data patch upgrades existing installs; fresh installs get the flags via `AddBackorderEtaAttribute`.

### Changed

- `LicenseValidator::isDevHost()` exposed as public so the new Module Status block can show bypass status.
- Admin field labels and `<comment>` text expanded across the entire `system.xml` — for example *"Show on Product Page"* now explains exactly which theme(s) it affects and what happens to the other display locations when toggled.
- `composer.json` `suggest` updated: dropped the reference to the deprecated `ETechFlow_BackorderShippingRestrictor` module; the NDE entry now describes the "complete loop" positioning of the 2-module bundle.

### Removed (from the dist zip)

- `docs/Installation_Guide.docx`, `docs/installation_guide.html`, `docs/installation_guide.md` — three v1.0.0-era install guides that hadn't been updated for v1.0.x changes. Moved to `docs/_archive/` (excluded from the shipped zip). Current install instructions live in `README.md` + `docs/USER_GUIDE.md` only — single source of truth, easier to keep current.

### i18n

- `i18n/en_US.csv` expanded from 4 → 26 strings, covering all admin labels, field labels, badge style options, and storefront strings introduced through v1.0.x.

---

## [1.0.2] — 2026-05-15

### Added
- **"Production Environment" toggle** in admin (Stores → Configuration → eTechFlow → Backorder ETA Display → License). Set to "No" on any dev/staging install to run the module at full features without a licence key. Default: Yes.
- 4 new unit tests covering toggle behaviour.

### Changed
- Admin License section now has two fields: Production Environment (new) + License Key (existing).

---

## [1.0.1] — 2026-05-15

### Fixed
- **N+1 query elimination on cart, checkout, and order email** — products are now bulk-loaded in a single collection query instead of one repository call per cart line. Significant TTFB improvement on carts with 10+ items.
- **Order email plugin return type** — defensive cast to string ensures we never return a non-string value upstream, eliminating a potential TypeError on emails sent through third-party customisations.
- **Badge style whitelist clamp** — `getBadgeStyle()` now validates against `['warning', 'info', 'neutral']` and falls back to `warning` for any unrecognised value. Prevents a malformed admin value from rendering as a raw CSS class.

### Changed
- **Hyvä template upgrades:**
  - Added inline SVG icons (clock, truck) instead of coloured dot indicators
  - Added dark-mode variants (`dark:bg-amber-900/20`, `dark:text-amber-200`, etc.) across all templates
  - Added Alpine.js fade-in transitions that respect `prefers-reduced-motion`
  - Added `aria-live="polite"` on cart/checkout summaries for screen readers
  - Added `truncate` + `whitespace-nowrap` so long product names don't break layout
- **HyvaCheckoutEta ViewModel** now returns text-colour utility classes (e.g. `text-amber-600`) for SVG `currentColor` inheritance, with corresponding dark-mode variants

### Added
- **`www.` prefix normalization** in license validation
- **Expanded dev-host pattern detection** for auto-bypass
- **Bundle license key support**
- **Hyvä-first templates** alongside Luma — Magento auto-uses the correct variant based on the active theme
- **Tailwind safelist comments** in templates that consume ViewModel-returned classes (protects against Hyvä build purge)

### Composer
- Magento framework version range extended to `^103.0||^104.0`

---

## [1.0.0] — 2026-05-11

Initial release.

- `backorder_eta` product attribute (per-store, text)
- Backorder detection (out-of-stock, partial-backorder, qty-depleted with backorders enabled)
- ETA resolver (per-product → store default fallback, with optional label prefix)
- Product detail page badge (Luma)
- Cart summary block (Luma)
- Hyvä Checkout integration via ViewModel
- Order confirmation email HTML section (inline styles for email-client compatibility)
- Admin config: enable, default ETA, label prefix, badge style, per-location display toggles
- HMAC-based per-domain license key system
