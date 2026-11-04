<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Controller\Adminhtml\Chart;

use Magento\Framework\Serialize\Serializer\Json;
use MageOS\DashboardChart\Controller\Adminhtml\Chart\Reset;
use MageOS\DashboardChart\Controller\Adminhtml\Chart\Save;
use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Dashboard\LayoutResolver;
use MageOS\DashboardChart\Model\Dashboard\UserPreferences;
use MageOS\DashboardChart\Test\Unit\Helper\ControllerTestTrait;
use MageOS\DashboardChart\Test\Unit\Helper\DefinitionTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Save::class)]
#[CoversClass(Reset::class)]
final class SaveTest extends TestCase
{
    use ControllerTestTrait;
    use DefinitionTestTrait;

    public function testItSavesTheAllowedChartsCappedAndReturnsTheResolvedLayout(): void
    {
        $preferences = $this->createMock(UserPreferences::class);
        $preferences->expects(self::once())->method('save')->with([
            ['id' => 'dead_stock', 'opened' => true],
            ['id' => 'orders', 'opened' => false],
        ]);

        $layout = json_encode([
            ['id' => 'dead_stock', 'opened' => true],
            ['id' => 'forbidden', 'opened' => true],
            ['id' => 'orders', 'opened' => true],
            'junk',
        ]);
        $this->save(['layout' => $layout], $preferences)->execute();

        self::assertSame(200, $this->resultCode);
        self::assertSame([['id' => 'resolved', 'opened' => true]], $this->resultData['layout']);
        self::assertTrue($this->resultData['personalized']);
    }

    public function testItRefusesAMalformedLayout(): void
    {
        $preferences = $this->createMock(UserPreferences::class);
        $preferences->expects(self::never())->method('save');

        $this->save(['layout' => '{oops'], $preferences)->execute();
        self::assertSame(400, $this->resultCode);

        $this->resultCode = 200;
        $this->save(['layout' => '"text"'], $preferences)->execute();
        self::assertSame(400, $this->resultCode);
    }

    public function testResetDropsTheSavedLayout(): void
    {
        $preferences = $this->createMock(UserPreferences::class);
        $preferences->expects(self::once())->method('reset');

        (new Reset($this->context([]), $this->jsonFactory(), $preferences, $this->layoutResolver()))->execute();

        self::assertSame([['id' => 'resolved', 'opened' => true]], $this->resultData['layout']);
        self::assertFalse($this->resultData['personalized']);
    }

    /**
     * @param array<string,string|false> $params
     */
    private function save(array $params, UserPreferences $preferences): Save
    {
        $access = $this->createStub(ChartAccess::class);
        $access->method('getAllowedCharts')->willReturn([
            'dead_stock' => self::chart('dead_stock'),
            'orders' => self::chart('orders'),
        ]);

        return new Save(
            $this->context($params),
            $this->jsonFactory(),
            new Json(),
            $access,
            $preferences,
            $this->layoutResolver(),
        );
    }

    private function layoutResolver(): LayoutResolver
    {
        $resolver = $this->createStub(LayoutResolver::class);
        $resolver->method('getLayout')->willReturn([['id' => 'resolved', 'opened' => true]]);
        // Max opened: 1.
        $resolver->method('capOpened')->willReturnCallback(static function (array $layout): array {
            $remaining = 1;

            return array_map(static function (array $entry) use (&$remaining): array {
                $entry['opened'] = $entry['opened'] && $remaining-- > 0;

                return $entry;
            }, $layout);
        });

        return $resolver;
    }
}
