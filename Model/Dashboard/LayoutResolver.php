<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Dashboard;

use Magento\Backend\Model\Auth\Session;
use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Config;

/**
 * The charts shown on the current admin's dashboard, in order, with their opened state.
 *
 * Saved preferences win (when the admin may personalize); otherwise the configuration rows matching the admin's
 * role (or every role). Charts the admin may not see are always dropped, so a role change or an ACL change never
 * exposes a chart through an old layout, and only the first "max opened" charts are opened (see {@see self::capOpened()}).
 */
class LayoutResolver
{
    public function __construct(
        private readonly Config $config,
        private readonly UserPreferences $preferences,
        private readonly ChartAccess $chartAccess,
        private readonly Session $authSession,
    ) {
    }

    /**
     * @return array<int,array{id: string, opened: bool}>
     */
    public function getLayout(): array
    {
        $saved = $this->chartAccess->canPersonalize() ? $this->preferences->get() : null;

        return $this->capOpened($this->filterAllowed($saved ?? $this->getDefaultLayout()));
    }

    /**
     * Keeps only the first {@see Config::getMaxOpened()} opened charts opened, in layout order.
     *
     * @param array<int,array{id: string, opened: bool}> $layout
     * @return array<int,array{id: string, opened: bool}>
     */
    public function capOpened(array $layout): array
    {
        $remaining = $this->config->getMaxOpened();
        foreach ($layout as &$entry) {
            if ($entry['opened']) {
                $entry['opened'] = $remaining-- > 0;
            }
        }

        return $layout;
    }

    public function isPersonalized(): bool
    {
        return $this->chartAccess->canPersonalize() && $this->preferences->get() !== null;
    }

    /**
     * Configuration rows for the admin's role (first row of a chart wins), allowed charts only.
     *
     * @return array<int,array{id: string, opened: bool}>
     */
    public function getDefaultLayout(): array
    {
        $roleId = (string) ($this->authSession->getUser()?->getRole()?->getId() ?? '');
        $layout = [];
        foreach ($this->config->getDefaultRows() as $row) {
            if (($row['role'] === Config::ALL_ROLES || $row['role'] === $roleId) && !isset($layout[$row['chart']])) {
                $layout[$row['chart']] = ['id' => $row['chart'], 'opened' => $row['opened']];
            }
        }

        return $this->capOpened($this->filterAllowed(array_values($layout)));
    }

    /**
     * @param array<int,array{id: string, opened: bool}> $layout
     * @return array<int,array{id: string, opened: bool}>
     */
    private function filterAllowed(array $layout): array
    {
        $allowed = $this->chartAccess->getAllowedCharts();

        return array_values(array_filter($layout, static fn (array $entry): bool => isset($allowed[$entry['id']])));
    }
}
