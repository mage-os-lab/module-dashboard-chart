<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use MageOS\DashboardChart\Model\Config;
use MageOS\DashboardChart\Model\Config\Backend\DashboardCharts;
use MageOS\DashboardChart\Model\Config\Source\Roles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DashboardCharts::class)]
final class DashboardChartsTest extends TestCase
{
    public function testItSavesRowsWithinTheMaximumPerRole(): void
    {
        $backend = $this->backend([
            '_1' => ['role' => '*', 'chart' => 'sales_overview', 'opened' => '1'],
            '_2' => ['role' => '*', 'chart' => 'dead_stock', 'opened' => '1'],
            '_3' => ['role' => '3', 'chart' => 'never_sold', 'opened' => '1'],
            '_4' => ['role' => '3', 'chart' => 'dead_stock', 'opened' => '1'],
            '_5' => ['role' => '5', 'chart' => 'stock_value', 'opened' => '0'],
        ], '3');

        $backend->beforeSave();

        self::assertIsString($backend->getValue(), 'The rows are serialized.');
    }

    public function testItRefusesARoleOpeningTooManyChartsCountingTheAllRolesRows(): void
    {
        $backend = $this->backend([
            '_1' => ['role' => '*', 'chart' => 'sales_overview', 'opened' => '1'],
            '_2' => ['role' => '*', 'chart' => 'dead_stock', 'opened' => '1'],
            '_3' => ['role' => '3', 'chart' => 'never_sold', 'opened' => '1'],
        ], '2');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('3 charts are opened at dashboard load for "Catalog managers", the maximum is 2.');

        $backend->beforeSave();
    }

    public function testItRefusesTooManyChartsForAllRoles(): void
    {
        $backend = $this->backend([
            '_1' => ['role' => '*', 'chart' => 'sales_overview', 'opened' => '1'],
            '_2' => ['role' => '*', 'chart' => 'dead_stock', 'opened' => '1'],
        ], '1');

        $this->expectExceptionMessage('2 charts are opened at dashboard load for "All Roles", the maximum is 1.');

        $backend->beforeSave();
    }

    public function testItUsesTheSavedMaximumWhenTheFieldIsNotPosted(): void
    {
        $backend = $this->backend([
            '_1' => ['role' => '*', 'chart' => 'sales_overview', 'opened' => '1'],
            '_2' => ['role' => '*', 'chart' => 'dead_stock', 'opened' => '1'],
        ], null, '1');

        $this->expectException(LocalizedException::class);

        $backend->beforeSave();
    }

    /**
     * @param array<string,array<string,string>> $rows
     */
    private function backend(array $rows, ?string $postedMax, string $savedMax = '3'): DashboardCharts
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => $path === Config::XML_PATH_MAX_OPENED ? $savedMax : null
        );
        $roles = $this->createStub(Roles::class);
        $roles->method('toOptionArray')->willReturn([
            ['value' => '*', 'label' => 'All Roles'],
            ['value' => '3', 'label' => 'Catalog managers'],
        ]);

        // beforeSave() dispatches the model events.
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $backend = new DashboardCharts(
            $context,
            $this->createStub(Registry::class),
            $scopeConfig,
            $this->createStub(TypeListInterface::class),
            $roles,
            serializer: new Json(),
        );
        $backend->setValue($rows);
        $backend->setData(
            'fieldset_data',
            $postedMax === null ? [] : ['advanced_report_charts_max_opened' => $postedMax]
        );

        return $backend;
    }
}
