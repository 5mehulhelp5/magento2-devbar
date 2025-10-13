<?php
/**
 * @copyright   perfcom.dev - https://perfcom.dev
 */

declare(strict_types=1);

namespace Perfcom\Devbar\Controller\User;

use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

class Login implements HttpGetActionInterface
{
    const COOKIE_NAME = 'private_content_version';
    const COOKIE_PERIOD = 315360000;

    public function __construct(
        private readonly CollectionFactory $customerCollectionFactory,
        private readonly RequestInterface $request,
        private readonly Session $customerSession,
        private readonly ManagerInterface $messageManager,
        private readonly CookieMetadataFactory  $cookieMetadataFactory,
        private readonly CookieManagerInterface $cookieManager,
        private readonly ResultFactory $resultFactory,
    )
    {}

    public function execute()
    {
        $collection = $this->customerCollectionFactory->create();
        $collection->getSelect()->orderRand()->limit(1);

        if ($customer = $collection->getFirstItem()) {
            $this->customerSession->setCustomerAsLoggedIn($customer);
            $this->messageManager->addSuccessMessage('Logged in as: ' . $customer->getEmail());
        }

        $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDuration(self::COOKIE_PERIOD)
            ->setPath('/')
            ->setSecure($this->request->isSecure())
            ->setHttpOnly(false);
        $this->cookieManager->setPublicCookie(self::COOKIE_NAME, $this->generateValue(), $publicCookieMetadata);

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('/');
    }

    protected function generateValue(): string
    {
        //phpcs:ignore
        return md5(rand() . time());
    }
}
