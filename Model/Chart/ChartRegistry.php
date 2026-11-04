<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Chart;

use MageOS\AdvancedReport\Model\Definition\DefinitionRegistry;
use MageOS\AdvancedReport\Model\Definition\NoSuchDefinitionException;
use MageOS\AdvancedReport\Model\Definition\Preset;
use MageOS\DashboardChart\Model\Config\Data;

/**
 * All dashboard charts, independent of the current admin's rights (see {@see ChartAccess}).
 *
 * Every Advanced Report preset (report_datasets.xml) generates a chart with the preset code as id, in the section
 * of its area. Chart type, metrics, dimension and sort come from the preset (its <chart> in report_datasets.xml).
 * dashboard_charts.xml declares the sections and refines generated charts or adds new ones
 * (another id on an existing preset). Charts on unknown presets are skipped.
 */
class ChartRegistry
{
    public const string ROOT_RESOURCE = 'MageOS_DashboardChart::charts';

    private const int DEFAULT_LIMIT = 10;
    private const int GENERATED_SORT_ORDER = 1000;

    /** @var array<string,Chart>|null */
    private ?array $charts = null;

    /** @var array<string,Section> */
    private array $sections = [];

    public function __construct(
        private readonly Data $configData,
        private readonly DefinitionRegistry $definitionRegistry,
    ) {
    }

    /**
     * Charts sorted by section then chart sort order.
     *
     * @return array<string,Chart>
     */
    public function getCharts(): array
    {
        return $this->charts ??= $this->build();
    }

    public function getChart(string $id): ?Chart
    {
        return $this->getCharts()[$id] ?? null;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function getOverrides(): array
    {
        return (array) ($this->configData->get('charts') ?? []);
    }

    /**
     * @return array<string,Chart>
     */
    private function build(): array
    {
        $definitions = [];
        $position = 0;
        foreach ($this->definitionRegistry->getPresets() as $preset) {
            $definitions[$preset->code] = ['id' => $preset->code, 'preset' => $preset->code, 'position' => $position++];
        }
        foreach ($this->getOverrides() as $id => $override) {
            $definitions[$id] = array_merge($definitions[$id] ?? ['position' => $position++], $override);
        }

        $charts = [];
        foreach ($definitions as $id => $definition) {
            if (!empty($definition['disabled'])) {
                continue;
            }
            try {
                $preset = $this->definitionRegistry->getPreset((string) $definition['preset']);
            } catch (NoSuchDefinitionException) {
                continue;
            }
            $chart = $this->createChart((string) $id, $preset, $definition);
            if ($chart !== null) {
                $charts[$chart->id] = $chart;
            }
        }

        uasort($charts, static fn (Chart $a, Chart $b): int => [$a->section->sortOrder, $a->section->id, $a->sortOrder]
            <=> [$b->section->sortOrder, $b->section->id, $b->sortOrder]);

        return $charts;
    }

    /**
     * @param array<string,mixed> $definition
     */
    private function createChart(string $id, Preset $preset, array $definition): ?Chart
    {
        $section = $this->getSection((string) ($definition['section'] ?? $preset->area));
        if ($section === null) {
            return null;
        }
        $dataset = $this->definitionRegistry->getDataset($preset->dataset);

        $presetChart = $preset->resolveChart();
        $dimensions = $definition['dimensions'] ?? [];
        $type = $definition['type'] ?? null;
        if ($dimensions === []) {
            $dimensions = $presetChart['dimensions'];
            $type ??= $presetChart['type'];
        }
        $type ??= ($dimensions[0]['granularity'] ?? null) !== null ? Chart::TYPE_LINE : Chart::TYPE_HORIZONTAL_BAR;

        $metrics = array_values(array_unique(array_map(strval(...), $definition['metrics'] ?? []))) ?: $presetChart['metrics'];
        if ($metrics === []) {
            return null;
        }

        return new Chart(
            id: $id,
            preset: $preset->code,
            dataset: $preset->dataset,
            section: $section,
            label: (string) ($definition['label'] ?? $preset->label),
            description: (string) ($definition['description'] ?? $preset->description),
            type: $type,
            size: (string) ($definition['size'] ?? 'half'),
            metrics: $metrics,
            dimensions: $dimensions,
            sort: $this->resolveSort($definition, $preset, $metrics),
            limit: $definition['limit'] ?? ($type === Chart::TYPE_LINE ? null : self::DEFAULT_LIMIT),
            dateRange: $definition['dateRange'] ?? $preset->dateRange,
            compareTo: (string) ($definition['compareTo'] ?? ($type === Chart::TYPE_LINE ? $preset->compareTo : 'none')),
            dateless: $dataset->dateless,
            sortOrder: (int) ($definition['sortOrder'] ?? self::GENERATED_SORT_ORDER + (int) $definition['position']),
            aclResources: $this->resolveResources($definition['acl'] ?? null, $section->aclResources),
        );
    }

    /**
     * Explicit sort, else the sort of the preset chart, else the preset sort on the charted metrics. Presets without a sort keep their natural order
     * (date buckets, hours, weekdays).
     *
     * @param array<string,mixed> $definition
     * @param string[] $metrics
     * @return array<int,array{by: string, direction: string}>
     */
    private function resolveSort(array $definition, Preset $preset, array $metrics): array
    {
        if (!empty($definition['sortBy'])) {
            return [['by' => (string) $definition['sortBy'], 'direction' => (string) ($definition['sortDirection'] ?? 'desc')]];
        }
        if (!empty($preset->chart['sortBy'])) {
            return [['by' => $preset->chart['sortBy'], 'direction' => $preset->chart['sortDirection'] ?? 'desc']];
        }
        $sort = [];
        foreach ($preset->sort as $entry) {
            if (\in_array($entry['metric'], $metrics, true)) {
                $sort[] = ['by' => $entry['metric'], 'direction' => $entry['direction']];
            }
        }

        return $sort;
    }

    private function getSection(string $id): ?Section
    {
        if (isset($this->sections[$id])) {
            return $this->sections[$id];
        }
        $declared = ((array) ($this->configData->get('sections') ?? []))[$id] ?? null;
        if (!empty($declared['disabled'])) {
            return null;
        }

        return $this->sections[$id] = new Section(
            id: $id,
            label: (string) ($declared['label'] ?? ucwords(str_replace('_', ' ', $id))),
            sortOrder: (int) ($declared['sortOrder'] ?? self::GENERATED_SORT_ORDER),
            aclResources: $this->resolveResources($declared['acl'] ?? null, [self::ROOT_RESOURCE]),
        );
    }

    /**
     * Own resource, plus the parent ones unless inherit="false".
     *
     * @param array{resource: string|null, inherit: bool}|null $acl
     * @param string[] $parentResources
     * @return string[]
     */
    private function resolveResources(?array $acl, array $parentResources): array
    {
        $resources = ($acl['inherit'] ?? true) ? $parentResources : [];
        if (!empty($acl['resource'])) {
            $resources[] = (string) $acl['resource'];
        }

        return array_values(array_unique($resources));
    }
}
