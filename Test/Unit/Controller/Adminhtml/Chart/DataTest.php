<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Controller\Adminhtml\Chart;

use Magento\Framework\Exception\AuthorizationException;
use MageOS\AdvancedReport\Api\Data\ReportRequestInterface;
use MageOS\AdvancedReport\Api\Data\ReportResultInterface;
use MageOS\AdvancedReport\Api\ReportRunnerInterface;
use MageOS\AdvancedReport\Model\Config as AdvancedReportConfig;
use MageOS\AdvancedReport\Model\DataSource\QueryTimeoutException;
use MageOS\AdvancedReport\Model\Query\InvalidRequestException;
use MageOS\AdvancedReport\Model\Security\ErrorLogger;
use MageOS\DashboardChart\Controller\Adminhtml\Chart\Data;
use MageOS\DashboardChart\Model\Chart\Chart;
use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Chart\ChartRegistry;
use MageOS\DashboardChart\Model\Chart\RequestBuilder;
use MageOS\DashboardChart\Test\Unit\Helper\ControllerTestTrait;
use MageOS\DashboardChart\Test\Unit\Helper\DefinitionTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Data::class)]
final class DataTest extends TestCase
{
    use ControllerTestTrait;
    use DefinitionTestTrait;

    /** @var array<int,mixed> */
    private array $buildArguments = [];

    /** @var array<int,string|null> */
    private array $compareModes = [];

    public function testItRunsTheReportOfTheChartWithThePeriodAndScope(): void
    {
        $this->controller(['chart' => 'orders', 'period' => 'today', 'store' => '2'])->execute();

        self::assertSame(200, $this->resultCode);
        self::assertSame(['rows' => []], $this->resultData);
        self::assertSame('orders', $this->buildArguments[0]->id);
        self::assertSame('today', $this->buildArguments[1]);
        self::assertSame(['store' => 2, 'group' => 0, 'website' => 0], $this->buildArguments[2]);
    }

    public function testItSendsNoPeriodWhenTheChartDefaultIsKept(): void
    {
        $this->controller(['chart' => 'orders', 'period' => ''])->execute();

        self::assertNull($this->buildArguments[1]);
    }

    public function testItRunsOnlyTheChartComparisonForOtherCharts(): void
    {
        $this->controller(['chart' => 'orders'])->execute();

        self::assertSame([null], $this->compareModes);
    }

    public function testItAddsThePreviousYearToLineCharts(): void
    {
        $comparison = ['orders' => ['value' => 3.0, 'delta' => 1.0, 'delta_pct' => 0.5]];
        $row = [
            'dims' => [],
            'dims_raw' => ['order_date' => '2026-11-01'],
            'metrics' => ['orders' => 4],
            'comparison' => $comparison,
        ];

        $this->controller(['chart' => 'trend'], rows: [$row])->execute();

        self::assertSame(['previous_period', 'previous_year'], $this->compareModes);
        self::assertSame($comparison, $this->resultData['rows'][0]['comparison_year']);
    }

    public function testItAnswers404ForAnUnknownChartOrADisabledReportModule(): void
    {
        $this->controller(['chart' => 'missing'])->execute();
        self::assertSame(404, $this->resultCode);

        $this->resultCode = 200;
        $this->controller(['chart' => 'orders'], reportsEnabled: false)->execute();
        self::assertSame(404, $this->resultCode);
    }

    public function testItAnswers403WhenTheAdminMayNotSeeTheChart(): void
    {
        $this->controller(['chart' => 'orders'], allowed: false)->execute();

        self::assertSame(403, $this->resultCode);
        self::assertTrue($this->resultData['error']);
    }

    #[DataProvider('failures')]
    public function testItMapsReportFailuresToHttpCodes(\Throwable $failure, int $code, string $message): void
    {
        $logger = $this->createMock(ErrorLogger::class);
        $logger->expects($code === 500 ? self::once() : self::never())->method('log');

        $this->controller(['chart' => 'orders'], failure: $failure, logger: $logger)->execute();

        self::assertSame($code, $this->resultCode);
        self::assertSame($message, $this->resultData['message']);
    }

    /**
     * @return array<string,array{\Throwable, int, string}>
     */
    public static function failures(): array
    {
        return [
            'invalid' => [new InvalidRequestException(__('Bad request.')), 400, 'Bad request.'],
            'acl' => [new AuthorizationException(__('Denied.')), 403, 'Denied.'],
            'timeout' => [new QueryTimeoutException(__('Too slow.')), 504, 'Too slow.'],
            'other' => [new \RuntimeException('SQL details'), 500, 'An error occurred while loading the chart.'],
        ];
    }

    /**
     * @param array<string,string> $params
     * @param array<int,array<string,mixed>> $rows
     */
    private function controller(
        array $params,
        bool $allowed = true,
        bool $reportsEnabled = true,
        ?\Throwable $failure = null,
        ?ErrorLogger $logger = null,
        array $rows = [],
    ): Data {
        $registry = $this->createStub(ChartRegistry::class);
        $registry->method('getChart')->willReturnCallback(
            static fn (string $id): ?Chart => match ($id) {
                'orders' => self::chart('orders'),
                'trend' => self::chart('trend', type: Chart::TYPE_LINE),
                default => null,
            }
        );
        $access = $this->createStub(ChartAccess::class);
        $access->method('isAllowed')->willReturn($allowed);
        $builder = $this->createStub(RequestBuilder::class);
        $builder->method('build')->willReturnCallback(function (mixed ...$arguments): ReportRequestInterface {
            $this->buildArguments = array_values($arguments);
            $this->compareModes[] = $arguments[3] ?? null;

            return $this->createStub(ReportRequestInterface::class);
        });
        $result = $this->createStub(ReportResultInterface::class);
        $result->method('toArray')->willReturn(['rows' => $rows]);
        $runner = $this->createStub(ReportRunnerInterface::class);
        if ($failure !== null) {
            $runner->method('run')->willThrowException($failure);
        } else {
            $runner->method('run')->willReturn($result);
        }
        $reportsConfig = $this->createStub(AdvancedReportConfig::class);
        $reportsConfig->method('isEnabled')->willReturn($reportsEnabled);

        return new Data(
            $this->context($params),
            $this->jsonFactory(),
            $registry,
            $access,
            $builder,
            $runner,
            $reportsConfig,
            $logger ?? $this->createStub(ErrorLogger::class),
        );
    }
}
