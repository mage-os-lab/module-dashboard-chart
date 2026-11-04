<?php

declare(strict_types=1);

namespace MageOS\DashboardChart\Controller\Adminhtml\Chart;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use MageOS\DashboardChart\Model\Chart\ChartAccess;
use MageOS\DashboardChart\Model\Dashboard\LayoutResolver;
use MageOS\DashboardChart\Model\Dashboard\UserPreferences;

/**
 * Drops the current admin's saved chart layout: the role defaults of the configuration apply again.
 */
class Reset extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = ChartAccess::PERSONALIZE_RESOURCE;

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly UserPreferences $preferences,
        private readonly LayoutResolver $layoutResolver,
    ) {
        parent::__construct($context);
    }

    public function execute(): JsonResult
    {
        $this->preferences->reset();

        return $this->resultJsonFactory->create()
            ->setData(['layout' => $this->layoutResolver->getLayout(), 'personalized' => false]);
    }
}
