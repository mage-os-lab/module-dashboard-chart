<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Controller\Adminhtml\Chart;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json;
use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Dashboard\LayoutResolver;
use MageOS\DashboardChart\Model\Dashboard\UserPreferences;

/**
 * Saves the current admin's chart layout (`layout`: JSON list of {id, opened}) in admin_user.extra.
 * Charts the admin may not see are dropped and only the first "max opened" charts stay opened.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = ChartAccess::PERSONALIZE_RESOURCE;

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly Json $serializer,
        private readonly ChartAccess $chartAccess,
        private readonly UserPreferences $preferences,
        private readonly LayoutResolver $layoutResolver,
    ) {
        parent::__construct($context);
    }

    public function execute(): JsonResult
    {
        $result = $this->resultJsonFactory->create();
        try {
            $layout = $this->serializer->unserialize((string) $this->getRequest()->getParam('layout'));
        } catch (\InvalidArgumentException) {
            $layout = null;
        }
        if (!\is_array($layout)) {
            return $result->setHttpResponseCode(400)
                ->setData(['error' => true, 'message' => (string) __('The chart layout is malformed.')]);
        }

        $allowed = $this->chartAccess->getAllowedCharts();
        $entries = [];
        foreach ($layout as $entry) {
            if (\is_array($entry) && isset($allowed[(string) ($entry['id'] ?? '')])) {
                $entries[] = ['id' => (string) $entry['id'], 'opened' => (bool) ($entry['opened'] ?? false)];
            }
        }
        $this->preferences->save($this->layoutResolver->capOpened($entries));

        return $result->setData(['layout' => $this->layoutResolver->getLayout(), 'personalized' => true]);
    }
}
