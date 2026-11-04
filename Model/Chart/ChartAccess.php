<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Chart;

use Magento\Framework\AuthorizationInterface;
use MageOS\AdvancedReport\Model\Definition\DefinitionRegistry;
use MageOS\AdvancedReport\Model\Security\DatasetAccess;

/**
 * Whether the current admin may see a chart: the chart ACL chain (chart, section, root) and the ACL of the
 * Advanced Report dataset behind its preset (module resource + core resource, see {@see DatasetAccess}).
 */
class ChartAccess
{
    public const string PERSONALIZE_RESOURCE = 'MageOS_DashboardChart::personalize';

    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly DatasetAccess $datasetAccess,
        private readonly DefinitionRegistry $definitionRegistry,
        private readonly ChartRegistry $chartRegistry,
    ) {
    }

    public function isAllowed(Chart $chart): bool
    {
        foreach ($chart->aclResources as $resource) {
            if (!$this->authorization->isAllowed($resource)) {
                return false;
            }
        }

        return $this->datasetAccess->isAllowed($this->definitionRegistry->getDataset($chart->dataset));
    }

    /**
     * Charts the current admin may see, in registry order.
     *
     * @return array<string,Chart>
     */
    public function getAllowedCharts(): array
    {
        return array_filter($this->chartRegistry->getCharts(), $this->isAllowed(...));
    }

    public function canPersonalize(): bool
    {
        return $this->authorization->isAllowed(self::PERSONALIZE_RESOURCE);
    }
}
