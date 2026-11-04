<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model\Chart;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Group;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use MageOS\AdvancedReport\Api\Data\ReportRequestInterface;
use MageOS\AdvancedReport\Model\Definition\DefinitionRegistry;
use MageOS\AdvancedReport\Model\Definition\Preset;
use MageOS\AdvancedReport\Model\Query\PresetRequestBuilder;
use MageOS\DashboardChart\Model\Chart\RequestBuilder;
use MageOS\DashboardChart\Test\Unit\Helper\DefinitionTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestBuilder::class)]
final class RequestBuilderTest extends TestCase
{
    use DefinitionTestTrait;

    /** @var array<string,mixed>|null */
    private ?array $overrides = null;

    public function testItOverridesThePresetWithTheChartDefinition(): void
    {
        $this->builder()->build(self::chart('by_payment', metrics: ['net_sales'], limit: 10));

        self::assertSame([
            'metrics' => ['net_sales'],
            'dimensions' => [['code' => 'payment_method', 'granularity' => null]],
            'sort' => [['by' => 'net_sales', 'direction' => 'desc']],
            'compareTo' => 'none',
            'limit' => 10,
            'offset' => 0,
            'storeIds' => [],
            'dateRange' => ['type' => 'last_30_days'],
        ], $this->overrides);
    }

    public function testItUsesADashboardPeriodInsteadOfTheChartOne(): void
    {
        $this->builder()->build(self::chart('by_payment'), 'year_to_date');

        self::assertSame(['type' => 'year_to_date'], $this->overrides['dateRange'] ?? null);
    }

    public function testItUsesARequestedComparisonInsteadOfTheChartOne(): void
    {
        $this->builder()->build(self::chart('by_payment'), null, [], 'previous_year');

        self::assertSame('previous_year', $this->overrides['compareTo'] ?? null);
    }

    #[DataProvider('invalidPeriods')]
    public function testItIgnoresAnInvalidOrCustomPeriod(?string $period): void
    {
        $this->builder()->build(self::chart('by_payment'), $period);

        self::assertSame(['type' => 'last_30_days'], $this->overrides['dateRange'] ?? null);
    }

    /**
     * @return array<string,array{string|null}>
     */
    public static function invalidPeriods(): array
    {
        return ['none' => [null], 'unknown' => ['forever'], 'custom' => ['custom']];
    }

    public function testItSendsNoPeriodForDatelessDatasets(): void
    {
        $this->builder()->build(self::chart('stock', dataset: 'catalog_stock', dateless: true), 'today');

        self::assertArrayNotHasKey('dateRange', $this->overrides ?? []);
    }

    /**
     * @param array{store?: int, group?: int, website?: int} $scope
     * @param int[] $expected
     */
    #[DataProvider('scopes')]
    public function testItResolvesTheDashboardStoreScope(array $scope, array $expected): void
    {
        $this->builder()->build(self::chart('by_payment'), null, $scope);

        self::assertSame($expected, $this->overrides['storeIds'] ?? null);
    }

    /**
     * @return array<string,array{array<string,int>, int[]}>
     */
    public static function scopes(): array
    {
        return [
            'all stores' => [[], []],
            'store' => [['store' => 3], [3]],
            'group' => [['group' => 2], [4, 5]],
            'website' => [['website' => 1], [1, 4, 5]],
            'store wins' => [['store' => 3, 'website' => 1], [3]],
            'unknown store' => [['store' => 99], []],
        ];
    }

    private function builder(): RequestBuilder
    {
        $presetBuilder = $this->createStub(PresetRequestBuilder::class);
        $presetBuilder->method('build')->willReturnCallback(
            function (Preset $preset, ?array $overrides): ReportRequestInterface {
                $this->overrides = $overrides;

                return $this->createStub(ReportRequestInterface::class);
            }
        );
        $definitions = $this->createStub(DefinitionRegistry::class);
        $definitions->method('getPreset')->willReturnCallback(static fn (string $code): Preset => self::preset($code));

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn('3');
        $group = $this->createStub(Group::class);
        $group->method('getStoreIds')->willReturn(['4', '5']);
        $website = $this->createStub(Website::class);
        $website->method('getStoreIds')->willReturn(['1', '4', '5']);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(
            static fn (int $id): StoreInterface => $id === 3 ? $store : throw new NoSuchEntityException(__('No store'))
        );
        $storeManager->method('getGroup')->willReturn($group);
        $storeManager->method('getWebsite')->willReturn($website);

        return new RequestBuilder($presetBuilder, $definitions, $storeManager);
    }
}
