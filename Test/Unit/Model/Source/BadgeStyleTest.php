<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Test\Unit\Model\Source;

use ETechFlow\BackorderEtaDisplay\Model\Source\BadgeStyle;
use PHPUnit\Framework\TestCase;

/**
 * BadgeStyle is the admin-form source model for the badge_style dropdown.
 * Pin its option set so we don't accidentally drop / rename one of the
 * three supported styles (which would silently break the `getBadgeStyle()`
 * whitelist clamp on existing installs that stored an old value).
 */
class BadgeStyleTest extends TestCase
{
    private BadgeStyle $source;

    protected function setUp(): void
    {
        $this->source = new BadgeStyle();
    }

    public function testReturnsExactlyThreeOptions(): void
    {
        $options = $this->source->toOptionArray();
        $this->assertCount(3, $options);
    }

    public function testReturnsTheCanonicalThreeStyles(): void
    {
        // The values returned here MUST match the BADGE_STYLES whitelist
        // in Config::getBadgeStyle(). If you change one, change both.
        $values = array_map(static fn($o) => $o['value'], $this->source->toOptionArray());
        $this->assertSame(['warning', 'info', 'neutral'], $values);
    }

    public function testEveryOptionHasAValueAndLabel(): void
    {
        // Magento crashes if a source option lacks either field — defensive
        // shape check.
        foreach ($this->source->toOptionArray() as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertIsString($option['value']);
            // label may be a Magento\Framework\Phrase or a string
            $this->assertTrue(is_string($option['label']) || is_object($option['label']));
        }
    }
}
