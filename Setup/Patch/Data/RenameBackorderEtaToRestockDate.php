<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * v1.2.1 — relabel the `backorder_eta` product attribute. Despite the
 * class name, this patch does NOT rename the attribute_code. It only
 * updates two display strings:
 *
 *   - `frontend_label`: "Backorder ETA"  →  "Restock Date"
 *   - `note`            : (old text)      →  new customer-facing wording
 *
 * The attribute_code stays `backorder_eta` permanently. Renaming the
 * code would orphan every saved value (Magento stores values keyed by
 * attribute_id, but external integrations / themes / SQL queries
 * reference the code string). Same reason Magento core kept the
 * `manufacturer` attribute_code unchanged when its label was relabelled
 * to "Brand" — install-data safety wins over naming purity.
 *
 * In hindsight the class should have been named
 * `RelabelBackorderEtaAsRestockDate` or
 * `UpdateBackorderEtaLabelToRestockDate`. We can't rename it now without
 * either re-firing the patch on installs that already ran it, or leaving
 * a dangling `patch_list` row on disk-removed-but-DB-present installs.
 * So the name stays and this docblock is the warning.
 *
 * The original `AddBackorderEtaAttribute` patch was updated in lockstep
 * with this one, so fresh installs get the "Restock Date" label
 * directly from the attribute-creation patch. This patch only does work
 * on installs that already ran the original v1.0.x creation patch with
 * the old "Backorder ETA" label.
 *
 * Idempotent — re-running `setup:upgrade` is a no-op.
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
