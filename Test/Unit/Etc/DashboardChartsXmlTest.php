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

/**
 * dashboard_charts.xml and the config.xml defaults against the schemas, acl.xml and the Advanced Report presets.
 */
final class DashboardChartsXmlTest extends TestCase
{
    use XPathTestTrait;

    private const string ETC = __DIR__ . '/../../../etc';

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
     * Metric and dimension codes of the dataset of every Advanced Report preset.
     *
     * @return array<string,array{metric: string[], dimension: string[]}>
     */
    private static function presets(): array
    {
        $path = InstalledVersions::getInstallPath('mage-os/module-advanced-report');
        self::assertNotNull($path);
        $file = $path . '/etc/report_datasets.xml';

        $datasets = [];
        foreach (self::elements($file, '/config/dataset') as $dataset) {
            foreach (['metric', 'dimension'] as $type) {
                $datasets[$dataset->getAttribute('code')][$type] = array_map(
                    static fn (DOMElement $node): string => $node->getAttribute('code'),
                    iterator_to_array($dataset->getElementsByTagName($type), false)
                );
            }
        }
        $presets = [];
        foreach (self::elements($file, '/config/preset') as $preset) {
            $presets[$preset->getAttribute('code')] = $datasets[$preset->getAttribute('dataset')]
                ?? ['metric' => [], 'dimension' => []];
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
