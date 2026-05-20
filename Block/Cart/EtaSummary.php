<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Block\Cart;

use ETechFlow\BackorderEtaDisplay\Model\BackorderDetector;
use ETechFlow\BackorderEtaDisplay\Model\Config;
use ETechFlow\BackorderEtaDisplay\Model\EtaResolver;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Psr\Log\LoggerInterface;

/**
 * Renders a summary of ETA notices for all backorder items in the cart.
 *
 * Used on both the shopping cart page and the checkout shipping step. The
 * `display_mode` layout argument selects which per-location toggle gates the
 * block's visibility (`cart` → isShowOnCart, `checkout` → isShowOnCheckout).
 */
class EtaSummary extends Template
{
    /**
     * Constructor.
     *
     * @param Context                  $context
     * @param Config                   $config
     * @param BackorderDetector        $detector
     * @param EtaResolver              $etaResolver
     * @param CheckoutSession          $checkoutSession
     * @param ProductCollectionFactory $productCollectionFactory
     * @param LoggerInterface          $logger
     * @param array                    $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly BackorderDetector $detector,
        private readonly EtaResolver $etaResolver,
        private readonly CheckoutSession $checkoutSession,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether the summary should render.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        $mode = (string) ($this->getData('display_mode') ?: 'cart');
        if ($mode === 'checkout' && !$this->config->isShowOnCheckout()) {
            return false;
        }
        if ($mode === 'cart' && !$this->config->isShowOnCart()) {
            return false;
        }

        return !empty($this->getBackorderEntries());
    }

    /**
     * Backorder entries to display, each as ['name' => string, 'eta' => string].
     *
     * Products are bulk-loaded in a single collection query so we make one DB
     * round-trip regardless of cart size — quote items only carry a thin
     * snapshot, so we DO need to load full products to reach the
     * `backorder_eta` EAV attribute, but we never need to load them one by one.
     *
     * @return array<int, array{name: string, eta: string}>
     */
    public function getBackorderEntries(): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $items = $quote->getAllVisibleItems();
        } catch (\Exception $e) {
            $this->logger->error(
                'ETechFlow_BackorderEtaDisplay: Could not load cart items.',
                ['exception' => $e->getMessage()]
            );
            return [];
        }

        if (empty($items)) {
            return [];
        }

        $productIds = [];
        foreach ($items as $item) {
            if ($item->isDeleted()) {
                continue;
            }
            $pid = (int) $item->getProductId();
            if ($pid > 0) {
                $productIds[$pid] = true;
            }
        }
        $productIds = array_keys($productIds);

        if (empty($productIds)) {
            return [];
        }

        $products = $this->loadProductsById($productIds);
        $entries  = [];

        foreach ($items as $item) {
            if ($item->isDeleted()) {
                continue;
            }

            $product = $products[(int) $item->getProductId()] ?? null;
            if (!$product) {
                continue;
            }

            $qty = (float) $item->getQty();
            if (!$this->detector->isBackordered($product, $qty)) {
                continue;
            }

            $eta = $this->etaResolver->resolveDisplay($product);
            if ($eta === '') {
                continue;
            }

            $entries[] = [
                'name' => (string) $item->getName(),
                'eta'  => $eta,
            ];
        }

        return $entries;
    }

    /**
     * Bulk-load products by ID for O(1) lookup by id.
     *
     * @param int[] $productIds
     * @return array<int, \Magento\Catalog\Api\Data\ProductInterface>
     */
    private function loadProductsById(array $productIds): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addIdFilter($productIds);

        $byId = [];
        foreach ($collection as $product) {
            $byId[(int) $product->getId()] = $product;
        }

        return $byId;
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
}
