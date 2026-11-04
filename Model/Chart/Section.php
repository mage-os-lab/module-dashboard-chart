<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Chart;

/**
 * A dashboard chart section, declared in dashboard_charts.xml or generated from a preset area.
 */
final readonly class Section
{
    /**
     * @param string $id
     * @param string $label
     * @param int $sortOrder
     * @param string[] $aclResources Own resource plus the inherited root resource.
     */
    public function __construct(
        public string $id,
        public string $label,
        public int $sortOrder,
        public array $aclResources,
    ) {
    }
}
