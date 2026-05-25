<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * No-op release marker for v1.2.2.
 *
 * Discipline established after the NDE v1.7.0 Keystation deploy incident
 * and adopted module-wide: every release ships at least one data patch,
 * even if it has no actual data work to do. This guarantees `setup:upgrade`
 * always has SOMETHING to register in the `patch_list` table, surfacing
 * FS / permissions / DI errors during the patch phase (which retries
 * cleanly) instead of at the end of the upgrade (which doesn't).
 *
 * Without this discipline, a version bump that ships zero patches risks
 * the same site-down condition that hit NDE v1.7.0: `setup:upgrade`
 * aborts on an unrelated post-patch step, `setup_module.data_version`
 * never advances, DbStatusValidator sees the mismatch vs module.xml
 * and 500s every request.
 *
 * Going forward, every release of this module ships at least one patch.
 * If a release genuinely has no data migration to do, this template gets
 * copied/renamed to e.g. `V123ReleaseMarker`, `V200ReleaseMarker`, etc.
 *
 * @see \ETechFlow\NextDayEligibility\Setup\Patch\Data\V171ReleaseMarker
 *      (the canonical pattern, shipped in NDE v1.7.1)
 */
class V122ReleaseMarker implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        // Intentionally no-op. Existence in `patch_list` is the only
        // side effect — that's the point. See class docblock.
        return $this;
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [];
    }
}
