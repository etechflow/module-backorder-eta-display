<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * v1.1.0 migration: enable "Visible in Grid" + "Filter in Grid" on the
 * backorder_eta attribute for installs that already had the attribute from
 * a pre-1.1.0 install (created with both flags = false).
 *
 * Fresh installs get the flags set directly via AddBackorderEtaAttribute;
 * this patch is only needed for upgrades.
 */
class EnableBackorderEtaGridColumn implements DataPatchInterface
{
    private const ATTRIBUTE_CODE = 'backorder_eta';

    /**
     * Constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavConfig                $eavConfig
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * Flip is_visible_in_grid + is_filterable_in_grid to 1 on the existing attribute.
     *
     * Idempotent — re-running has no effect once the flags are already set.
     *
     * @return self
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        try {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, self::ATTRIBUTE_CODE);
            if ($attribute && $attribute->getId()) {
                $dirty = false;
                if ((int) $attribute->getData('is_visible_in_grid') !== 1) {
                    $attribute->setData('is_visible_in_grid', 1);
                    $dirty = true;
                }
                if ((int) $attribute->getData('is_filterable_in_grid') !== 1) {
                    $attribute->setData('is_filterable_in_grid', 1);
                    $dirty = true;
                }
                if ($dirty) {
                    $attribute->save();
                }
            }
        } catch (\Exception $e) {
            // Attribute doesn't exist yet — AddBackorderEtaAttribute will run
            // with the correct flags set. Nothing to do here.
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
        return [AddBackorderEtaAttribute::class];
    }
}
