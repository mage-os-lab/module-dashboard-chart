<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Config;

use Magento\Framework\Config\ConverterInterface;

/**
 * Converts the merged dashboard_charts.xml DOM into sections and chart overrides keyed by id.
 *
 * Chart values not set in XML stay null (or empty lists) so that the chart registry can fill them from the
 * Advanced Report preset. Metrics/dimensions flagged remove="true" are dropped here; disabled charts are kept
 * (flagged) because they remove a chart generated from a preset.
 */
class Converter implements ConverterInterface
{
    /**
     * @param \DOMDocument $source
     * @return array{sections: array<string,array<string,mixed>>, charts: array<string,array<string,mixed>>}
     */
    public function convert($source): array
    {
        $root = $source->documentElement;
        $sections = [];
        $charts = [];
        foreach ($root !== null ? $this->children($root, 'section') : [] as $node) {
            $id = $node->getAttribute('id');
            $sections[$id] = [
                'id' => $id,
                'label' => $node->getAttribute('label') ?: null,
                'sortOrder' => (int) $node->getAttribute('sortOrder'),
                'disabled' => $this->bool($node->getAttribute('disabled')),
                'acl' => $this->convertAcl($node),
            ];
        }
        foreach ($root !== null ? $this->children($root, 'chart') : [] as $node) {
            $id = $node->getAttribute('id');
            $charts[$id] = $this->convertChart($id, $node);
        }

        return ['sections' => $sections, 'charts' => $charts];
    }

    /**
     * @return array<string,mixed>
     */
    private function convertChart(string $id, \DOMElement $node): array
    {
        $description = $this->children($node, 'description')[0] ?? null;

        return [
            'id' => $id,
            'preset' => $node->getAttribute('preset') ?: $id,
            'section' => $node->getAttribute('section') ?: null,
            'label' => $node->getAttribute('label') ?: null,
            'description' => $description !== null ? trim($description->textContent) : null,
            'type' => $node->getAttribute('type') ?: null,
            'size' => $node->getAttribute('size') ?: null,
            'limit' => $node->hasAttribute('limit') ? (int) $node->getAttribute('limit') : null,
            'dateRange' => $node->getAttribute('dateRange') ?: null,
            'compareTo' => $node->getAttribute('compareTo') ?: null,
            'sortBy' => $node->getAttribute('sortBy') ?: null,
            'sortDirection' => $node->getAttribute('sortDirection') ?: null,
            'sortOrder' => $node->hasAttribute('sortOrder') ? (int) $node->getAttribute('sortOrder') : null,
            'disabled' => $this->bool($node->getAttribute('disabled')),
            'acl' => $this->convertAcl($node),
            'metrics' => $this->convertMetrics($node),
            'dimensions' => $this->convertDimensions($node),
        ];
    }

    /**
     * @return array{resource: string|null, inherit: bool}
     */
    private function convertAcl(\DOMElement $parent): array
    {
        $acl = $this->children($parent, 'acl')[0] ?? null;

        return [
            'resource' => $acl?->getAttribute('resource') ?: null,
            'inherit' => $acl === null || !$acl->hasAttribute('inherit') || $this->bool($acl->getAttribute('inherit')),
        ];
    }

    /**
     * @return string[]
     */
    private function convertMetrics(\DOMElement $chart): array
    {
        $metrics = [];
        foreach ($this->children($chart, 'metric') as $node) {
            if (!$this->bool($node->getAttribute('remove'))) {
                $metrics[] = $node->getAttribute('code');
            }
        }

        return $metrics;
    }

    /**
     * @return array<int,array{code: string, granularity: string|null}>
     */
    private function convertDimensions(\DOMElement $chart): array
    {
        $dimensions = [];
        foreach ($this->children($chart, 'dimension') as $node) {
            if (!$this->bool($node->getAttribute('remove'))) {
                $dimensions[] = [
                    'code' => $node->getAttribute('code'),
                    'granularity' => $node->getAttribute('granularity') ?: null,
                ];
            }
        }

        return $dimensions;
    }

    /**
     * @return \DOMElement[]
     */
    private function children(\DOMElement $parent, string $name): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === $name) {
                $children[] = $child;
            }
        }

        return $children;
    }

    private function bool(string $value): bool
    {
        return $value === 'true' || $value === '1';
    }
}
