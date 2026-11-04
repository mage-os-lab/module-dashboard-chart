<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model\Config;

use Magento\Framework\Config\Dom;
use Magento\Framework\Config\ValidationState\Configurable;
use MageOS\DashboardChart\Model\Config\Converter;
use MageOS\DashboardChart\Model\Config\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reader::class)]
final class ReaderTest extends TestCase
{
    private const string HEAD = '<?xml version="1.0"?><config>';

    public function testItMergesTheSectionsAndChartsOfSeveralModulesById(): void
    {
        $dom = new Dom(
            self::HEAD
            . '<section id="sales" label="Sales"/>'
            . '<chart id="sales_overview" type="line"><metric code="net_sales"/><metric code="orders"/></chart>'
            . '<chart id="dead_stock" type="horizontalBar"/>'
            . '</config>',
            new Configurable(false),
            Reader::ID_ATTRIBUTES,
        );
        $dom->merge(
            self::HEAD
            . '<section id="sales" sortOrder="5"/>'
            . '<section id="recommendation" label="Recommendations"/>'
            . '<chart id="sales_overview" type="bar"><metric code="orders" remove="true"/></chart>'
            . '<chart id="recommendation_trend" section="recommendation"/>'
            . '</config>'
        );

        $result = (new Converter())->convert($dom->getDom());

        self::assertSame(['sales', 'recommendation'], array_keys($result['sections']));
        self::assertSame(5, $result['sections']['sales']['sortOrder']);
        self::assertSame('Sales', $result['sections']['sales']['label']);
        self::assertSame(['sales_overview', 'dead_stock', 'recommendation_trend'], array_keys($result['charts']));
        self::assertSame('bar', $result['charts']['sales_overview']['type']);
        self::assertSame(['net_sales'], $result['charts']['sales_overview']['metrics']);
    }
}
