<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model\Config\Source;

use MageOS\DashboardChart\Model\Chart\ChartRegistry;
use MageOS\DashboardChart\Model\Config\Source\Charts;
use MageOS\DashboardChart\Test\Unit\Helper\DefinitionTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Charts::class)]
final class ChartsTest extends TestCase
{
    use DefinitionTestTrait;

    public function testItGroupsEveryChartBySection(): void
    {
        $registry = $this->createStub(ChartRegistry::class);
        $registry->method('getCharts')->willReturn([
            'orders' => self::chart('orders'),
            'payments' => self::chart('payments'),
        ]);

        self::assertSame([[
            'label' => 'Sales',
            'value' => [
                ['value' => 'orders', 'label' => 'Label orders'],
                ['value' => 'payments', 'label' => 'Label payments'],
            ],
        ]], (new Charts($registry))->toOptionArray());
    }
}
