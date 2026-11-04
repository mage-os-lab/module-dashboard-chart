<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model\Chart;

use MageOS\AdvancedReport\Model\Definition\Dataset;
use MageOS\AdvancedReport\Model\Definition\DefinitionRegistry;
use MageOS\AdvancedReport\Model\Definition\Granularity;
use MageOS\AdvancedReport\Model\Definition\NoSuchDefinitionException;
use MageOS\AdvancedReport\Model\Definition\Preset;
use MageOS\DashboardChart\Model\Chart\Chart;
use MageOS\DashboardChart\Model\Chart\ChartRegistry;
use MageOS\DashboardChart\Model\Config\Data;
use MageOS\DashboardChart\Test\Unit\Helper\DefinitionTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChartRegistry::class)]
final class ChartRegistryTest extends TestCase
{
    use DefinitionTestTrait;

    public function testItGeneratesALineChartForADateDimensionPreset(): void
    {
        $chart = $this->registry([
            self::preset(
                'overview',
                dimensions: [['code' => 'order_date', 'granularity' => Granularity::DAY]],
                compareTo: 'previous_period',
            ),
        ])->getChart('overview');

        self::assertNotNull($chart);
        self::assertSame(Chart::TYPE_LINE, $chart->type);
        self::assertSame(['orders'], $chart->metrics);
        self::assertSame([['code' => 'order_date', 'granularity' => 'day']], $chart->dimensions);
        self::assertNull($chart->limit);
        self::assertSame('previous_period', $chart->compareTo);
        self::assertSame([], $chart->sort);
        self::assertSame('Label overview', $chart->label);
        self::assertSame('Description overview', $chart->description);
        self::assertSame('half', $chart->size);
        self::assertSame('last_30_days', $chart->dateRange);
    }

    public function testItGeneratesATopTenBarChartOnTheSortMetricForOtherPresets(): void
    {
        $chart = $this->registry([
            self::preset('by_payment', sort: [['metric' => 'net_sales', 'direction' => 'desc']], compareTo: 'previous_period'),
        ])->getChart('by_payment');

        self::assertNotNull($chart);
        self::assertSame(Chart::TYPE_HORIZONTAL_BAR, $chart->type);
        self::assertSame(['net_sales'], $chart->metrics);
        self::assertSame(10, $chart->limit);
        self::assertSame('none', $chart->compareTo, 'Only line charts draw a comparison.');
        self::assertSame([['by' => 'net_sales', 'direction' => 'desc']], $chart->sort);
    }

    public function testItKeepsTheNaturalOrderOfPresetsWithoutSort(): void
    {
        $chart = $this->registry([self::preset('by_hour', dimensions: [['code' => 'order_hour', 'granularity' => null]])])
            ->getChart('by_hour');

        self::assertNotNull($chart);
        self::assertSame([], $chart->sort);
        self::assertSame(['orders'], $chart->metrics);
    }

    public function testItAppliesTheXmlOverridesOfAGeneratedChart(): void
    {
        $chart = $this->registry(
            [self::preset('by_payment', sort: [['metric' => 'net_sales', 'direction' => 'desc']])],
            charts: ['by_payment' => [
                'type' => 'doughnut',
                'size' => 'full',
                'label' => 'Payments',
                'description' => 'Share by payment method.',
                'limit' => 5,
                'metrics' => ['orders', 'orders', 'aov'],
                'dimensions' => [['code' => 'store_id', 'granularity' => null]],
                'sortBy' => 'aov',
                'dateRange' => 'year_to_date',
                'compareTo' => 'previous_year',
                'sortOrder' => 3,
            ]],
        )->getChart('by_payment');

        self::assertNotNull($chart);
        self::assertSame('doughnut', $chart->type);
        self::assertSame('full', $chart->size);
        self::assertSame('Payments', $chart->label);
        self::assertSame('Share by payment method.', $chart->description);
        self::assertSame(5, $chart->limit);
        self::assertSame(['orders', 'aov'], $chart->metrics);
        self::assertSame([['code' => 'store_id', 'granularity' => null]], $chart->dimensions);
        self::assertSame([['by' => 'aov', 'direction' => 'desc']], $chart->sort);
        self::assertSame('year_to_date', $chart->dateRange);
        self::assertSame('previous_year', $chart->compareTo);
        self::assertSame(3, $chart->sortOrder);
    }

    public function testItTakesTypeMetricsDimensionAndSortFromThePresetChart(): void
    {
        $chart = $this->registry(
            [
                self::preset(
                    'recent_carts',
                    area: 'carts',
                    dataset: 'carts',
                    metrics: ['carts', 'cart_value', 'cart_items'],
                    dimensions: [['code' => 'customer_email', 'granularity' => null]],
                    sort: [['metric' => 'cart_items', 'direction' => 'desc']],
                    chart: [
                        'type' => 'bar',
                        'metrics' => ['cart_value', 'carts'],
                        'dimensions' => [['code' => 'activity_date', 'granularity' => 'day']],
                        'sortBy' => 'activity_date',
                        'sortDirection' => 'asc',
                    ],
                ),
            ],
            charts: ['recent_carts' => ['limit' => 366]],
        )->getChart('recent_carts');

        self::assertNotNull($chart);
        self::assertSame(Chart::TYPE_BAR, $chart->type);
        self::assertSame(['cart_value', 'carts'], $chart->metrics);
        self::assertSame([['code' => 'activity_date', 'granularity' => 'day']], $chart->dimensions);
        self::assertSame([['by' => 'activity_date', 'direction' => 'asc']], $chart->sort);
        self::assertSame(366, $chart->limit);
    }

    public function testItDropsThePresetSortOnMetricsTheChartDoesNotDraw(): void
    {
        $chart = $this->registry(
            [self::preset('by_payment', sort: [['metric' => 'net_sales', 'direction' => 'desc']])],
            charts: ['by_payment' => ['metrics' => ['orders']]],
        )->getChart('by_payment');

        self::assertNotNull($chart);
        self::assertSame([], $chart->sort);
    }

    public function testItAddsAnotherChartOnAnExistingPresetAndRemovesDisabledOnes(): void
    {
        $registry = $this->registry(
            [self::preset('by_payment'), self::preset('dead_stock', area: 'catalog')],
            charts: [
                'by_payment_share' => ['preset' => 'by_payment', 'type' => 'pie'],
                'dead_stock' => ['disabled' => true],
            ],
        );

        self::assertSame(['by_payment', 'by_payment_share'], array_keys($registry->getCharts()));
        self::assertSame('by_payment', $registry->getChart('by_payment_share')?->preset);
        self::assertSame('pie', $registry->getChart('by_payment_share')->type);
        self::assertNull($registry->getChart('dead_stock'));
    }

    public function testItSkipsChartsOnUnknownPresetsAndPresetsWithoutMetrics(): void
    {
        $registry = $this->registry(
            [self::preset('empty', metrics: [])],
            charts: ['ghost' => ['preset' => 'missing']],
        );

        self::assertSame([], $registry->getCharts());
    }

    public function testItSortsChartsBySectionThenChartSortOrder(): void
    {
        $registry = $this->registry(
            [
                self::preset('stock', area: 'catalog'),
                self::preset('orders_b'),
                self::preset('orders_a'),
                self::preset('custom_area', area: 'marketing'),
            ],
            sections: [
                'sales' => ['label' => 'Sales', 'sortOrder' => 10],
                'catalog' => ['label' => 'Catalog', 'sortOrder' => 20],
            ],
            charts: ['orders_a' => ['sortOrder' => 1]],
        );

        self::assertSame(['orders_a', 'orders_b', 'stock', 'custom_area'], array_keys($registry->getCharts()));
        self::assertSame('Marketing', $registry->getChart('custom_area')?->section->label);
    }

    public function testItSkipsChartsOfDisabledSections(): void
    {
        $registry = $this->registry([self::preset('stock', area: 'catalog')], sections: ['catalog' => ['disabled' => true]]);

        self::assertSame([], $registry->getCharts());
    }

    public function testItInheritsTheSectionAndRootAclByDefault(): void
    {
        $chart = $this->registry(
            [self::preset('orders')],
            sections: ['sales' => ['acl' => ['resource' => 'Vendor_Module::sales', 'inherit' => true]]],
            charts: ['orders' => ['acl' => ['resource' => 'Vendor_Module::orders', 'inherit' => true]]],
        )->getChart('orders');

        self::assertSame(
            [ChartRegistry::ROOT_RESOURCE, 'Vendor_Module::sales', 'Vendor_Module::orders'],
            $chart?->aclResources
        );
    }

    public function testItStopsTheAclInheritanceWhereInheritIsFalse(): void
    {
        $registry = $this->registry(
            [self::preset('orders'), self::preset('stock', area: 'catalog')],
            sections: [
                'sales' => ['acl' => ['resource' => 'Vendor_Module::sales', 'inherit' => true]],
                'catalog' => ['acl' => ['resource' => 'Vendor_Module::catalog', 'inherit' => false]],
            ],
            charts: ['orders' => ['acl' => ['resource' => 'Vendor_Module::orders', 'inherit' => false]]],
        );

        self::assertSame(['Vendor_Module::orders'], $registry->getChart('orders')?->aclResources);
        self::assertSame(['Vendor_Module::catalog'], $registry->getChart('stock')?->aclResources);
    }

    public function testItFlagsChartsOfDatelessDatasets(): void
    {
        $chart = $this->registry([self::preset('stock', area: 'catalog', dataset: 'catalog_stock')])->getChart('stock');

        self::assertTrue($chart?->dateless);
        self::assertSame('catalog_stock', $chart->dataset);
    }

    public function testItBuildsTheChartsOnce(): void
    {
        $definitions = $this->createMock(DefinitionRegistry::class);
        $definitions->expects(self::once())->method('getPresets')->willReturn([]);
        $registry = new ChartRegistry($this->configData([], []), $definitions);

        $registry->getCharts();
        $registry->getCharts();
    }

    /**
     * @param Preset[] $presets
     * @param array<string,array<string,mixed>> $sections
     * @param array<string,array<string,mixed>> $charts
     */
    private function registry(array $presets, array $sections = [], array $charts = []): ChartRegistry
    {
        $byCode = [];
        foreach ($presets as $preset) {
            $byCode[$preset->code] = $preset;
        }
        $definitions = $this->createStub(DefinitionRegistry::class);
        $definitions->method('getPresets')->willReturn($byCode);
        $definitions->method('getPreset')->willReturnCallback(
            static fn (string $code): Preset => $byCode[$code] ?? throw new NoSuchDefinitionException(__('No such preset'))
        );
        $definitions->method('getDataset')->willReturnCallback(
            static fn (string $code): Dataset => self::dataset($code, dateless: $code === 'catalog_stock')
        );

        return new ChartRegistry($this->configData($sections, $charts), $definitions);
    }

    /**
     * @param array<string,array<string,mixed>> $sections
     * @param array<string,array<string,mixed>> $charts
     */
    private function configData(array $sections, array $charts): Data
    {
        $data = $this->createStub(Data::class);
        $data->method('get')->willReturnCallback(
            static fn (string $path): array => match ($path) {
                'sections' => $sections,
                'charts' => $charts,
                default => [],
            }
        );

        return $data;
    }
}
