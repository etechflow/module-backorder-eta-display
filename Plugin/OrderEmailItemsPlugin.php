<?php

declare(strict_types=1);

namespace ETechFlow\BackorderEtaDisplay\Plugin;

use ETechFlow\BackorderEtaDisplay\Model\BackorderDetector;
use ETechFlow\BackorderEtaDisplay\Model\Config;
use ETechFlow\BackorderEtaDisplay\Model\EtaResolver;
use ETechFlow\BackorderEtaDisplay\Model\Performance\Profiler;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Escaper;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Block\Order\Email\Items;
use Psr\Log\LoggerInterface;

/**
 * Appends a backorder ETA summary block to the order confirmation email.
 *
 * Activates only when:
 *  - Module is enabled
 *  - "Show in Order Confirmation Email" admin toggle is on
 *  - The order contains at least one backorder item with a resolvable ETA
 *
 * Otherwise the email renders unchanged.
 */
class OrderEmailItemsPlugin
{
    /**
     * Constructor.
     *
     * @param Config                   $config
     * @param BackorderDetector        $detector
     * @param EtaResolver              $etaResolver
     * @param ProductCollectionFactory $productCollectionFactory
     * @param Escaper                  $escaper
     * @param LoggerInterface          $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly BackorderDetector $detector,
        private readonly EtaResolver $etaResolver,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Append the ETA summary HTML after the rendered order items.
     *
     * Magento's `Items::toHtml()` is declared to return string, so we cast
     * defensively before returning to satisfy our own `: string` signature
     * if a third-party plugin upstream returned something unusual.
     *
     * @param Items $subject
     * @param mixed $result
     * @return string
     */
    public function afterToHtml(Items $subject, $result): string
    {
        $resultString = is_string($result) ? $result : (string) $result;

        if (!$this->config->isEnabled() || !$this->config->isShowInOrderEmail()) {
            return $resultString;
        }

        $span = Profiler::start('ETechFlow_BED_OrderEmail');
        try {
            /** @var OrderInterface|null $order */
            $order = $subject->getOrder();
            if (!$order) {
                return $resultString;
            }

            $entries = $this->collectBackorderEntries($order);
            if (empty($entries)) {
                return $resultString;
            }

            return $resultString . $this->renderHtml($entries);
        } catch (\Exception $e) {
            $this->logger->error(
                'ETechFlow_BackorderEtaDisplay: Order email plugin failed.',
                ['exception' => $e->getMessage()]
            );
            return $resultString;
        } finally {
            Profiler::stop($span);
        }
    }

    /**
     * Collect backorder line items for the email.
     *
     * All products are bulk-loaded in a single collection query instead of
     * one repository call per line — significant TTFB win when the order has
     * many items, and avoids stalling the email queue on large orders.
     *
     * @param OrderInterface $order
     * @return array<int, array{name: string, eta: string}>
     */
    private function collectBackorderEntries(OrderInterface $order): array
    {
        $items = $order->getAllVisibleItems();
        if (empty($items)) {
            return [];
        }

        $productIds = [];
        foreach ($items as $item) {
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

        $entries = [];

        foreach ($items as $item) {
            $product = $products[(int) $item->getProductId()] ?? null;
            if (!$product) {
                continue;
            }

            if (!$this->detector->isBackordered($product, (float) $item->getQtyOrdered())) {
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
     * Bulk-load products by ID, returning them keyed by id for O(1) lookup.
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
     * Render the email-friendly HTML block. Inline styles only — email clients
     * strip <style> tags and don't apply external stylesheets.
     *
     * @param array<int, array{name: string, eta: string}> $entries
     * @return string
     */
    private function renderHtml(array $entries): string
    {
        $colours = $this->emailColours();

        $rows = '';
        foreach ($entries as $entry) {
            $rows .= sprintf(
                '<tr><td style="padding:6px 0;color:%s;">%s</td><td style="padding:6px 0;color:%s;text-align:right;font-weight:600;">%s</td></tr>',
                $colours['text'],
                $this->escaper->escapeHtml($entry['name']),
                $colours['text'],
                $this->escaper->escapeHtml($entry['eta'])
            );
        }

        return sprintf(
            '<table cellspacing="0" cellpadding="0" border="0" width="100%%" style="margin:16px 0 0;background-color:%s;border-left:4px solid %s;border-radius:4px;">'
            . '<tr><td style="padding:14px 18px;">'
            . '<strong style="display:block;font-size:14px;color:%s;margin-bottom:8px;">%s</strong>'
            . '<table cellspacing="0" cellpadding="0" border="0" width="100%%" style="font-size:13px;">%s</table>'
            . '</td></tr></table>',
            $colours['bg'],
            $colours['accent'],
            $colours['text'],
            $this->escaper->escapeHtml(__('Estimated delivery times')),
            $rows
        );
    }

    /**
     * Inline colour palette matching the configured badge style.
     *
     * @return array{bg:string, accent:string, text:string}
     */
    private function emailColours(): array
    {
        $style = $this->config->getBadgeStyle();

        return match ($style) {
            'info'    => ['bg' => '#e3f2fd', 'accent' => '#1976d2', 'text' => '#0d47a1'],
            'neutral' => ['bg' => '#f1f5f9', 'accent' => '#64748b', 'text' => '#334155'],
            default   => ['bg' => '#fff8e1', 'accent' => '#f59e0b', 'text' => '#5d4037'],
        };
    }
}
