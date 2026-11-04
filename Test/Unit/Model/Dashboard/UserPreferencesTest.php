<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model\Dashboard;

use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;
use MageOS\DashboardChart\Model\Dashboard\UserPreferences;
use MageOS\DashboardChart\Test\Unit\Helper\AdminSessionTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserPreferences::class)]
final class UserPreferencesTest extends TestCase
{
    use AdminSessionTestTrait;

    public function testItReadsNothingWhenTheAdminNeverPersonalized(): void
    {
        self::assertNull($this->preferences(self::user(1, ['configState' => ['a' => 1]]))->get());
        self::assertNull($this->preferences(self::user(1))->get());
        self::assertNull($this->preferences(null)->get());
    }

    public function testItReadsTheSavedLayoutNormalized(): void
    {
        $user = self::user(1, [UserPreferences::EXTRA_KEY => ['charts' => [
            ['id' => 'sales_overview', 'opened' => 1],
            ['id' => 'dead_stock'],
            ['id' => 'sales_overview', 'opened' => false],
            ['opened' => true],
            'junk',
        ]]]);

        self::assertSame(
            [['id' => 'sales_overview', 'opened' => true], ['id' => 'dead_stock', 'opened' => true]],
            $this->preferences($user)->get()
        );
    }

    public function testItSavesInTheFreshExtraAndKeepsTheOtherKeys(): void
    {
        $sessionUser = self::user(7, ['stale' => true]);
        $fresh = self::user(7, ['configState' => ['admin_dashboard' => '1']]);

        $this->preferences($sessionUser, $fresh)->save([['id' => 'dead_stock', 'opened' => false]]);

        $expected = [
            'configState' => ['admin_dashboard' => '1'],
            UserPreferences::EXTRA_KEY => ['charts' => [['id' => 'dead_stock', 'opened' => false]]],
        ];
        self::assertSame($expected, $fresh->savedExtra);
        self::assertSame($expected, $sessionUser->getData('extra'), 'The session user gets the merged extra.');
    }

    public function testItResetsByRemovingItsKeyOnly(): void
    {
        $fresh = self::user(7, ['configState' => [], UserPreferences::EXTRA_KEY => ['charts' => []]]);

        $this->preferences(self::user(7), $fresh)->reset();

        self::assertSame(['configState' => []], $fresh->savedExtra);
    }

    public function testItSavesNothingWithoutALoggedInAdmin(): void
    {
        $fresh = self::user(7);

        $this->preferences(null, $fresh)->save([['id' => 'dead_stock', 'opened' => true]]);

        self::assertNull($fresh->savedExtra);
    }

    private function preferences(?User $sessionUser, ?User $freshUser = null): UserPreferences
    {
        $factory = $this->createStub(UserFactory::class);
        $factory->method('create')->willReturn($freshUser ?? self::user(0));

        return new UserPreferences(self::session($sessionUser), $factory, $this->createStub(UserResource::class));
    }
}
