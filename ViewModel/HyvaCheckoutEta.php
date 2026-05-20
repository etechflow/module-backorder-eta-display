<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\ViewModel;

use ETechFlow\BackorderEtaDisplay\Model\BackorderDetector;
use ETechFlow\BackorderEtaDisplay\Model\Config;
use ETechFlow\BackorderEtaDisplay\Model\EtaResolver;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;

/**
 * View model for the Hyvä Checkout backorder ETA summary.
 *
 * Used only when Hyvä Checkout is installed; the standard Magento checkout
 * uses the Knockout/server-side EtaSummary block instead.
 */
class HyvaCheckoutEta implements ArgumentInterface
{
    /**
     * Constructor.
     *
     * @param Config                   $config
     * @param BackorderDetector        $detector
     * @param EtaResolver              $etaResolver
     * @param CheckoutSession          $checkoutSession
     * @param ProductCollectionFactory $productCollectionFactory
     * @param LoggerInterface          $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly BackorderDetector $detector,
        private readonly EtaResolver $etaResolver,
        private readonly CheckoutSession $checkoutSession,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Whether the summary should render in Hyvä Checkout.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        if (!$this->config->isEnabled() || !$this->config->isShowOnCheckout()) {
            return false;
        }

        return !empty($this->getBackorderEntries());
    }

    /**
     * Backorder entries — each as ['name' => string, 'eta' => string].
     *
     * Products are bulk-loaded in a single collection query — checkout pages
     * cannot afford an N+1 round-trip per cart item.
     *
     * @return array<int, array{name: string, eta: string}>
     */
    public function getBackorderEntries(): array
    {
        try {
            $items = $this->checkoutSession->getQuote()->getAllVisibleItems();
        } catch (\Exception $e) {
            $this->logger->error(
                'ETechFlow_BackorderEtaDisplay: Hyva ETA — could not load cart items.',
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

            if (!$this->detector->isBackordered($product, (float) $item->getQty())) {
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
     * Tailwind class set for the configured badge style.
     *
     * Returns both light- and dark-mode classes. The `accent` value is a
     * text-colour utility (used on inline SVGs whose stroke="currentColor"),
     * not a background-colour utility.
     *
     * @return array{container:string, accent:string}
     */
    public function getStyleClasses(): array
    {
        $style = $this->config->getBadgeStyle();

        return match ($style) {
            'info' => [
                'container' => 'bg-blue-50 text-blue-900 border-blue-500 '
                    . 'dark:bg-blue-900/20 dark:text-blue-200 dark:border-blue-400',
                'accent'    => 'text-blue-600 dark:text-blue-300',
            ],
            'neutral' => [
                'container' => 'bg-slate-100 text-slate-800 border-slate-500 '
                    . 'dark:bg-slate-700/30 dark:text-slate-200 dark:border-slate-400',
                'accent'    => 'text-slate-600 dark:text-slate-300',
            ],
            default => [
                'container' => 'bg-amber-50 text-amber-900 border-amber-500 '
                    . 'dark:bg-amber-900/20 dark:text-amber-200 dark:border-amber-400',
                'accent'    => 'text-amber-600 dark:text-amber-300',
            ],
        };
    }
}
