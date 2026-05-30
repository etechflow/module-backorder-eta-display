<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED              = 'etechflow_backorderetadisplay/general/enabled';
    private const XML_PATH_DEFAULT_ETA          = 'etechflow_backorderetadisplay/general/default_eta';
    private const XML_PATH_LABEL_PREFIX         = 'etechflow_backorderetadisplay/general/label_prefix';
    private const XML_PATH_SHOW_ON_PRODUCT_PAGE = 'etechflow_backorderetadisplay/display/show_on_product_page';
    private const XML_PATH_SHOW_ON_CART         = 'etechflow_backorderetadisplay/display/show_on_cart';
    private const XML_PATH_SHOW_ON_CHECKOUT     = 'etechflow_backorderetadisplay/display/show_on_checkout';
    private const XML_PATH_SHOW_IN_ORDER_EMAIL  = 'etechflow_backorderetadisplay/display/show_in_order_email';
    private const XML_PATH_BADGE_STYLE          = 'etechflow_backorderetadisplay/display/badge_style';
    private const XML_PATH_HIDE_IF_NEXT_DAY     = 'etechflow_backorderetadisplay/display/hide_if_next_day_eligible';

    /** Product attribute the next-day-suppression rule reads. */
    public const NEXT_DAY_ELIGIBLE_ATTR = 'next_day_eligible';

    /**
     * Constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param LicenseValidator     $licenseValidator
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LicenseValidator $licenseValidator
    ) {
    }

    /**
     * Whether the module is active for the current store.
     *
     * Returns false on unlicensed installs so the module silently no-ops.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        if (!$this->licenseValidator->isValid()) {
            return false;
        }

        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Default ETA text used when the per-product attribute is empty.
     *
     * @return string
     */
    public function getDefaultEta(): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_ETA,
            ScopeInterface::SCOPE_STORE
        ));
    }

    /**
     * Prefix shown before the ETA text (e.g. "Ships in").
     *
     * @return string
     */
    public function getLabelPrefix(): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_LABEL_PREFIX,
            ScopeInterface::SCOPE_STORE
        ));
    }

    /**
     * @return bool
     */
    public function isShowOnProductPage(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_ON_PRODUCT_PAGE,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isShowOnCart(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_ON_CART,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isShowOnCheckout(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_ON_CHECKOUT,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return bool
     */
    public function isShowInOrderEmail(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SHOW_IN_ORDER_EMAIL,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Whether the PDP badge should be suppressed when the product is also
     * flagged Next Day Eligible by ETechFlow_NextDayEligibility.
     *
     * Resolves the "Next Day Eligible + Ships in 5-7 business days" contradiction
     * that hits any merchant running both modules. Default is FALSE (no change to
     * existing installs). Merchants opt in via admin.
     *
     * Soft-detected: this getter just reads the config flag. The next-day check
     * at the badge level reads the `next_day_eligible` attribute via
     * Product::getData() — if NDE isn't installed the attribute doesn't exist,
     * getData() returns null, the rule is a no-op, and BED remains fully
     * standalone-capable.
     */
    public function isHideIfNextDayEligible(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_HIDE_IF_NEXT_DAY,
            ScopeInterface::SCOPE_STORE
        );
    }

    /** Allowed badge style modifiers. Anything else is clamped to 'warning'. */
    private const BADGE_STYLES = ['warning', 'info', 'neutral'];

    /**
     * Badge style: warning, info, or neutral.
     *
     * Clamped to the known whitelist so a malformed admin value cannot render
     * as a raw CSS class (defence-in-depth — the admin source model already
     * restricts the dropdown).
     *
     * @return string
     */
    public function getBadgeStyle(): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_BADGE_STYLE,
            ScopeInterface::SCOPE_STORE
        );

        return in_array($value, self::BADGE_STYLES, true) ? $value : 'warning';
    }
}
