<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Test\Unit\Model;

use ETechFlow\BackorderEtaDisplay\Model\BackorderDetector;
use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the BackorderDetector — the heart of the BED module. Decides
 * "should the ETA badge render?" based on stock state.
 *
 * Three positive cases (return true):
 *   1. Stock item flagged out-of-stock (is_in_stock = 0)
 *   2. Backorders allowed AND stock_qty <= min_qty
 *   3. Ordered qty > saleable qty (partial backorder, cart-side)
 *
 * Short-circuits to false (don't render) for:
 *   - Configurable / bundle parents (children carry the eligibility)
 *   - Virtual / downloadable products (no shipping → no ETA)
 *   - Stock item missing entirely (defensive null check)
 *
 * Anywhere the detector incorrectly returns true would surface as
 * "ETA badge showing on an in-stock product" — a customer-confidence-
 * destroying bug. These tests pin the contract.
 */
class BackorderDetectorTest extends TestCase
{
    private StockRegistryInterface|MockObject $stockRegistry;
    private BackorderDetector $detector;

    protected function setUp(): void
    {
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->detector = new BackorderDetector($this->stockRegistry);
    }

    /**
     * Build a product mock with the given type id.
     */
    private function buildProduct(string $typeId = 'simple', int $id = 42): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getId')->willReturn($id);
        return $product;
    }

    /**
     * Stub the stock registry to return a stock item with the given values.
     */
    private function stubStockItem(
        bool $hasItemId,
        bool $isInStock,
        float $qty,
        int $backorders,
        float $minQty = 0.0
    ): void {
        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getItemId')->willReturn($hasItemId ? 1 : 0);
        $stockItem->method('getIsInStock')->willReturn($isInStock);
        $stockItem->method('getQty')->willReturn($qty);
        $stockItem->method('getBackorders')->willReturn($backorders);
        $stockItem->method('getMinQty')->willReturn($minQty);
        $this->stockRegistry->method('getStockItem')->willReturn($stockItem);
    }

    // -----------------------------------------------------------------
    // Case 1: explicitly out of stock
    // -----------------------------------------------------------------

    public function testExplicitlyOutOfStockReturnsTrue(): void
    {
        $this->stubStockItem(hasItemId: true, isInStock: false, qty: 0, backorders: 0);
        $this->assertTrue($this->detector->isBackordered($this->buildProduct()));
    }

    public function testOutOfStockBeatsPositiveQty(): void
    {
        // is_in_stock=0 wins even when qty is positive (Magento's stock
        // reservations can leave qty > 0 but is_in_stock = false)
        $this->stubStockItem(hasItemId: true, isInStock: false, qty: 5, backorders: 0);
        $this->assertTrue($this->detector->isBackordered($this->buildProduct()));
    }

    // -----------------------------------------------------------------
    // Case 2: backorders allowed + depleted
    // -----------------------------------------------------------------

    public function testBackordersAllowedAndDepletedReturnsTrue(): void
    {
        // qty=0, min_qty=0 → "depleted" (qty <= min_qty)
        $this->stubStockItem(hasItemId: true, isInStock: true, qty: 0, backorders: 1);
        $this->assertTrue($this->detector->isBackordered($this->buildProduct()));
    }

    public function testBackordersAllowedWithStockReturnsFalse(): void
    {
        // qty=10, backorders=1 → not yet depleted → not backordered
        $this->stubStockItem(hasItemId: true, isInStock: true, qty: 10, backorders: 1);
        $this->assertFalse($this->detector->isBackordered($this->buildProduct()));
    }

    public function testBackordersDisallowedAndDepletedReturnsFalse(): void
    {
        // qty=0, backorders=0 → not in stock but backorders not allowed →
        // (handled by case 1 if is_in_stock=0; here we test the
        // is_in_stock=1 + backorders=0 combination, which is impossible
        // in practice but defensively returns false)
        $this->stubStockItem(hasItemId: true, isInStock: true, qty: 0, backorders: 0);
        // qty 0 > saleable 0? No (0 is not > 0). So case 3 doesn't fire.
        // Case 2 needs backorders > 0, so doesn't fire either.
        $this->assertFalse($this->detector->isBackordered($this->buildProduct()));
    }

    public function testBackordersAllowedAndExactlyMinQtyReturnsTrue(): void
    {
        // qty = min_qty (boundary case — "<= min_qty" semantic)
        $this->stubStockItem(hasItemId: true, isInStock: true, qty: 3, backorders: 1, minQty: 3);
        $this->assertTrue($this->detector->isBackordered($this->buildProduct()));
    }

    // -----------------------------------------------------------------
    // Case 3: ordered qty exceeds saleable qty (partial backorder)
    // -----------------------------------------------------------------

    public function testOrderedQtyExceedsSaleableQtyReturnsTrue(): void
    {
        // Stock = 5, requested = 10 → partial backorder.
        $this->stubStockItem(hasItemId: true, isInStock: true, qty: 5, backorders: 0);
        $this->assertTrue($this->detector->isBackordered(
            $this->buildProduct(),
            requestedQty: 10
        ));
    }

    public function testOrderedQtyWithinSaleableReturnsFalse(): void
    {
        // Stock = 5, requested = 3 → fine.
        $this->stubStockItem(hasItemId: true, isInStock: true, qty: 5, backorders: 0);
        $this->assertFalse($this->detector->isBackordered(
            $this->buildProduct(),
            requestedQty: 3
        ));
    }

    public function testMinQtyReducesSaleableQty(): void
    {
        // Stock 10, min_qty 8 → saleable = 2. Requested 3 → backorder.
        $this->stubStockItem(hasItemId: true, isInStock: true, qty: 10, backorders: 0, minQty: 8);
        $this->assertTrue($this->detector->isBackordered(
            $this->buildProduct(),
            requestedQty: 3
        ));
    }

    // -----------------------------------------------------------------
    // Short-circuits: container types + virtuals + missing stock
    // -----------------------------------------------------------------

    public function testConfigurableParentReturnsFalse(): void
    {
        // No stock registry call expected — short-circuits on type id
        $this->stockRegistry->expects($this->never())->method('getStockItem');
        $this->assertFalse(
            $this->detector->isBackordered($this->buildProduct(ConfigurableType::TYPE_CODE))
        );
    }

    public function testBundleParentReturnsFalse(): void
    {
        $this->stockRegistry->expects($this->never())->method('getStockItem');
        $this->assertFalse(
            $this->detector->isBackordered($this->buildProduct(BundleType::TYPE_CODE))
        );
    }

    public function testVirtualProductReturnsFalse(): void
    {
        // Virtual products don't ship → no ETA makes sense
        $this->stockRegistry->expects($this->never())->method('getStockItem');
        $this->assertFalse(
            $this->detector->isBackordered($this->buildProduct('virtual'))
        );
    }

    public function testDownloadableProductReturnsFalse(): void
    {
        $this->stockRegistry->expects($this->never())->method('getStockItem');
        $this->assertFalse(
            $this->detector->isBackordered($this->buildProduct('downloadable'))
        );
    }

    public function testMissingStockItemReturnsFalse(): void
    {
        // Defensive: stock registry returned a stock item with no item id
        // (e.g. brand-new product, stock not yet populated). Don't crash.
        $this->stubStockItem(hasItemId: false, isInStock: false, qty: 0, backorders: 0);
        $this->assertFalse($this->detector->isBackordered($this->buildProduct()));
    }

    // -----------------------------------------------------------------
    // Healthy in-stock product (the most common cart shape) returns false
    // -----------------------------------------------------------------

    public function testInStockWithPositiveQtyReturnsFalse(): void
    {
        // The canonical "everything is fine" case — most carts hit this
        $this->stubStockItem(hasItemId: true, isInStock: true, qty: 100, backorders: 0);
        $this->assertFalse($this->detector->isBackordered(
            $this->buildProduct(),
            requestedQty: 1
        ));
    }
}
