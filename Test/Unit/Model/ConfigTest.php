<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use MageOS\DashboardChart\Model\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    public function testItReadsTheSwitchAndTheMaximumOpenedCharts(): void
    {
        $config = $this->config([Config::XML_PATH_ENABLED => '1', Config::XML_PATH_MAX_OPENED => '4']);

        self::assertTrue($config->isEnabled());
        self::assertSame(4, $config->getMaxOpened());
        self::assertSame(0, $this->config([Config::XML_PATH_MAX_OPENED => '-2'])->getMaxOpened());
    }

    public function testItReadsTheDefaultRowsInConfigurationOrder(): void
    {
        $rows = $this->config([Config::XML_PATH_DEFAULTS => json_encode([
            '_1' => ['role' => '*', 'chart' => 'sales_overview', 'opened' => '1'],
            '_2' => ['role' => '3', 'chart' => 'dead_stock', 'opened' => '0'],
            '_3' => ['chart' => 'never_sold'],
            '_4' => ['role' => '3', 'chart' => ''],
            '_5' => 'broken',
        ])])->getDefaultRows();

        self::assertSame([
            ['role' => '*', 'chart' => 'sales_overview', 'opened' => true],
            ['role' => '3', 'chart' => 'dead_stock', 'opened' => false],
            ['role' => '*', 'chart' => 'never_sold', 'opened' => true],
        ], $rows);
    }

    public function testItReturnsNoRowsForAnEmptyOrMalformedValue(): void
    {
        self::assertSame([], $this->config([])->getDefaultRows());
        self::assertSame([], $this->config([Config::XML_PATH_DEFAULTS => '{oops'])->getDefaultRows());
        self::assertSame([], $this->config([Config::XML_PATH_DEFAULTS => '"text"'])->getDefaultRows());
    }

    /**
     * @param array<string,string|false> $values
     */
    private function config(array $values): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(static fn (string $path): mixed => $values[$path] ?? null);
        $scopeConfig->method('isSetFlag')->willReturnCallback(static fn (string $path): bool => !empty($values[$path]));

        return new Config($scopeConfig, new Json());
    }
}
