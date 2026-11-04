<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Test\Unit\Model\Config\Source;

use ArrayIterator;
use Magento\Authorization\Model\ResourceModel\Role\Collection;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory;
use Magento\Authorization\Model\Role;
use MageOS\DashboardChart\Model\Config\Source\Roles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Roles::class)]
final class RolesTest extends TestCase
{
    public function testItListsAllRolesThenTheAdminRoles(): void
    {
        $role = new class() extends Role {
            public function __construct()
            {
            }
        };
        $role->setIdFieldName('role_id');
        $role->setData(['role_id' => 3, 'role_name' => 'Catalog managers']);

        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('setRolesFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new ArrayIterator([$role]));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        self::assertSame(
            [['value' => '*', 'label' => 'All Roles'], ['value' => '3', 'label' => 'Catalog managers']],
            (new Roles($factory))->toOptionArray()
        );
    }
}
