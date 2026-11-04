<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model\Chart;

use Magento\Framework\AuthorizationInterface;
use MageOS\AdvancedReport\Model\Definition\Dataset;
use MageOS\AdvancedReport\Model\Definition\DefinitionRegistry;
use MageOS\AdvancedReport\Model\Security\DatasetAccess;
use MageOS\DashboardChart\Model\Chart\Chart;
use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Chart\ChartRegistry;
use MageOS\DashboardChart\Test\Unit\Helper\DefinitionTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChartAccess::class)]
final class ChartAccessTest extends TestCase
{
    use DefinitionTestTrait;

    public function testItRequiresEveryChartResourceAndTheDatasetAcl(): void
    {
        $access = $this->access(
            allowed: ['MageOS_DashboardChart::charts', 'MageOS_DashboardChart::catalog'],
            allowedDatasets: ['catalog_stock'],
        );

        self::assertTrue($access->isAllowed(
            self::chart('stock', ['MageOS_DashboardChart::charts', 'MageOS_DashboardChart::catalog'], 'catalog_stock')
        ));
        self::assertFalse($access->isAllowed(
            self::chart('orders', ['MageOS_DashboardChart::charts', 'MageOS_DashboardChart::sales'], 'catalog_stock')
        ), 'A missing chart resource denies the chart.');
        self::assertFalse($access->isAllowed(
            self::chart('top_products', ['MageOS_DashboardChart::charts', 'MageOS_DashboardChart::catalog'], 'sales_items')
        ), 'A denied dataset denies the chart even when every chart resource is allowed.');
    }

    public function testItListsTheAllowedChartsInRegistryOrder(): void
    {
        $charts = [
            'stock' => self::chart('stock', ['MageOS_DashboardChart::charts'], 'catalog_stock'),
            'orders' => self::chart('orders', ['MageOS_DashboardChart::charts'], 'sales_orders'),
            'dead' => self::chart('dead', ['MageOS_DashboardChart::charts'], 'catalog_stock'),
        ];

        $allowed = $this->access(['MageOS_DashboardChart::charts'], ['catalog_stock'], $charts)->getAllowedCharts();

        self::assertSame(['stock', 'dead'], array_keys($allowed));
    }

    public function testItAllowsPersonalizationThroughItsOwnResource(): void
    {
        self::assertTrue($this->access([ChartAccess::PERSONALIZE_RESOURCE], [])->canPersonalize());
        self::assertFalse($this->access(['MageOS_DashboardChart::charts'], [])->canPersonalize());
    }

    /**
     * @param string[] $allowed
     * @param string[] $allowedDatasets
     * @param array<string, Chart> $charts
     */
    private function access(array $allowed, array $allowedDatasets, array $charts = []): ChartAccess
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(
            static fn (string $resource): bool => \in_array($resource, $allowed, true)
        );
        $datasetAccess = $this->createStub(DatasetAccess::class);
        $datasetAccess->method('isAllowed')->willReturnCallback(
            static fn (Dataset $dataset): bool => \in_array($dataset->code, $allowedDatasets, true)
        );
        $definitions = $this->createStub(DefinitionRegistry::class);
        $definitions->method('getDataset')->willReturnCallback(static fn (string $code): Dataset => self::dataset($code));
        $registry = $this->createStub(ChartRegistry::class);
        $registry->method('getCharts')->willReturn($charts);

        return new ChartAccess($authorization, $datasetAccess, $definitions, $registry);
    }
}
