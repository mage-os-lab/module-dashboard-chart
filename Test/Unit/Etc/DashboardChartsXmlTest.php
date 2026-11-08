<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Etc;

use Composer\InstalledVersions;
use DOMDocument;
use DOMElement;
use DOMXPath;
use MageOS\DashboardChart\Model\Config;
use MageOS\DashboardChart\Test\Tooling\XsdValidator;
use MageOS\DashboardChart\Test\Unit\Helper\XPathTestTrait;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * dashboard_charts.xml and the config.xml defaults against the schemas, acl.xml and the Advanced Report presets.
 */
final class DashboardChartsXmlTest extends TestCase
{
    use XPathTestTrait;

    private const string ETC = __DIR__ . '/../../../etc';

    const string RETURNS_SECTION = 'returns';

    const array PRESET_FILES = ['report_datasets.xml', 'report_datasets_rma.xml'];

    const array RETURN_CHARTS = [
        'returns_overview' => ['size' => 'full', 'limit' => '', 'sortOrder' => '10'],
        'withdrawals_vs_returns' => ['size' => '', 'limit' => '', 'sortOrder' => '20'],
        'withdrawals_trend' => ['size' => '', 'limit' => '366', 'sortOrder' => '30'],
        'returns_by_reason' => ['size' => '', 'limit' => '10', 'sortOrder' => '40'],
        'returns_by_status' => ['size' => '', 'limit' => '', 'sortOrder' => '50'],
        'returns_by_resolution' => ['size' => '', 'limit' => '', 'sortOrder' => '60'],
        'top_returned_products' => ['size' => '', 'limit' => '10', 'sortOrder' => '70'],
        'returns_by_condition' => ['size' => '', 'limit' => '', 'sortOrder' => '80'],
        'product_return_rate' => ['size' => '', 'limit' => '10', 'sortOrder' => '90'],
        'unit_return_rate_trend' => ['size' => 'full', 'limit' => '', 'sortOrder' => '100'],
        'order_return_rate' => ['size' => 'full', 'limit' => '', 'sortOrder' => '110'],
        'returns_by_shipping_country' => ['size' => '', 'limit' => '10', 'sortOrder' => '120'],
        'open_return_requests' => ['size' => '', 'limit' => '366', 'sortOrder' => '130'],
    ];

    public function testItIsValidAgainstTheFileAndTheMergedSchemas(): void
    {
        $validator = new XsdValidator();

        self::assertSame([], $validator->validateFile(self::ETC . '/dashboard_charts.xml', self::ETC . '/dashboard_charts_file.xsd'));
        self::assertSame([], $validator->validateFile(self::ETC . '/dashboard_charts.xml', self::ETC . '/dashboard_charts.xsd'));
    }

    public function testItOnlyReferencesDeclaredAclResources(): void
    {
        $declared = array_map(
            static fn (DOMElement $node): string => $node->getAttribute('id'),
            self::elements(self::ETC . '/acl.xml', '//resource')
        );

        foreach (self::elements(self::ETC . '/dashboard_charts.xml', '//acl[@resource]') as $acl) {
            self::assertContains($acl->getAttribute('resource'), $declared);
        }
    }

    public function testItRefinesExistingPresetsWithTheirOwnMetricsAndDimensions(): void
    {
        $presets = self::presets();

        foreach (self::elements(self::ETC . '/dashboard_charts.xml', '/config/chart') as $chart) {
            $presetCode = $chart->getAttribute('preset') ?: $chart->getAttribute('id');
            self::assertArrayHasKey($presetCode, $presets, 'Unknown preset ' . $presetCode);
            foreach (['metric', 'dimension'] as $type) {
                foreach ($chart->getElementsByTagName($type) as $node) {
                    self::assertContains(
                        $node->getAttribute('code'),
                        $presets[$presetCode][$type],
                        \sprintf('Chart "%s": %s "%s" is not in the preset dataset', $chart->getAttribute('id'), $type, $node->getAttribute('code'))
                    );
                }
            }
        }
    }

    public function testTheDefaultRowsShowExistingChartsWithinTheOpenedLimit(): void
    {
        $config = new DOMXPath(self::document(self::ETC . '/config.xml'));
        $rows = json_decode($config->evaluate('string(//admin/dashboard/advanced_report_charts)'), true, flags: JSON_THROW_ON_ERROR);
        $max = (int) $config->evaluate('string(//admin/dashboard/advanced_report_charts_max_opened)');
        self::assertIsArray($rows);
        self::assertNotEmpty($rows);

        $presets = self::presets();
        $opened = 0;
        foreach ($rows as $row) {
            self::assertIsArray($row);
            self::assertSame(Config::ALL_ROLES, $row['role']);
            self::assertArrayHasKey($row['chart'], $presets);
            $opened += (int) $row['opened'];
        }
        self::assertGreaterThan(0, $max);
        self::assertLessThanOrEqual($max, $opened);
    }

    /**
     * @return void
     * @throws ExpectationFailedException
     */
    public function testItDeclaresTheReturnsSectionWithoutAcl(): void
    {
        $sections = self::elements(
            self::ETC . '/dashboard_charts.xml',
            '/config/section[@id="' . self::RETURNS_SECTION . '"]'
        );

        self::assertCount(1, $sections);
        self::assertSame('Returns', $sections[0]->getAttribute('label'));
        self::assertSame('50', $sections[0]->getAttribute('sortOrder'));
        self::assertSame(0, $sections[0]->getElementsByTagName('acl')->length);
    }

    /**
     * @return void
     * @throws ExpectationFailedException
     */
    public function testItRefinesEveryReturnPresetInTheReturnsSection(): void
    {
        $presets = self::presets();
        $charts = [];
        foreach (self::elements(self::ETC . '/dashboard_charts.xml', '/config/chart') as $chart) {
            $presetCode = $chart->getAttribute('preset') ?: $chart->getAttribute('id');
            if (($presets[$presetCode]['area'] ?? null) !== self::RETURNS_SECTION) {
                continue;
            }
            self::assertContains($chart->getAttribute('section'), ['', self::RETURNS_SECTION]);
            $descriptions = $chart->getElementsByTagName('description');
            self::assertCount(1, $descriptions);
            self::assertNotSame('', trim((string) $descriptions->item(0)?->textContent));
            $charts[$chart->getAttribute('id')] = [
                'size' => $chart->getAttribute('size'),
                'limit' => $chart->getAttribute('limit'),
                'sortOrder' => $chart->getAttribute('sortOrder'),
            ];
        }

        self::assertSame(self::RETURN_CHARTS, $charts);
        self::assertEqualsCanonicalizing(
            array_keys(self::RETURN_CHARTS),
            array_keys(array_filter(
                $presets,
                static fn (array $preset): bool => $preset['area'] === self::RETURNS_SECTION
            ))
        );
    }

    /**
     * Area and metric and dimension codes of the dataset of every Advanced Report preset.
     *
     * @return array<string,array{area: string, metric: string[], dimension: string[]}>
     */
    private static function presets(): array
    {
        $path = InstalledVersions::getInstallPath('mage-os/module-advanced-report');
        self::assertNotNull($path);
        $files = array_map(static fn (string $file): string => $path . '/etc/' . $file, self::PRESET_FILES);

        $datasets = [];
        foreach ($files as $file) {
            foreach (self::elements($file, '/config/dataset') as $dataset) {
                foreach (['metric', 'dimension'] as $type) {
                    $datasets[$dataset->getAttribute('code')][$type] = array_map(
                        static fn (DOMElement $node): string => $node->getAttribute('code'),
                        iterator_to_array($dataset->getElementsByTagName($type), false)
                    );
                }
            }
        }
        $presets = [];
        foreach ($files as $file) {
            foreach (self::elements($file, '/config/preset') as $preset) {
                $presets[$preset->getAttribute('code')] = [
                    'area' => $preset->getAttribute('area'),
                    ...($datasets[$preset->getAttribute('dataset')] ?? ['metric' => [], 'dimension' => []]),
                ];
            }
        }

        return $presets;
    }

    /**
     * @return DOMElement[]
     */
    private static function elements(string $file, string $expression): array
    {
        $elements = [];
        foreach (self::queryNodes(new DOMXPath(self::document($file)), $expression) as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $elements[] = $node;
        }

        return $elements;
    }

    private static function document(string $file): DOMDocument
    {
        $document = new DOMDocument();
        self::assertTrue($document->load($file), 'Cannot load ' . $file);

        return $document;
    }
}
