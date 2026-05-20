<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * v1.2.1 — rename the customer-facing label of the `backorder_eta` product
 * attribute from "Backorder ETA" to "Restock Date".
 *
 * The DB column name stays `backorder_eta` (renaming would break every
 * install's data). Only the frontend label + admin note change. Shoppers
 * never see either — but merchants do, on every product edit page.
 *
 * Why: "backorder" is industry jargon. Customer-facing language uses
 * "restock date" / "available date" / "temporarily sold out" — shoppers
 * understand those without context. Merchants benefit too because the
 * field label now matches the customer-facing language they'd use when
 * writing the actual ETA text into it.
 *
 * Idempotent — checks the current label before overwriting. Re-running
 * setup:upgrade is a no-op.
 *
 * The original AddBackorderEtaAttribute patch was updated in lockstep
 * with this one, so fresh installs get the new label directly. This
 * patch only fires on installs that already ran the v1.0.x patch.
 */
class RenameBackorderEtaToRestockDate implements DataPatchInterface
{
    private const ATTRIBUTE_CODE = 'backorder_eta';
    private const NEW_LABEL = 'Restock Date';
    private const NEW_NOTE = 'Customer-facing message shown verbatim when this product is sold out (out of stock, or stock-allowed-backorder is depleted). Examples: "Ships December 15", "Available in 2 weeks", "Pre-order — ships January 5". Leave empty to use the default text + label prefix from Stores → Configuration → eTechFlow → Backorder ETA Display.';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributeId = $eavSetup->getAttributeId(Product::ENTITY, self::ATTRIBUTE_CODE);
        if ($attributeId) {
            // Update only the customer-facing labels. The attribute code,
            // backend type, and stored values are untouched.
            $eavSetup->updateAttribute(
                Product::ENTITY,
                self::ATTRIBUTE_CODE,
                'frontend_label',
                self::NEW_LABEL
            );
            $eavSetup->updateAttribute(
                Product::ENTITY,
                self::ATTRIBUTE_CODE,
                'note',
                self::NEW_NOTE
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        // Must run after the original attribute creation
        return [AddBackorderEtaAttribute::class];
    }
}
