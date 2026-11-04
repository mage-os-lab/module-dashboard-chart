<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model\Dashboard;

use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Config;
use MageOS\DashboardChart\Model\Dashboard\LayoutResolver;
use MageOS\DashboardChart\Model\Dashboard\UserPreferences;
use MageOS\DashboardChart\Test\Unit\Helper\AdminSessionTestTrait;
use MageOS\DashboardChart\Test\Unit\Helper\DefinitionTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LayoutResolver::class)]
final class LayoutResolverTest extends TestCase
{
    use AdminSessionTestTrait;
    use DefinitionTestTrait;

    private const array ROWS = [
        ['role' => '3', 'chart' => 'dead_stock', 'opened' => true],
        ['role' => '*', 'chart' => 'sales_overview', 'opened' => true],
        ['role' => '*', 'chart' => 'dead_stock', 'opened' => false],
        ['role' => '9', 'chart' => 'never_sold', 'opened' => true],
        ['role' => '*', 'chart' => 'customer_value', 'opened' => true],
        ['role' => '*', 'chart' => 'stock_value', 'opened' => true],
    ];

    public function testItUsesTheRowsOfTheAdminRoleAndOfAllRolesFirstRowWinning(): void
    {
        $layout = $this->resolver(roleId: 3)->getLayout();

        self::assertSame([
            ['id' => 'dead_stock', 'opened' => true],
            ['id' => 'sales_overview', 'opened' => true],
            ['id' => 'customer_value', 'opened' => true],
            ['id' => 'stock_value', 'opened' => false],
        ], $layout, 'never_sold belongs to another role; only the first 3 charts stay opened.');
    }

    public function testItDropsTheChartsTheAdminMayNotSee(): void
    {
        $layout = $this->resolver(roleId: 3, allowed: ['sales_overview', 'stock_value'])->getDefaultLayout();

        self::assertSame(
            [['id' => 'sales_overview', 'opened' => true], ['id' => 'stock_value', 'opened' => true]],
            $layout
        );
    }

    public function testItPrefersTheSavedLayoutWhenTheAdminMayPersonalize(): void
    {
        $saved = [['id' => 'never_sold', 'opened' => true], ['id' => 'forbidden', 'opened' => true]];

        $resolver = $this->resolver(roleId: 3, saved: $saved, allowed: ['never_sold', 'sales_overview']);

        self::assertSame([['id' => 'never_sold', 'opened' => true]], $resolver->getLayout());
        self::assertTrue($resolver->isPersonalized());
    }

    public function testItIgnoresTheSavedLayoutWithoutThePersonalizeRight(): void
    {
        $resolver = $this->resolver(roleId: 3, saved: [['id' => 'never_sold', 'opened' => true]], canPersonalize: false);

        self::assertSame('dead_stock', $resolver->getLayout()[0]['id']);
        self::assertFalse($resolver->isPersonalized());
    }

    public function testItCapsTheOpenedChartsInLayoutOrder(): void
    {
        $layout = [
            ['id' => 'a', 'opened' => false],
            ['id' => 'b', 'opened' => true],
            ['id' => 'c', 'opened' => true],
            ['id' => 'd', 'opened' => true],
        ];

        self::assertSame(
            [false, true, true, false],
            array_column($this->resolver(maxOpened: 2)->capOpened($layout), 'opened')
        );
        self::assertSame(
            [false, false, false, false],
            array_column($this->resolver(maxOpened: 0)->capOpened($layout), 'opened')
        );
    }

    /**
     * @param array<int,array{id: string, opened: bool}>|null $saved
     * @param string[]|null $allowed
     */
    private function resolver(
        ?int $roleId = null,
        ?array $saved = null,
        ?array $allowed = null,
        bool $canPersonalize = true,
        int $maxOpened = 3,
    ): LayoutResolver {
        $config = $this->createStub(Config::class);
        $config->method('getDefaultRows')->willReturn(self::ROWS);
        $config->method('getMaxOpened')->willReturn($maxOpened);
        $preferences = $this->createStub(UserPreferences::class);
        $preferences->method('get')->willReturn($saved);
        $charts = [];
        foreach ($allowed ?? ['dead_stock', 'sales_overview', 'never_sold', 'customer_value', 'stock_value'] as $id) {
            $charts[$id] = self::chart($id);
        }
        $access = $this->createStub(ChartAccess::class);
        $access->method('getAllowedCharts')->willReturn($charts);
        $access->method('canPersonalize')->willReturn($canPersonalize);

        return new LayoutResolver($config, $preferences, $access, self::session(self::user(1, null, $roleId)));
    }
}
