<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Chart;

/**
 * A dashboard chart: an Advanced Report preset plus how to draw it.
 */
final readonly class Chart
{
    public const string TYPE_LINE = 'line';
    public const string TYPE_BAR = 'bar';
    public const string TYPE_HORIZONTAL_BAR = 'horizontalBar';

    /**
     * @param string $id
     * @param string $preset Advanced Report preset code.
     * @param string $dataset Dataset code of the preset.
     * @param Section $section
     * @param string $label
     * @param string $description
     * @param string $type line|bar|horizontalBar|doughnut|pie
     * @param string $size half|full
     * @param string[] $metrics
     * @param array<int,array{code: string, granularity: string|null}> $dimensions
     * @param array<int,array{by: string, direction: string}> $sort
     * @param int|null $limit
     * @param string|null $dateRange
     * @param string $compareTo
     * @param bool $dateless
     * @param int $sortOrder
     * @param string[] $aclResources Own resources plus the inherited section ones (dataset ACL excluded).
     */
    public function __construct(
        public string $id,
        public string $preset,
        public string $dataset,
        public Section $section,
        public string $label,
        public string $description,
        public string $type,
        public string $size,
        public array $metrics,
        public array $dimensions,
        public array $sort,
        public ?int $limit,
        public ?string $dateRange,
        public string $compareTo,
        public bool $dateless,
        public int $sortOrder,
        public array $aclResources,
    ) {
    }

    /**
     * Front end description of the chart (no ACL details), labels translated.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'preset' => $this->preset,
            'section' => $this->section->id,
            'sectionLabel' => (string) __($this->section->label),
            'label' => (string) __($this->label),
            'description' => $this->description === '' ? '' : (string) __($this->description),
            'type' => $this->type,
            'size' => $this->size,
            'dateRange' => $this->dateRange,
            'dateless' => $this->dateless,
        ];
    }
}
