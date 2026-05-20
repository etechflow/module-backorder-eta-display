<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Test\Unit\Model;

use ETechFlow\BackorderEtaDisplay\Model\Config;
use ETechFlow\BackorderEtaDisplay\Model\EtaResolver;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EtaResolverTest extends TestCase
{
    /** @var Config|MockObject */
    private Config|MockObject $config;

    /** @var EtaResolver */
    private EtaResolver $resolver;

    protected function setUp(): void
    {
        $this->config   = $this->createMock(Config::class);
        $this->resolver = new EtaResolver($this->config);
    }

    /**
     * Build a product mock that returns the given backorder_eta value.
     *
     * @param string $eta
     * @return Product|MockObject
     */
    private function buildProduct(string $eta): Product|MockObject
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->getMock();
        $product->method('getData')
            ->with('backorder_eta')
            ->willReturn($eta);

        return $product;
    }

    public function testReturnsPerProductValueWhenSet(): void
    {
        $product = $this->buildProduct('2 weeks');

        $this->config->expects($this->never())->method('getDefaultEta');

        $this->assertSame('2 weeks', $this->resolver->resolve($product));
    }

    public function testFallsBackToDefaultWhenProductValueEmpty(): void
    {
        $product = $this->buildProduct('');

        $this->config->method('getDefaultEta')->willReturn('5-7 business days');

        $this->assertSame('5-7 business days', $this->resolver->resolve($product));
    }

    public function testReturnsEmptyWhenNeitherProductNorDefaultSet(): void
    {
        $product = $this->buildProduct('');

        $this->config->method('getDefaultEta')->willReturn('');

        $this->assertSame('', $this->resolver->resolve($product));
    }

    public function testTrimsPerProductValue(): void
    {
        $product = $this->buildProduct('   3 weeks   ');

        $this->assertSame('3 weeks', $this->resolver->resolve($product));
    }

    public function testResolveDisplayReturnsPerProductValueVerbatim(): void
    {
        // Per-product values are shown as-is; no prefix is added so merchants
        // can control the wording (e.g. "Ships December 15").
        $product = $this->buildProduct('Ships December 15');

        $this->config->expects($this->never())->method('getDefaultEta');
        $this->config->expects($this->never())->method('getLabelPrefix');

        $this->assertSame('Ships December 15', $this->resolver->resolveDisplay($product));
    }

    public function testResolveDisplayPrependsPrefixToDefaultOnly(): void
    {
        $product = $this->buildProduct('');

        $this->config->method('getDefaultEta')->willReturn('5-7 business days');
        $this->config->method('getLabelPrefix')->willReturn('Ships in');

        $this->assertSame('Ships in 5-7 business days', $this->resolver->resolveDisplay($product));
    }

    public function testResolveDisplayDefaultWithoutPrefix(): void
    {
        $product = $this->buildProduct('');

        $this->config->method('getDefaultEta')->willReturn('5-7 business days');
        $this->config->method('getLabelPrefix')->willReturn('');

        $this->assertSame('5-7 business days', $this->resolver->resolveDisplay($product));
    }

    public function testResolveDisplayReturnsEmptyWhenNoEta(): void
    {
        $product = $this->buildProduct('');

        $this->config->method('getDefaultEta')->willReturn('');

        $this->assertSame('', $this->resolver->resolveDisplay($product));
    }
}
