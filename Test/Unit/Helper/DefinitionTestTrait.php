<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Helper;

use MageOS\AdvancedReport\Model\Definition\Dataset;
use MageOS\AdvancedReport\Model\Definition\Granularity;
use MageOS\AdvancedReport\Model\Definition\Preset;
use MageOS\DashboardChart\Model\Chart\Chart;
use MageOS\DashboardChart\Model\Chart\Section;

/**
 * Builds MageOS_AdvancedReport definitions and dashboard charts for tests.
 */
trait DefinitionTestTrait
{
    /**
     * @param string[] $metrics
     * @param array<int,array{code: string, granularity: Granularity|null}> $dimensions
     * @param array<int,array{metric: string, direction: string}> $sort
     * @param array<string,mixed>|null $chart
     */
    private static function preset(
        string $code,
        string $area = 'sales',
        string $dataset = 'sales_orders',
        array $metrics = ['orders', 'net_sales'],
        array $dimensions = [['code' => 'payment_method', 'granularity' => null]],
        array $sort = [],
        string $compareTo = 'none',
        ?string $dateRange = 'last_30_days',
        ?array $chart = null,
    ): Preset {
        return new Preset(
            $code,
            $area,
            'Label ' . $code,
            $dataset,
            $metrics,
            $dimensions,
            [],
            $sort,
            $compareTo,
            $dateRange,
            ['mysql'],
            description: 'Description ' . $code,
            chart: $chart,
        );
    }

    private static function dataset(string $code, string $acl = 'MageOS_AdvancedReport::sales', bool $dateless = false): Dataset
    {
        return new Dataset($code, $code, $acl, 'sales', $dateless, null, null, null, [], [], [], [], []);
    }

    /**
     * @param string[] $aclResources
     * @param string[] $metrics
     */
    private static function chart(
        string $id,
        array $aclResources = ['MageOS_DashboardChart::charts'],
        string $dataset = 'sales_orders',
        array $metrics = ['orders'],
        bool $dateless = false,
        ?int $limit = 10,
        string $type = Chart::TYPE_BAR,
    ): Chart {
        return new Chart(
            id: $id,
            preset: $id,
            dataset: $dataset,
            section: new Section('sales', 'Sales', 10, ['MageOS_DashboardChart::charts']),
            label: 'Label ' . $id,
            description: 'Description ' . $id,
            type: $type,
            size: 'half',
            metrics: $metrics,
            dimensions: [['code' => 'payment_method', 'granularity' => null]],
            sort: [['by' => $metrics[0], 'direction' => 'desc']],
            limit: $limit,
            dateRange: 'last_30_days',
            compareTo: 'none',
            dateless: $dateless,
            sortOrder: 10,
            aclResources: $aclResources,
        );
    }
}
