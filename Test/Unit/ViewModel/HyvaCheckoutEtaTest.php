<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Test\Unit\ViewModel;

use ETechFlow\BackorderEtaDisplay\Model\BackorderDetector;
use ETechFlow\BackorderEtaDisplay\Model\Config;
use ETechFlow\BackorderEtaDisplay\Model\EtaResolver;
use ETechFlow\BackorderEtaDisplay\ViewModel\HyvaCheckoutEta;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * ViewModel that supplies the Hyvä Checkout ETA summary block.
 *
 * Tests focus on:
 *   1. Visibility — short-circuits when module disabled / Show on Checkout off
 *   2. Style classes — the badge_style → Tailwind class mapping (the
 *      whitelist clamp in Config means we only need to test the 3 known
 *      styles plus the default fallback)
 *
 * Cart-walking + product-loading paths are exercised by
 * `bin/magento etechflow:bed:verify` against a real DB rather than
 * deeply-mocked here — unit-testing the collection bulk-load adds little
 * confidence and a lot of mock plumbing.
 */
class HyvaCheckoutEtaTest extends TestCase
{
    private Config|MockObject $config;
    private BackorderDetector|MockObject $detector;
    private EtaResolver|MockObject $etaResolver;
    private CheckoutSession|MockObject $checkoutSession;
    private ProductCollectionFactory|MockObject $productCollectionFactory;
    private LoggerInterface|MockObject $logger;
    private HyvaCheckoutEta $viewModel;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->detector = $this->createMock(BackorderDetector::class);
        $this->etaResolver = $this->createMock(EtaResolver::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->productCollectionFactory = $this->createMock(ProductCollectionFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->viewModel = new HyvaCheckoutEta(
            $this->config,
            $this->detector,
            $this->etaResolver,
            $this->checkoutSession,
            $this->productCollectionFactory,
            $this->logger
        );
    }

    // -----------------------------------------------------------------
    // isVisible — short-circuit conditions
    // -----------------------------------------------------------------

    public function testNotVisibleWhenModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        // isShowOnCheckout shouldn't even be consulted
        $this->config->expects($this->never())->method('isShowOnCheckout');
        $this->checkoutSession->expects($this->never())->method('getQuote');
        $this->assertFalse($this->viewModel->isVisible());
    }

    public function testNotVisibleWhenShowOnCheckoutIsOff(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowOnCheckout')->willReturn(false);
        $this->checkoutSession->expects($this->never())->method('getQuote');
        $this->assertFalse($this->viewModel->isVisible());
    }

    public function testNotVisibleWhenCartIsEmpty(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowOnCheckout')->willReturn(true);
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([]);
        $this->checkoutSession->method('getQuote')->willReturn($quote);
        $this->assertFalse($this->viewModel->isVisible());
    }

    public function testGetBackorderEntriesEmptyWhenCheckoutSessionThrows(): void
    {
        // CheckoutSession can throw on cart-less requests; the ViewModel
        // logs + returns [] rather than crashing the checkout page.
        $this->checkoutSession->method('getQuote')->willThrowException(new \RuntimeException('no quote'));
        $this->logger->expects($this->once())->method('error');
        $this->assertSame([], $this->viewModel->getBackorderEntries());
    }

    // -----------------------------------------------------------------
    // getStyleClasses — Tailwind class mapping (visual contract)
    // -----------------------------------------------------------------

    public function testStyleClassesForWarning(): void
    {
        $this->config->method('getBadgeStyle')->willReturn('warning');
        $classes = $this->viewModel->getStyleClasses();
        $this->assertArrayHasKey('container', $classes);
        $this->assertArrayHasKey('accent', $classes);
        $this->assertStringContainsString('amber', $classes['container']);
        $this->assertStringContainsString('amber', $classes['accent']);
    }

    public function testStyleClassesForInfo(): void
    {
        $this->config->method('getBadgeStyle')->willReturn('info');
        $classes = $this->viewModel->getStyleClasses();
        $this->assertStringContainsString('blue', $classes['container']);
        $this->assertStringContainsString('blue', $classes['accent']);
    }

    public function testStyleClassesForNeutral(): void
    {
        $this->config->method('getBadgeStyle')->willReturn('neutral');
        $classes = $this->viewModel->getStyleClasses();
        $this->assertStringContainsString('slate', $classes['container']);
        $this->assertStringContainsString('slate', $classes['accent']);
    }

    public function testStyleClassesFallsBackToWarningOnUnknown(): void
    {
        // Defence-in-depth — Config::getBadgeStyle whitelist-clamps to one
        // of the 3 known styles, but if a future change widened that or
        // bypassed the clamp, the ViewModel's match expression still falls
        // back to warning's class set.
        $this->config->method('getBadgeStyle')->willReturn('something-unexpected');
        $classes = $this->viewModel->getStyleClasses();
        $this->assertStringContainsString('amber', $classes['container']);
    }

    public function testStyleClassesShapeIsStable(): void
    {
        // The Hyvä template reads exactly these two keys — guard against
        // accidental rename / drop.
        $this->config->method('getBadgeStyle')->willReturn('warning');
        $classes = $this->viewModel->getStyleClasses();
        $this->assertSame(['container', 'accent'], array_keys($classes));
        $this->assertIsString($classes['container']);
        $this->assertIsString($classes['accent']);
        $this->assertNotEmpty($classes['container']);
        $this->assertNotEmpty($classes['accent']);
    }

    public function testStyleClassesIncludeDarkModeVariants(): void
    {
        // Every style ships both light and dark mode classes — Hyvä's
        // dark-mode support is a v1.1.0 selling point. Don't regress.
        foreach (['warning', 'info', 'neutral'] as $style) {
            $config = $this->createMock(Config::class);
            $config->method('getBadgeStyle')->willReturn($style);
            $vm = new HyvaCheckoutEta(
                $config,
                $this->detector,
                $this->etaResolver,
                $this->checkoutSession,
                $this->productCollectionFactory,
                $this->logger
            );
            $classes = $vm->getStyleClasses();
            $this->assertStringContainsString('dark:', $classes['container'], "Style '{$style}' is missing dark: classes");
            $this->assertStringContainsString('dark:', $classes['accent'], "Style '{$style}' accent is missing dark: classes");
        }
    }
}
