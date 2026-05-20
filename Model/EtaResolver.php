<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Model;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Single source of truth for the customer-facing ETA string of a given product.
 *
 * Resolution order:
 *   1. Per-product `backorder_eta` attribute (if set)
 *   2. Module-level Default ETA from admin config
 *
 * Returns an empty string when neither is configured — display blocks treat
 * this as "do not render".
 */
class EtaResolver
{
    private const ATTRIBUTE_CODE = 'backorder_eta';

    /**
     * Constructor.
     *
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Resolve the raw ETA text for a product (without the label prefix).
     *
     * @param ProductInterface $product
     * @return string
     */
    public function resolve(ProductInterface $product): string
    {
        $perProduct = trim((string) $product->getData(self::ATTRIBUTE_CODE));
        if ($perProduct !== '') {
            return $perProduct;
        }

        return $this->config->getDefaultEta();
    }

    /**
     * Resolve the full display string for a product.
     *
     * Resolution rules:
     *   - If per-product `backorder_eta` is set, return it verbatim. The
     *     merchant controls the exact wording; no prefix is added.
     *   - If per-product is empty, return the default ETA prefixed by the
     *     configured label (e.g. "Ships in 5-7 business days").
     *   - If neither is set, return an empty string.
     *
     * @param ProductInterface $product
     * @return string
     */
    public function resolveDisplay(ProductInterface $product): string
    {
        $perProduct = trim((string) $product->getData(self::ATTRIBUTE_CODE));
        if ($perProduct !== '') {
            return $perProduct;
        }

        $default = $this->config->getDefaultEta();
        if ($default === '') {
            return '';
        }

        $prefix = $this->config->getLabelPrefix();
        return $prefix !== '' ? trim($prefix . ' ' . $default) : $default;
    }
}
