<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BadgeStyle implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase|string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'warning', 'label' => __('Warning (amber)')],
            ['value' => 'info',    'label' => __('Info (blue)')],
            ['value' => 'neutral', 'label' => __('Neutral (grey)')],
        ];
    }
}
