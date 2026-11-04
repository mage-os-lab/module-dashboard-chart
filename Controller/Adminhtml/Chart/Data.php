<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Controller\Adminhtml\Chart;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\AuthorizationException;
use MageOS\AdvancedReport\Api\ReportRunnerInterface;
use MageOS\AdvancedReport\Model\Config as AdvancedReportConfig;
use MageOS\AdvancedReport\Model\DataSource\QueryTimeoutException;
use MageOS\AdvancedReport\Model\Query\CompareMode;
use MageOS\AdvancedReport\Model\Query\InvalidRequestException;
use MageOS\AdvancedReport\Model\Security\ErrorLogger;
use MageOS\DashboardChart\Model\Chart\Chart;
use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Chart\ChartRegistry;
use MageOS\DashboardChart\Model\Chart\RequestBuilder;

/**
 * Runs the report of one dashboard chart. The request is built server side from the chart definition: the client
 * only chooses the chart, the period and the store scope. Advanced Report checks the dataset ACL again.
 */
class Data extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Magento_Backend::dashboard';

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ChartRegistry $chartRegistry,
        private readonly ChartAccess $chartAccess,
        private readonly RequestBuilder $requestBuilder,
        private readonly ReportRunnerInterface $reportRunner,
        private readonly AdvancedReportConfig $advancedReportConfig,
        private readonly ErrorLogger $errorLogger,
    ) {
        parent::__construct($context);
    }

    public function execute(): JsonResult
    {
        $result = $this->resultJsonFactory->create();
        $chart = $this->chartRegistry->getChart((string) $this->getRequest()->getParam('chart'));
        if ($chart === null || !$this->advancedReportConfig->isEnabled()) {
            return $this->error($result, 404, (string) __('This chart is not available.'));
        }
        if (!$this->chartAccess->isAllowed($chart)) {
            return $this->error($result, 403, (string) __('You are not allowed to see this chart.'));
        }

        try {
            $period = $this->getRequest()->getParam('period') ?: null;
            $scope = [
                'store' => (int) $this->getRequest()->getParam('store'),
                'group' => (int) $this->getRequest()->getParam('group'),
                'website' => (int) $this->getRequest()->getParam('website'),
            ];
            $isLine = $chart->type === Chart::TYPE_LINE;
            $data = $this->reportRunner->run(
                $this->requestBuilder->build($chart, $period, $scope, $isLine ? CompareMode::PREVIOUS_PERIOD->value : null)
            )->toArray();
            if ($isLine) {
                $data = $this->addPreviousYear($data, $this->reportRunner->run(
                    $this->requestBuilder->build($chart, $period, $scope, CompareMode::PREVIOUS_YEAR->value)
                )->toArray());
            }

            return $result->setData($data);
        } catch (InvalidRequestException $e) {
            return $this->error($result, 400, $e->getMessage());
        } catch (AuthorizationException $e) {
            return $this->error($result, 403, $e->getMessage());
        } catch (QueryTimeoutException $e) {
            return $this->error($result, 504, $e->getMessage());
        } catch (\Throwable $e) {
            $this->errorLogger->log($e, 'dashboard_chart_data');

            return $this->error($result, 500, (string) __('An error occurred while loading the chart.'));
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $yearData
     * @return array<string,mixed>
     */
    protected function addPreviousYear(array $data, array $yearData): array
    {
        $comparisons = [];
        foreach ($yearData['rows'] as $row) {
            $comparisons[json_encode($row['dims_raw'])] = $row['comparison'] ?? null;
        }
        foreach ($data['rows'] as &$row) {
            $row['comparison_year'] = $comparisons[json_encode($row['dims_raw'])] ?? null;
        }
        unset($row);

        return $data;
    }

    private function error(JsonResult $result, int $code, string $message): JsonResult
    {
        return $result->setHttpResponseCode($code)->setData(['error' => true, 'message' => $message]);
    }
}
