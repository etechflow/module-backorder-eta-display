# Changelog — Backorder ETA Display

All notable changes to this module. Adheres to [Semantic Versioning](https://semver.org/).

---

## [1.3.0] — 2026-06-02 — Stripe portal licensing + tri-state validation

Replaces the HMAC-only licence model with a Stripe Checkout subscription
flow gated by the eTechFlow licensing portal. Customers can now subscribe
directly from inside Magento Admin without ever leaving the panel — plan
selection, card entry, and SP-XXXX key activation all happen in the same
embedded frame. HMAC validation is retained as a fallback so existing
v1.2.x licences continue to work without re-activation.

### Added

- **Admin subscription gate** under *Stores → Configuration → ETECHFLOW →
  Backorder ETA Display* — when no licence is detected on a production
  environment, the configuration page is replaced with three plan cards
  (Starter $19/mo, Professional $49/mo, Enterprise $99/mo) and an
  embedded customer-details form.
- **`Controller/Adminhtml/License/Checkout.php`** — builds a Stripe
  Checkout session against the selected plan and redirects the browser
  to Stripe's hosted payment page.
- **`Controller/Adminhtml/License/Activated.php`** — Stripe success-URL
  endpoint. Calls the portal's `/license/activate` route with the
  session ID, decrypts the stored secret key, receives an SP-XXXX
  licence in the response, and writes it to `core_config_data`.
- **Three new admin fields** in *License* group: `portal_url`,
  `portal_api_url`. New *Payment* group: `stripe_secret_key` (obscured
  + encrypted backend model), `stripe_publishable_key`, `stripe_currency`.
- **Tri-state portal validation** in `Model/LicenseValidator.php` — the
  validator now returns `?bool` for portal calls: `true` (valid),
  `false` (explicit reject, HTTP 200+valid:false / 401 / 403), or
  `null` (unreachable). Only `null` falls back to the 48h local grace,
  so admin IP-removal or subscription suspension locks the storefront
  immediately rather than waiting for the grace window to expire.
- **Split cache TTLs** — valid responses cached 1h
  (`CACHE_TTL_VALID = 3600`), explicit rejections cached only 60s
  (`CACHE_TTL_REJECT = 60`) so re-authorisation propagates fast.

### Changed

- `Model/LicenseValidator.php` constructor now takes 4 arguments
  (`ScopeConfigInterface`, `StoreManagerInterface`, `CacheInterface`,
  `Curl`) — added Cache + Curl dependencies for portal validation. Any
  caller using `ObjectManager::get()` is unaffected. Direct
  instantiation in tests or DI overrides needs updating.
- `isValid()` SP-key branch now calls the portal **before** checking
  the local 48h grace. The grace is only used when the portal cannot
  be reached, never when the portal explicitly rejects.
- Removed the hyphen-suffix dev-host bypass (e.g. `*-dev.com`,
  `*-staging.com`, `*-uat.com`). It false-matched legitimate production
  hosts like `magento-dev.etechflow.com` and gave them a free unlimited
  bypass. Hosts matching `dev.`, `staging.`, `uat.` etc. as a
  subdomain prefix still bypass.
- Added `.ngrok-free.dev` to the recognised tunnel suffixes.

### Test changes

- `Test/Unit/Model/LicenseValidatorTest.php` constructor calls updated
  to 4-arg form with mocked `CacheInterface` and `Curl`.
- Removed 3 dev-host bypass entries that tested the now-removed
  hyphen-suffix behaviour. Added one entry for `.ngrok-free.dev`.

### Upgrade notes

- `bin/magento setup:upgrade` will need a generated/code wipe before
  it runs cleanly: `rm -rf generated/code/* generated/metadata/* var/cache/*`
  — the validator's constructor arity changed from 2 to 4 and stale
  metadata causes `ArgumentCountError`.
- Set your Stripe keys after upgrade: *Stores → Configuration →
  ETECHFLOW → Backorder ETA Display → Payment*. Secret key is stored
  encrypted via Magento's Encrypted backend model.
- Existing v1.2.x HMAC licences are NOT affected — `isValid()` only
  enters the portal branch for SP-prefixed keys.

---

## [1.2.3] — 2026-05-30 — Optional next-day-eligibility suppression on PDP

Resolves a real-world contradiction merchants hit when running both
this module and `etechflow/module-next-day-eligibility`: a product can
show both the green "Next Day Eligible" badge AND the amber "Ships in
5-7 business days" badge on the same PDP, confusing customers with
contradictory delivery promises. Surfaced live on Keystation.

### Added

- **New admin field** under *Stores → Configuration → eTechFlow →
  Backorder ETA Display → Display Locations*:
  **"Hide PDP Badge When Product is Next Day Eligible"** (Yes/No,
  default **No**).
  When set to **Yes** AND the product's `next_day_eligible` attribute
  equals `1`, `EtaBadge::isVisible()` returns false on the PDP — the
  Next-Day-Eligible badge wins the messaging slot, the slow ETA is
  suppressed. Only product page is affected; cart/checkout/email still
  fire on every backorder line regardless.
- **Soft-detected NDE integration** — no hard module dependency, no FQCN
  reference. The check reads the product attribute via `getData()`. If
  NDE isn't installed, the attribute doesn't exist, the check
  no-ops, BED remains fully standalone-capable. Safe to set Yes even on
  installs that may uninstall NDE later.
- **`Setup/Patch/Data/V123ReleaseMarker.php`** — continues the always-a-
  patch discipline. Depends on `V122ReleaseMarker` so patches run in
  version order.

### Changed

- `Model/Config.php`: new `XML_PATH_HIDE_IF_NEXT_DAY` constant,
  `NEXT_DAY_ELIGIBLE_ATTR` public const, `isHideIfNextDayEligible()`
  getter.
- `Block/Product/EtaBadge.php::isVisible()`: extra check between the
  backorder gate and the display-text gate. Five new LOC.
- `Console/Command/VerifyCommand.php`: version literal 1.2.2 → 1.2.3.

### Not changed

- **No schema changes, no DI changes, no API breakage.** Drop-in
  upgrade from 1.2.2.
- **Default behaviour is identical to 1.2.2** — the new toggle
  defaults to No. Existing installs see no change unless they opt in.

### Migration

```bash
composer require etechflow/module-backorder-eta-display:^1.2.3
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

To opt in: *Stores → Configuration → eTechFlow → Backorder ETA Display →
Display Locations* → **Hide PDP Badge When Product is Next Day Eligible**
→ **Yes** → Save → flush cache.

### When you should turn it on

You should turn it on if all three are true:
1. You have `etechflow/module-next-day-eligibility` installed
2. Some of your products are flagged Next-Day-Eligible AND would also be
   on backorder (e.g. drop-ship products where NDE auto-enables backorders)
3. You'd rather customers see "Next Day Eligible" alone than both
   badges fighting each other

If you don't have NDE installed, leave it at No — the toggle does
nothing in that case anyway.

---

## [1.2.2] — 2026-05-25 — Always-a-patch discipline + clarify rename patch docblock

Two additive changes, no behaviour change, no schema/API change. Drop-in
upgrade from 1.2.1.

### Added

- **`Setup/Patch/Data/V122ReleaseMarker.php`** — no-op release marker.
  Establishes the discipline that every BED release ships at least one
  data patch, even if it has nothing to do. This guarantees
  `setup:upgrade` always has SOMETHING to register in `patch_list`,
  surfacing FS / permissions / DI errors during the patch phase (which
  retries cleanly) instead of at the end of the upgrade (which doesn't).

  Same pattern shipped in NDE v1.7.1 after the v1.7.0 Keystation deploy
  incident — a version bump with zero patches risks the same site-down
  condition where `setup:upgrade` aborts post-patch, `data_version`
  never advances, and DbStatusValidator returns 500 on every request
  until rollback.

  Future releases copy this template (`V123ReleaseMarker`, etc).

### Changed

- **`RenameBackorderEtaToRestockDate` docblock** rewritten to make
  clear that this patch ONLY relabels the attribute (the
  `frontend_label` and `note`), NOT the attribute_code. The class name
  is misleading — `attribute_code = 'backorder_eta'` is deliberately
  permanent because renaming it would orphan every saved value and
  break custom themes / integrations / SQL queries. Same reason
  Magento core kept the `manufacturer` code unchanged when its label
  was relabelled to "Brand".

  Class name kept as-is (renaming would either re-fire the patch on
  installs that already ran it or leave dangling `patch_list` rows).
  Docblock now warns future readers.

### Files added

```
Setup/Patch/Data/V122ReleaseMarker.php
```

### Files modified

```
Setup/Patch/Data/RenameBackorderEtaToRestockDate.php   (docblock only)
etc/module.xml                                          (1.2.1 → 1.2.2)
composer.json                                           (1.2.1 → 1.2.2)
```

### Upgrade

```bash
composer require etechflow/module-backorder-eta-display:^1.2.2
bin/magento setup:upgrade
# Watch for warnings — if any appear, do NOT cache:flush yet.
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Pre-flight check after upgrade:
```sql
SELECT module, schema_version, data_version FROM setup_module
WHERE module='ETechFlow_BackorderEtaDisplay';
```
Both columns should read `1.2.2`. If `data_version` is stale, re-run
`setup:upgrade` before flushing cache.

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
