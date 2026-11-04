<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model\Config;

use DOMDocument;
use MageOS\DashboardChart\Model\Config\Converter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Converter::class)]
final class ConverterTest extends TestCase
{
    private const string XML = <<<'XML'
        <config>
            <section id="sales" label="Sales" sortOrder="10">
                <acl resource="Vendor_Module::sales"/>
            </section>
            <section id="hidden" disabled="true">
                <acl resource="Vendor_Module::hidden" inherit="false"/>
            </section>
            <chart id="overview" preset="sales_overview" section="sales" label="Overview" type="line" size="full"
                   limit="5" dateRange="last_7_days" compareTo="previous_year" sortBy="orders" sortDirection="asc" sortOrder="20">
                <description>  Orders per day.  </description>
                <acl resource="Vendor_Module::overview" inherit="false"/>
                <metric code="orders"/>
                <metric code="net_sales" remove="true"/>
                <metric code="aov"/>
                <dimension code="order_date" granularity="week"/>
                <dimension code="store_id" remove="true"/>
            </chart>
            <chart id="dead_stock" disabled="true"/>
        </config>
        XML;

    public function testItConvertsSections(): void
    {
        $result = $this->convert();

        self::assertSame([
            'id' => 'sales',
            'label' => 'Sales',
            'sortOrder' => 10,
            'disabled' => false,
            'acl' => ['resource' => 'Vendor_Module::sales', 'inherit' => true],
        ], $result['sections']['sales']);
        self::assertTrue($result['sections']['hidden']['disabled']);
        self::assertNull($result['sections']['hidden']['label']);
        self::assertSame(['resource' => 'Vendor_Module::hidden', 'inherit' => false], $result['sections']['hidden']['acl']);
    }

    public function testItConvertsAFullChart(): void
    {
        self::assertSame([
            'id' => 'overview',
            'preset' => 'sales_overview',
            'section' => 'sales',
            'label' => 'Overview',
            'description' => 'Orders per day.',
            'type' => 'line',
            'size' => 'full',
            'limit' => 5,
            'dateRange' => 'last_7_days',
            'compareTo' => 'previous_year',
            'sortBy' => 'orders',
            'sortDirection' => 'asc',
            'sortOrder' => 20,
            'disabled' => false,
            'acl' => ['resource' => 'Vendor_Module::overview', 'inherit' => false],
            'metrics' => ['orders', 'aov'],
            'dimensions' => [['code' => 'order_date', 'granularity' => 'week']],
        ], $this->convert()['charts']['overview']);
    }

    public function testItLeavesUnsetChartValuesEmptyForTheRegistryToFillFromThePreset(): void
    {
        $chart = $this->convert()['charts']['dead_stock'];

        self::assertTrue($chart['disabled']);
        self::assertSame('dead_stock', $chart['preset']);
        self::assertSame(['resource' => null, 'inherit' => true], $chart['acl']);
        foreach (['section', 'label', 'description', 'type', 'size', 'limit', 'dateRange', 'compareTo', 'sortBy', 'sortOrder'] as $key) {
            self::assertNull($chart[$key], $key);
        }
        self::assertSame([], $chart['metrics']);
        self::assertSame([], $chart['dimensions']);
    }

    public function testItReturnsEmptyListsForAnEmptyDocument(): void
    {
        self::assertSame(['sections' => [], 'charts' => []], (new Converter())->convert(new DOMDocument()));
    }

    /**
     * @return array{sections: array<string,array<string,mixed>>, charts: array<string,array<string,mixed>>}
     */
    private function convert(): array
    {
        $document = new DOMDocument();
        $document->loadXML(self::XML);

        return (new Converter())->convert($document);
    }
}
