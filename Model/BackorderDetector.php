<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Model;

use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;

/**
 * Detects whether a product is in a backorder or partial-stock state.
 *
 * Mirrors the detection logic used by ETechFlow_BackorderShippingRestrictor
 * but is self-contained so this module works standalone. When both modules
 * are installed they detect the same state independently.
 *
 * Three cases trigger backorder status:
 *  1. Product is out of stock entirely (is_in_stock = false).
 *  2. Backorders are allowed and qty is at or below the min-qty threshold.
 *  3. Requested qty exceeds saleable stock (partial backorder).
 */
class BackorderDetector
{
    private const CONTAINER_TYPES = [
        ConfigurableType::TYPE_CODE,
        BundleType::TYPE_CODE,
    ];

    private const VIRTUAL_TYPES = ['virtual', 'downloadable'];

    /**
     * Constructor.
     *
     * @param StockRegistryInterface $stockRegistry
     */
    public function __construct(
        private readonly StockRegistryInterface $stockRegistry
    ) {
    }

    /**
     * Determine whether a product is currently in a backorder state.
     *
     * @param ProductInterface $product
     * @param int|float        $requestedQty Qty being added or already in cart, used for case 3
     * @param int              $websiteId
     * @return bool
     */
    public function isBackordered(ProductInterface $product, $requestedQty = 1, int $websiteId = 0): bool
    {
        $type = (string) $product->getTypeId();

        if (in_array($type, self::CONTAINER_TYPES, true)) {
            return false;
        }

        if (in_array($type, self::VIRTUAL_TYPES, true)) {
            return false;
        }

        $stockItem = $this->stockRegistry->getStockItem(
            (int) $product->getId(),
            $websiteId
        );

        if (!$stockItem || !$stockItem->getItemId()) {
            return false;
        }

        // Case 1: explicitly out of stock
        if (!$stockItem->getIsInStock()) {
            return true;
        }

        $stockQty = (float) $stockItem->getQty();
        $minQty   = (float) $stockItem->getMinQty();

        // Case 2: backorders allowed and depleted
        if ((int) $stockItem->getBackorders() > 0 && $stockQty <= $minQty) {
            return true;
        }

        // Case 3: ordered qty exceeds saleable qty
        $orderedQty  = (float) $requestedQty;
        $saleableQty = $stockQty - $minQty;

        if ($stockQty > 0 && $orderedQty > $saleableQty) {
            return true;
        }

        return false;
    }
}
