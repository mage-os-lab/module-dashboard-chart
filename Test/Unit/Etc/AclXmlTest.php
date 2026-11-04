<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Etc;

use DOMDocument;
use DOMElement;
use DOMXPath;
use MageOS\DashboardChart\Controller\Adminhtml\Chart\Reset;
use MageOS\DashboardChart\Controller\Adminhtml\Chart\Save;
use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Chart\ChartRegistry;
use MageOS\DashboardChart\Test\Unit\Helper\XPathTestTrait;
use PHPUnit\Framework\TestCase;

final class AclXmlTest extends TestCase
{
    use XPathTestTrait;

    private const string ACL_XML_PATH = __DIR__ . '/../../../etc/acl.xml';

    public function testItDeclaresTheChartResourcesUnderTheDashboardResource(): void
    {
        self::assertSame(
            [
                'MageOS_DashboardChart::sales',
                'MageOS_DashboardChart::catalog',
                'MageOS_DashboardChart::customers',
                'MageOS_DashboardChart::personalize',
            ],
            self::ids('//resource[@id="Magento_Backend::dashboard"]/resource[@id="MageOS_DashboardChart::charts"]/resource')
        );
    }

    public function testTheResourcesUsedByTheCodeAreDeclared(): void
    {
        $declared = self::ids('//resource');

        foreach ([ChartRegistry::ROOT_RESOURCE, ChartAccess::PERSONALIZE_RESOURCE, Save::ADMIN_RESOURCE, Reset::ADMIN_RESOURCE] as $resource) {
            if (str_starts_with($resource, 'MageOS_DashboardChart::')) {
                self::assertContains($resource, $declared);
            }
        }
    }

    /**
     * @return string[]
     */
    private static function ids(string $expression): array
    {
        $document = new DOMDocument();
        $document->load(self::ACL_XML_PATH);
        $ids = [];
        foreach (self::queryNodes(new DOMXPath($document), $expression) as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $ids[] = $node->getAttribute('id');
        }

        return $ids;
    }
}
