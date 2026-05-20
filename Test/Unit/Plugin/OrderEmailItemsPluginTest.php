<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Test\Unit\Plugin;

use ETechFlow\BackorderEtaDisplay\Model\BackorderDetector;
use ETechFlow\BackorderEtaDisplay\Model\Config;
use ETechFlow\BackorderEtaDisplay\Model\EtaResolver;
use ETechFlow\BackorderEtaDisplay\Plugin\OrderEmailItemsPlugin;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Escaper;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Block\Order\Email\Items;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the order-confirmation-email plugin.
 *
 * Covers the gates (module enabled + show-in-email toggle), the cart-scan
 * logic (only backorder items with a resolvable ETA get a row), and the
 * defensive try/catch (an internal exception must not crash the email send).
 */
class OrderEmailItemsPluginTest extends TestCase
{
    private Config|MockObject $config;
    private BackorderDetector|MockObject $detector;
    private EtaResolver|MockObject $etaResolver;
    private ProductCollectionFactory|MockObject $productCollectionFactory;
    private Escaper|MockObject $escaper;
    private LoggerInterface|MockObject $logger;
    private OrderEmailItemsPlugin $plugin;

    protected function setUp(): void
    {
        $this->config                   = $this->createMock(Config::class);
        $this->detector                 = $this->createMock(BackorderDetector::class);
        $this->etaResolver              = $this->createMock(EtaResolver::class);
        $this->productCollectionFactory = $this->createMock(ProductCollectionFactory::class);
        $this->escaper                  = $this->createMock(Escaper::class);
        $this->logger                   = $this->createMock(LoggerInterface::class);

        // Escaper is a pass-through in tests — we're not testing escape behaviour
        $this->escaper->method('escapeHtml')->willReturnArgument(0);

        $this->plugin = new OrderEmailItemsPlugin(
            $this->config,
            $this->detector,
            $this->etaResolver,
            $this->productCollectionFactory,
            $this->escaper,
            $this->logger
        );
    }

    /**
     * Build an order line item mock with given product id + name + qty.
     */
    private function buildOrderItem(int $productId, string $name, float $qty): MockObject
    {
        $item = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['getProductId', 'getName', 'getQtyOrdered'])
            ->getMock();
        $item->method('getProductId')->willReturn($productId);
        $item->method('getName')->willReturn($name);
        $item->method('getQtyOrdered')->willReturn($qty);
        return $item;
    }

    /**
     * Build a product mock keyed by id, used by the bulk-load stub.
     */
    private function buildProduct(int $productId): Product|MockObject
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($productId);
        return $product;
    }

    /**
     * Stub the product collection factory to return a collection containing
     * the given products. Keyed by id for assertion convenience.
     *
     * @param array<int, Product|MockObject> $productsById
     */
    private function stubProductCollection(array $productsById): void
    {
        $collection = $this->createMock(ProductCollection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addIdFilter')->willReturnSelf();
        $collection->method('getIterator')
            ->willReturn(new \ArrayIterator(array_values($productsById)));

        $this->productCollectionFactory->method('create')->willReturn($collection);
    }

    /**
     * Build an Order mock returning given items + a non-null sentinel.
     *
     * @param array $items
     */
    private function buildOrder(array $items): OrderInterface|MockObject
    {
        $order = $this->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllVisibleItems'])
            ->getMock();
        $order->method('getAllVisibleItems')->willReturn($items);
        return $order;
    }

    /**
     * Build an Items block mock returning the given order.
     */
    private function buildItemsBlock(?OrderInterface $order): Items|MockObject
    {
        $block = $this->getMockBuilder(Items::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder'])
            ->getMock();
        $block->method('getOrder')->willReturn($order);
        return $block;
    }

    // -----------------------------------------------------------------
    // Gates
    // -----------------------------------------------------------------

    public function testReturnsOriginalWhenModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        // None of the downstream code should run
        $this->detector->expects($this->never())->method('isBackordered');

        $result = $this->plugin->afterToHtml($this->buildItemsBlock(null), '<table>original</table>');

        $this->assertSame('<table>original</table>', $result);
    }

    public function testReturnsOriginalWhenShowInEmailDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInOrderEmail')->willReturn(false);

        $this->detector->expects($this->never())->method('isBackordered');

        $result = $this->plugin->afterToHtml($this->buildItemsBlock(null), '<table>original</table>');

        $this->assertSame('<table>original</table>', $result);
    }

    public function testReturnsOriginalWhenOrderIsNull(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInOrderEmail')->willReturn(true);

        $result = $this->plugin->afterToHtml($this->buildItemsBlock(null), '<table>original</table>');

        $this->assertSame('<table>original</table>', $result);
    }

    public function testReturnsOriginalWhenOrderHasNoItems(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInOrderEmail')->willReturn(true);

        $result = $this->plugin->afterToHtml(
            $this->buildItemsBlock($this->buildOrder([])),
            '<table>original</table>'
        );

        $this->assertSame('<table>original</table>', $result);
    }

    // -----------------------------------------------------------------
    // Cart-scan logic
    // -----------------------------------------------------------------

    public function testReturnsOriginalWhenNoItemIsBackordered(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInOrderEmail')->willReturn(true);

        $product = $this->buildProduct(42);
        $this->stubProductCollection([42 => $product]);

        $this->detector->method('isBackordered')->willReturn(false);

        $result = $this->plugin->afterToHtml(
            $this->buildItemsBlock($this->buildOrder([
                $this->buildOrderItem(42, 'In-stock widget', 1.0),
            ])),
            '<table>original</table>'
        );

        $this->assertSame('<table>original</table>', $result);
    }

    public function testReturnsOriginalWhenBackorderItemHasNoEta(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInOrderEmail')->willReturn(true);

        $product = $this->buildProduct(42);
        $this->stubProductCollection([42 => $product]);

        $this->detector->method('isBackordered')->willReturn(true);
        // Empty ETA — should skip this entry
        $this->etaResolver->method('resolveDisplay')->willReturn('');

        $result = $this->plugin->afterToHtml(
            $this->buildItemsBlock($this->buildOrder([
                $this->buildOrderItem(42, 'No-ETA widget', 1.0),
            ])),
            '<table>original</table>'
        );

        $this->assertSame('<table>original</table>', $result);
    }

    public function testAppendsEtaBlockWhenBackorderItemHasEta(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInOrderEmail')->willReturn(true);
        $this->config->method('getBadgeStyle')->willReturn('warning');

        $product = $this->buildProduct(42);
        $this->stubProductCollection([42 => $product]);

        $this->detector->method('isBackordered')->willReturn(true);
        $this->etaResolver->method('resolveDisplay')->willReturn('Ships 28 May 2026');

        $result = $this->plugin->afterToHtml(
            $this->buildItemsBlock($this->buildOrder([
                $this->buildOrderItem(42, 'Backorder widget', 1.0),
            ])),
            '<table>original</table>'
        );

        // Original content is preserved
        $this->assertStringStartsWith('<table>original</table>', $result);
        // The appended block carries the item + ETA
        $this->assertStringContainsString('Backorder widget', $result);
        $this->assertStringContainsString('Ships 28 May 2026', $result);
        // Heading present
        $this->assertStringContainsString('Estimated delivery times', $result);
    }

    public function testOnlyBackorderItemsAppearInBlock(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInOrderEmail')->willReturn(true);
        $this->config->method('getBadgeStyle')->willReturn('info');

        $productInStock = $this->buildProduct(10);
        $productBackorder = $this->buildProduct(20);
        $this->stubProductCollection([10 => $productInStock, 20 => $productBackorder]);

        $this->detector->method('isBackordered')->willReturnCallback(
            static fn($product) => (int) $product->getId() === 20
        );
        $this->etaResolver->method('resolveDisplay')->willReturn('2 weeks');

        $result = $this->plugin->afterToHtml(
            $this->buildItemsBlock($this->buildOrder([
                $this->buildOrderItem(10, 'In-stock thing', 1.0),
                $this->buildOrderItem(20, 'Backorder thing', 1.0),
            ])),
            '<table>original</table>'
        );

        $this->assertStringContainsString('Backorder thing', $result);
        $this->assertStringNotContainsString('In-stock thing', $result);
        $this->assertStringContainsString('2 weeks', $result);
    }

    // -----------------------------------------------------------------
    // Defensive exception handling
    // -----------------------------------------------------------------

    public function testExceptionInternallyCaughtAndLogged(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInOrderEmail')->willReturn(true);

        $order = $this->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllVisibleItems'])
            ->getMock();
        $order->method('getAllVisibleItems')
            ->willThrowException(new \RuntimeException('DB error inside Order::getAllVisibleItems'));

        $this->logger->expects($this->once())->method('error');

        $result = $this->plugin->afterToHtml(
            $this->buildItemsBlock($order),
            '<table>original</table>'
        );

        // Must NOT throw, must return original
        $this->assertSame('<table>original</table>', $result);
    }

    // -----------------------------------------------------------------
    // Result-type robustness
    // -----------------------------------------------------------------

    public function testNonStringResultIsCastBeforeReturning(): void
    {
        // Defensive: an upstream plugin might return something weird.
        // The plugin signature is `: string`, so we cast.
        $this->config->method('isEnabled')->willReturn(false);

        $result = $this->plugin->afterToHtml($this->buildItemsBlock(null), 'plain string');

        $this->assertSame('plain string', $result);
        $this->assertIsString($result);
    }
}
