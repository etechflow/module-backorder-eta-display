<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * No-op release marker for v1.2.3.
 *
 * Continues the always-a-patch discipline established in NDE v1.7.1 and
 * BED v1.2.2 (V122ReleaseMarker). Every release ships at least one data
 * patch so `setup:upgrade` always has something to register in
 * `patch_list` — surfacing FS / permissions / DI errors during the
 * patch phase (which retries cleanly) instead of at the end of the
 * upgrade (which doesn't).
 *
 * @see V122ReleaseMarker  v1.2.2 marker
 */
class V123ReleaseMarker implements DataPatchInterface
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
        return [V122ReleaseMarker::class];
    }
}
