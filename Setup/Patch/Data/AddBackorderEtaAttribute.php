<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * Adds the per-product `backorder_eta` text attribute.
 *
 * Merchants enter a free-form ETA string ("5-7 business days", "Ships Dec 15",
 * "2 weeks") on the product edit page. When the product is on backorder, the
 * EtaResolver returns this value to the frontend display blocks.
 *
 * If left empty, the resolver falls back to the module-level default ETA from
 * Stores → Configuration → eTechFlow → Backorder ETA Display.
 */
class AddBackorderEtaAttribute implements DataPatchInterface, PatchRevertableInterface
{
    private const ATTRIBUTE_CODE = 'backorder_eta';

    /**
     * Constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory          $eavSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    /**
     * Create the backorder_eta product attribute.
     *
     * Idempotent — re-running this patch on an install that already has the
     * attribute is a no-op.
     *
     * @return self
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        if (!$eavSetup->getAttributeId(Product::ENTITY, self::ATTRIBUTE_CODE)) {
            $eavSetup->addAttribute(
                Product::ENTITY,
                self::ATTRIBUTE_CODE,
                [
                'type'                    => 'varchar',
                'label'                   => 'Restock Date',
                'note'                    => 'Customer-facing message shown verbatim when this product is sold out (out of stock, or stock-allowed-backorder is depleted). Examples: "Ships December 15", "Available in 2 weeks", "Pre-order — ships January 5". Leave empty to use the default text + label prefix from Stores → Configuration → eTechFlow → Backorder ETA Display.',
                'input'                   => 'text',
                'required'                => false,
                'sort_order'              => 220,
                'global'                  => ScopedAttributeInterface::SCOPE_STORE,
                'default'                 => '',
                'visible'                 => true,
                'user_defined'            => true,
                'searchable'              => false,
                'filterable'              => false,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => true,
                'unique'                  => false,
                'apply_to'                => 'simple,configurable,virtual,bundle,grouped',
                'is_used_in_grid'         => true,
                'is_visible_in_grid'      => true,
                'is_filterable_in_grid'   => true,
                'group'                   => 'eTechFlow Shipping',
                ]
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Remove the backorder_eta attribute.
     *
     * @return void
     */
    public function revert(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $eavSetup->removeAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);

        $this->moduleDataSetup->getConnection()->endSetup();
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
        return [];
    }
}
