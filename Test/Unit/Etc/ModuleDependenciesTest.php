<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Etc;

use DOMDocument;
use DOMElement;
use DOMXPath;
use MageOS\DashboardChart\Test\Unit\Helper\XPathTestTrait;
use PHPUnit\Framework\TestCase;

final class ModuleDependenciesTest extends TestCase
{
    use XPathTestTrait;

    private const string MODULE_XML_PATH = __DIR__ . '/../../../etc/module.xml';
    private const string COMPOSER_JSON_PATH = __DIR__ . '/../../../composer.json';

    public function testItDeclaresTheSameModuleDependenciesInModuleXmlSequenceAndComposerJsonRequire(): void
    {
        $document = new DOMDocument();
        $document->load(self::MODULE_XML_PATH);

        $sequenceModules = [];
        foreach (self::queryNodes(new DOMXPath($document), '//sequence/module') as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $sequenceModules[] = $node->getAttribute('name');
        }
        self::assertNotEmpty($sequenceModules);

        $composer = json_decode((string) file_get_contents(self::COMPOSER_JSON_PATH), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertIsArray($composer['require']);
        $require = array_keys($composer['require']);

        foreach ($sequenceModules as $moduleName) {
            $packageName = self::packageName($moduleName);
            self::assertContains(
                $packageName,
                $require,
                \sprintf('composer.json require misses "%s" for the module.xml dependency "%s"', $packageName, $moduleName)
            );
        }
    }

    public function testItMapsModuleNamesToPackageNames(): void
    {
        self::assertSame('magento/module-backend', self::packageName('Magento_Backend'));
        self::assertSame('mageos/module-advanced-report', self::packageName('MageOS_AdvancedReport'));
    }

    /**
     * Magento_Backend => magento/module-backend, MageOS_AdvancedReport => mageos/module-advanced-report.
     */
    private static function packageName(string $moduleName): string
    {
        [$vendor, $name] = explode('_', $moduleName, 2);

        return strtolower($vendor) . '/module-' . strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $name));
    }
}
