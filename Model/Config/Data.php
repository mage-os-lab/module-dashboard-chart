<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Model\Config;

use Magento\Framework\Config\CacheInterface;
use Magento\Framework\Config\Data as FrameworkConfigData;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Cached, merged dashboard_charts.xml (see {@see Reader}).
 */
class Data extends FrameworkConfigData
{
    private const string CACHE_ID = 'mageos_dashboard_charts';

    public function __construct(
        Reader $reader,
        CacheInterface $cache,
        string $cacheId = self::CACHE_ID,
        ?SerializerInterface $serializer = null,
    ) {
        parent::__construct($reader, $cache, $cacheId, $serializer);
    }
}
