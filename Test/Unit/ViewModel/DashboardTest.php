<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\ViewModel;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use MageOS\AdvancedReport\Model\Config as AdvancedReportConfig;
use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Config;
use MageOS\DashboardChart\Model\Dashboard\LayoutResolver;
use MageOS\DashboardChart\Test\Unit\Helper\DefinitionTestTrait;
use MageOS\DashboardChart\ViewModel\Dashboard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dashboard::class)]
final class DashboardTest extends TestCase
{
    use DefinitionTestTrait;

    public function testItIsVisibleOnlyWhenBothModulesAreEnabledAndACharIsAllowed(): void
    {
        self::assertTrue($this->viewModel()->isVisible());
        self::assertFalse($this->viewModel(enabled: false)->isVisible());
        self::assertFalse($this->viewModel(reportsEnabled: false)->isVisible());
        self::assertFalse($this->viewModel(charts: false)->isVisible());
    }

    public function testItBuildsTheAppConfiguration(): void
    {
        $config = json_decode($this->viewModel()->getJsConfig(), true);

        self::assertIsArray($config);
        self::assertSame('orders', $config['charts'][0]['id']);
        self::assertSame('advancedreport/report/view?preset=orders', $config['charts'][0]['reportUrl']);
        self::assertSame([['id' => 'orders', 'opened' => true]], $config['layout']);
        self::assertFalse($config['personalized']);
        self::assertTrue($config['canPersonalize']);
        self::assertSame(3, $config['maxOpened']);
        self::assertSame('it_IT', $config['locale']);
        self::assertSame(['store' => 2], $config['scope']);
        self::assertSame('', $config['periods'][0]['value']);
        self::assertSame('year_to_date', $config['periods'][6]['value']);
        self::assertSame('dashboardchart/chart/data?', $config['urls']['data']);
    }

    public function testItEncodesAnEmptyScopeAsAnObject(): void
    {
        self::assertStringContainsString('"scope":{}', $this->viewModel(store: 0)->getJsConfig());
    }

    public function testItHidesTheReportLinkWithoutTheReportsRight(): void
    {
        $config = json_decode($this->viewModel(canOpenReports: false)->getJsConfig(), true);

        self::assertIsArray($config);
        self::assertNull($config['charts'][0]['reportUrl']);
    }

    private function viewModel(
        bool $enabled = true,
        bool $reportsEnabled = true,
        bool $charts = true,
        bool $canOpenReports = true,
        int $store = 2,
    ): Dashboard {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($enabled);
        $config->method('getMaxOpened')->willReturn(3);
        $reportsConfig = $this->createStub(AdvancedReportConfig::class);
        $reportsConfig->method('isEnabled')->willReturn($reportsEnabled);
        $access = $this->createStub(ChartAccess::class);
        $access->method('getAllowedCharts')->willReturn($charts ? ['orders' => self::chart('orders')] : []);
        $access->method('canPersonalize')->willReturn(true);
        $layout = $this->createStub(LayoutResolver::class);
        $layout->method('getLayout')->willReturn([['id' => 'orders', 'opened' => true]]);
        $layout->method('isPersonalized')->willReturn(false);
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn($canOpenReports);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $path, ?array $params = null): string => $path . '?' . http_build_query($params ?? [])
        );
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn (string $name): ?int => $name === 'store' ? $store : null);
        $locale = $this->createStub(ResolverInterface::class);
        $locale->method('getLocale')->willReturn('it_IT');

        return new Dashboard($config, $reportsConfig, $access, $layout, $authorization, $url, $request, $locale, new Json());
    }
}
