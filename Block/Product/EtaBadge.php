<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Block\Product;

use ETechFlow\BackorderEtaDisplay\Model\BackorderDetector;
use ETechFlow\BackorderEtaDisplay\Model\Config;
use ETechFlow\BackorderEtaDisplay\Model\EtaResolver;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Renders the ETA badge below the price on the product detail page.
 */
class EtaBadge extends Template
{
    /**
     * Constructor.
     *
     * @param Context           $context
     * @param Config            $config
     * @param BackorderDetector $detector
     * @param EtaResolver       $etaResolver
     * @param Registry          $registry
     * @param array             $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly BackorderDetector $detector,
        private readonly EtaResolver $etaResolver,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether the badge should render on this page.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        if (!$this->config->isEnabled() || !$this->config->isShowOnProductPage()) {
            return false;
        }

        $product = $this->getCurrentProduct();
        if ($product === null) {
            return false;
        }

        if (!$this->detector->isBackordered($product)) {
            return false;
        }

        return $this->getDisplayText() !== '';
    }

    /**
     * The text to render inside the badge.
     *
     * @return string
     */
    public function getDisplayText(): string
    {
        $product = $this->getCurrentProduct();
        if ($product === null) {
            return '';
        }

        return $this->etaResolver->resolveDisplay($product);
    }

    /**
     * Badge style modifier (warning / info / neutral).
     *
     * @return string
     */
    public function getBadgeStyle(): string
    {
        return $this->config->getBadgeStyle();
    }

    /**
     * Currently viewed product, if any.
     *
     * @return ProductInterface|null
     */
    public function getCurrentProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }
}
