<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Dashboard;

use Magento\Backend\Model\Auth\Session;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;

/**
 * The chart layout an admin saved for themselves, kept in admin_user.extra under {@see self::EXTRA_KEY}.
 *
 * The other extra keys (e.g. configState) are preserved: the extra column is re-read from the database before
 * writing, and the session user gets the merged value so that later core saveExtra() calls keep this key.
 */
class UserPreferences
{
    public const string EXTRA_KEY = 'mageos_dashboard_charts';

    public function __construct(
        private readonly Session $authSession,
        private readonly UserFactory $userFactory,
        private readonly UserResource $userResource,
    ) {
    }

    /**
     * @return array<int,array{id: string, opened: bool}>|null Null when the admin never personalized the dashboard.
     */
    public function get(): ?array
    {
        $user = $this->authSession->getUser();
        $extra = $user?->getExtra();
        $charts = \is_array($extra) ? ($extra[self::EXTRA_KEY]['charts'] ?? null) : null;

        return \is_array($charts) ? $this->normalize($charts) : null;
    }

    /**
     * @param array<int,array{id: string, opened: bool}> $charts
     */
    public function save(array $charts): void
    {
        $this->write([self::EXTRA_KEY => ['charts' => $this->normalize($charts)]]);
    }

    public function reset(): void
    {
        $this->write([self::EXTRA_KEY => null]);
    }

    /**
     * @param array<string,mixed> $changes Null values remove the key.
     */
    private function write(array $changes): void
    {
        $sessionUser = $this->authSession->getUser();
        if ($sessionUser === null || !$sessionUser->getId()) {
            return;
        }

        /** @var User $user */
        $user = $this->userFactory->create();
        $this->userResource->load($user, $sessionUser->getId());
        $extra = $user->getExtra();
        $extra = \is_array($extra) ? $extra : [];
        foreach ($changes as $key => $value) {
            if ($value === null) {
                unset($extra[$key]);
            } else {
                $extra[$key] = $value;
            }
        }

        $user->saveExtra($extra);
        $sessionUser->setExtra($extra);
    }

    /**
     * @param array<mixed> $charts
     * @return array<int,array{id: string, opened: bool}>
     */
    private function normalize(array $charts): array
    {
        $result = [];
        foreach ($charts as $chart) {
            $id = \is_array($chart) ? (string) ($chart['id'] ?? '') : '';
            if ($id !== '' && !isset($result[$id])) {
                $result[$id] = ['id' => $id, 'opened' => (bool) ($chart['opened'] ?? true)];
            }
        }

        return array_values($result);
    }
}
