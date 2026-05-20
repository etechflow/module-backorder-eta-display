<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Test\Unit\Model;

use ETechFlow\BackorderEtaDisplay\Model\Config;
use ETechFlow\BackorderEtaDisplay\Model\LicenseValidator;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Config wrapper: license-gated isEnabled, the three display-
 * location toggles, default ETA + label prefix + badge style whitelist.
 *
 * The badge-style whitelist is the only "real" logic here — it clamps
 * any unknown value to "warning" so a corrupted DB row or a config-import
 * mishap can't render arbitrary CSS class names into the rendered HTML
 * (defence-in-depth against admin-side XSS).
 */
class ConfigTest extends TestCase
{
    private ScopeConfigInterface|MockObject $scopeConfig;
    private LicenseValidator|MockObject $licenseValidator;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->licenseValidator = $this->createMock(LicenseValidator::class);
        $this->config = new Config($this->scopeConfig, $this->licenseValidator);
    }

    /**
     * Stub the scope config to return $value for $path; all other paths
     * return null / false.
     */
    private function stubScopeValue(string $path, $value): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $p, string $scope) => $p === $path ? $value : null
        );
    }

    private function stubScopeFlag(string $path, bool $value): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $p, string $scope) => $p === $path ? $value : false
        );
    }

    // -----------------------------------------------------------------
    // isEnabled — license-gated
    // -----------------------------------------------------------------

    public function testIsEnabledReturnsFalseWhenLicenseInvalid(): void
    {
        // License invalid: short-circuits before reading scope config.
        // No flag check should happen — the module silently no-ops.
        $this->licenseValidator->method('isValid')->willReturn(false);
        $this->scopeConfig->expects($this->never())->method('isSetFlag');
        $this->assertFalse($this->config->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenLicenseValidButFlagOff(): void
    {
        $this->licenseValidator->method('isValid')->willReturn(true);
        $this->stubScopeFlag('etechflow_backorderetadisplay/general/enabled', false);
        $this->assertFalse($this->config->isEnabled());
    }

    public function testIsEnabledReturnsTrueWhenLicenseValidAndFlagOn(): void
    {
        $this->licenseValidator->method('isValid')->willReturn(true);
        $this->stubScopeFlag('etechflow_backorderetadisplay/general/enabled', true);
        $this->assertTrue($this->config->isEnabled());
    }

    // -----------------------------------------------------------------
    // Display-location toggles (4 independent flags)
    // -----------------------------------------------------------------

    public function testIsShowOnProductPageReadsCorrectPath(): void
    {
        $this->stubScopeFlag('etechflow_backorderetadisplay/display/show_on_product_page', true);
        $this->assertTrue($this->config->isShowOnProductPage());
    }

    public function testIsShowOnCartReadsCorrectPath(): void
    {
        $this->stubScopeFlag('etechflow_backorderetadisplay/display/show_on_cart', true);
        $this->assertTrue($this->config->isShowOnCart());
    }

    public function testIsShowOnCheckoutReadsCorrectPath(): void
    {
        $this->stubScopeFlag('etechflow_backorderetadisplay/display/show_on_checkout', true);
        $this->assertTrue($this->config->isShowOnCheckout());
    }

    public function testIsShowInOrderEmailReadsCorrectPath(): void
    {
        $this->stubScopeFlag('etechflow_backorderetadisplay/display/show_in_order_email', true);
        $this->assertTrue($this->config->isShowInOrderEmail());
    }

    // -----------------------------------------------------------------
    // Default ETA + label prefix
    // -----------------------------------------------------------------

    public function testGetDefaultEtaReturnsTrimmedString(): void
    {
        $this->stubScopeValue(
            'etechflow_backorderetadisplay/general/default_eta',
            '  Ships in 5 business days  '
        );
        $this->assertSame('Ships in 5 business days', $this->config->getDefaultEta());
    }

    public function testGetDefaultEtaReturnsEmptyWhenNotConfigured(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->assertSame('', $this->config->getDefaultEta());
    }

    public function testGetLabelPrefixReturnsTrimmedString(): void
    {
        $this->stubScopeValue(
            'etechflow_backorderetadisplay/general/label_prefix',
            ' Ships in '
        );
        $this->assertSame('Ships in', $this->config->getLabelPrefix());
    }

    // -----------------------------------------------------------------
    // Badge style — whitelist clamp (the only real logic in Config)
    // -----------------------------------------------------------------

    public function testGetBadgeStyleReturnsWarningWhenConfigured(): void
    {
        $this->stubScopeValue('etechflow_backorderetadisplay/display/badge_style', 'warning');
        $this->assertSame('warning', $this->config->getBadgeStyle());
    }

    public function testGetBadgeStyleReturnsInfoWhenConfigured(): void
    {
        $this->stubScopeValue('etechflow_backorderetadisplay/display/badge_style', 'info');
        $this->assertSame('info', $this->config->getBadgeStyle());
    }

    public function testGetBadgeStyleReturnsNeutralWhenConfigured(): void
    {
        $this->stubScopeValue('etechflow_backorderetadisplay/display/badge_style', 'neutral');
        $this->assertSame('neutral', $this->config->getBadgeStyle());
    }

    public function testGetBadgeStyleClampsUnknownToWarning(): void
    {
        // An unknown / corrupted value (e.g. someone hand-edited core_config_data
        // OR a malicious admin tried to inject "danger; <script>") should NOT
        // be rendered as a CSS class. Falls back to the safe default.
        $this->stubScopeValue(
            'etechflow_backorderetadisplay/display/badge_style',
            'danger" onclick="alert(1)'
        );
        $this->assertSame('warning', $this->config->getBadgeStyle());
    }

    public function testGetBadgeStyleClampsNullToWarning(): void
    {
        // Brand-new install, no value saved yet
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->assertSame('warning', $this->config->getBadgeStyle());
    }
}
