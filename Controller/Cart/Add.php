<?php
/**
 * @copyright   perfcom.dev - https://perfcom.dev
 */

declare(strict_types=1);

namespace Perfcom\Devbar\Controller\Cart;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;

class Add implements HttpGetActionInterface
{
    const COOKIE_NAME = 'private_content_version';
    const COOKIE_PERIOD = 315360000;
    private Quote $quote;

    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly RequestInterface $request,
        private readonly ManagerInterface $messageManager,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly Session $checkoutSession,
        private readonly QuoteRepository $quoteRepository,
        private readonly CookieMetadataFactory  $cookieMetadataFactory,
        private readonly CookieManagerInterface $cookieManager,
    )
    {
        $this->quote = $this->checkoutSession->getQuote();
    }

    public function execute()
    {
        $attempts = 0;
        $maxAttempts = 5;
        $productAdded = false;

        while ($attempts < $maxAttempts && !$productAdded) {
            $attempts++;

            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect('*')
                ->addAttributeToFilter('type_id', 'simple')
                ->addAttributeToFilter('status', 1);
            $collection->getSelect()->orderRand()->limit(1);

            if ($product = $collection->getFirstItem()) {
                try {
                    $this->quote->addProduct($product, 1);
                    $this->quoteRepository->save($this->quote);
                    $this->messageManager->addSuccessMessage('Added: ' . $product->getName());
                    $productAdded = true;
                } catch (\Exception $e) {
                    if ($attempts >= $maxAttempts) {
                        $this->messageManager->addErrorMessage('Failed to add product after ' . $maxAttempts . ' attempts');
                    }
                    // Continue to next attempt
                }
            } else {
                if ($attempts >= $maxAttempts) {
                    $this->messageManager->addErrorMessage('No products available to add');
                }
            }
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
