<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\DashboardChart\Model\Chart\ChartRegistry;

/**
 * Every dashboard chart, grouped by section (rights are applied per admin on the dashboard).
 */
class Charts implements OptionSourceInterface
{
    public function __construct(private readonly ChartRegistry $chartRegistry)
    {
    }

    /**
     * @return array<int,array{label: string, value: array<int,array{value: string, label: string}>}>
     */
    public function toOptionArray(): array
    {
        $groups = [];
        foreach ($this->chartRegistry->getCharts() as $chart) {
            $groups[$chart->section->id] ??= ['label' => (string) __($chart->section->label), 'value' => []];
            $groups[$chart->section->id]['value'][] = ['value' => $chart->id, 'label' => (string) __($chart->label)];
        }

        return array_values($groups);
    }
}
