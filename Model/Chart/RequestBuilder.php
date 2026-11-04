<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Chart;

use Magento\Store\Model\Group;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use MageOS\AdvancedReport\Api\Data\ReportRequestInterface;
use MageOS\AdvancedReport\Model\Definition\DefinitionRegistry;
use MageOS\AdvancedReport\Model\Query\DateRangeType;
use MageOS\AdvancedReport\Model\Query\InvalidRequestException;
use MageOS\AdvancedReport\Model\Query\PresetRequestBuilder;

/**
 * Builds the Advanced Report request of a chart: the preset defaults with the chart metrics, dimensions, sort,
 * limit, period and comparison, scoped to the dashboard store switcher selection.
 */
class RequestBuilder
{
    public function __construct(
        private readonly PresetRequestBuilder $presetRequestBuilder,
        private readonly DefinitionRegistry $definitionRegistry,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * @param Chart $chart
     * @param string|null $period A date range type overriding the chart one (ignored for dateless datasets).
     * @param array{store?: int, group?: int, website?: int} $scope Dashboard store switcher parameters.
     * @param string|null $compareTo A comparison mode overriding the chart one.
     * @throws InvalidRequestException
     */
    public function build(Chart $chart, ?string $period = null, array $scope = [], ?string $compareTo = null): ReportRequestInterface
    {
        $overrides = [
            'metrics' => $chart->metrics,
            'dimensions' => $chart->dimensions,
            'sort' => $chart->sort,
            'compareTo' => $compareTo ?? $chart->compareTo,
            'limit' => $chart->limit,
            'offset' => 0,
            'storeIds' => $this->resolveStoreIds($scope),
        ];
        if (!$chart->dateless) {
            $range = $this->isPeriod($period) ? $period : $chart->dateRange;
            if ($range !== null) {
                $overrides['dateRange'] = ['type' => $range];
            }
        }

        return $this->presetRequestBuilder->build($this->definitionRegistry->getPreset($chart->preset), $overrides);
    }

    private function isPeriod(?string $period): bool
    {
        return $period !== null && $period !== DateRangeType::CUSTOM->value && DateRangeType::tryFrom($period) !== null;
    }

    /**
     * @param array{store?: int, group?: int, website?: int} $scope
     * @return int[] Empty for all stores.
     */
    private function resolveStoreIds(array $scope): array
    {
        try {
            if (!empty($scope['store'])) {
                return [(int) $this->storeManager->getStore($scope['store'])->getId()];
            }
            if (!empty($scope['group'])) {
                $group = $this->storeManager->getGroup((string) $scope['group']);

                return $group instanceof Group ? array_map(intval(...), $group->getStoreIds()) : [];
            }
            if (!empty($scope['website'])) {
                $website = $this->storeManager->getWebsite($scope['website']);

                return $website instanceof Website ? array_map(intval(...), $website->getStoreIds()) : [];
            }
        } catch (\Exception) {
            return [];
        }

        return [];
    }
}
