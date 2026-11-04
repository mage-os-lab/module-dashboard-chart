<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\ViewModel;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use MageOS\AdvancedReport\Model\Config as AdvancedReportConfig;
use MageOS\AdvancedReport\Model\Query\DateRangeType;
use MageOS\DashboardChart\Model\Chart\Chart;
use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Config;
use MageOS\DashboardChart\Model\Dashboard\LayoutResolver;

/**
 * Configuration of the dashboard charts app (MageOS_DashboardChart/js/dashboard).
 */
class Dashboard implements ArgumentInterface
{
    private const string REPORTS_RESOURCE = 'MageOS_AdvancedReport::reports';

    /** @var array<string,Chart>|null */
    private ?array $charts = null;

    public function __construct(
        private readonly Config $config,
        private readonly AdvancedReportConfig $advancedReportConfig,
        private readonly ChartAccess $chartAccess,
        private readonly LayoutResolver $layoutResolver,
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $urlBuilder,
        private readonly RequestInterface $request,
        private readonly ResolverInterface $localeResolver,
        private readonly Json $serializer,
    ) {
    }

    public function isVisible(): bool
    {
        return $this->config->isEnabled() && $this->advancedReportConfig->isEnabled() && $this->getCharts() !== [];
    }

    public function getJsConfig(): string
    {
        $canOpenReports = $this->authorization->isAllowed(self::REPORTS_RESOURCE);
        $charts = [];
        foreach ($this->getCharts() as $chart) {
            $charts[] = $chart->toArray() + [
                'reportUrl' => $canOpenReports
                    ? $this->urlBuilder->getUrl('advancedreport/report/view', ['preset' => $chart->preset])
                    : null,
            ];
        }

        return (string) $this->serializer->serialize([
            'charts' => $charts,
            'layout' => $this->layoutResolver->getLayout(),
            'personalized' => $this->layoutResolver->isPersonalized(),
            'canPersonalize' => $this->chartAccess->canPersonalize(),
            'maxOpened' => $this->config->getMaxOpened(),
            'locale' => $this->localeResolver->getLocale(),
            'scope' => (object) array_filter([
                'store' => (int) $this->request->getParam('store'),
                'group' => (int) $this->request->getParam('group'),
                'website' => (int) $this->request->getParam('website'),
            ]),
            'periods' => $this->getPeriods(),
            'urls' => [
                'data' => $this->urlBuilder->getUrl('dashboardchart/chart/data'),
                'save' => $this->urlBuilder->getUrl('dashboardchart/chart/save'),
                'reset' => $this->urlBuilder->getUrl('dashboardchart/chart/reset'),
            ],
        ]);
    }

    /**
     * @return array<string,Chart>
     */
    private function getCharts(): array
    {
        return $this->charts ??= $this->chartAccess->getAllowedCharts();
    }

    /**
     * @return array<int,array{value: string, label: string}>
     */
    private function getPeriods(): array
    {
        $labels = [
            DateRangeType::TODAY->value => __('Today'),
            DateRangeType::LAST_7_DAYS->value => __('Last 7 days'),
            DateRangeType::LAST_30_DAYS->value => __('Last 30 days'),
            DateRangeType::MONTH_TO_DATE->value => __('Month to date'),
            DateRangeType::LAST_MONTH->value => __('Last month'),
            DateRangeType::YEAR_TO_DATE->value => __('Year to date'),
        ];
        $periods = [['value' => '', 'label' => (string) __('Chart default period')]];
        foreach ($labels as $value => $label) {
            $periods[] = ['value' => $value, 'label' => (string) $label];
        }

        return $periods;
    }
}
