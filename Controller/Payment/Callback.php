<?php
/**
 * AfconWave Webhook Callback Controller
 * Receives payment status updates from AfconWave, verifies HMAC signature,
 * and updates the Magento order status accordingly.
 */

namespace AfconWave\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Callback extends Action implements CsrfAwareActionInterface
{
    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        Context $context,
        OrderFactory $orderFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->orderFactory = $orderFactory;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    /**
     * Disable Magento's CSRF check for this webhook endpoint
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Disable Magento's CSRF check for this webhook endpoint
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Handle incoming AfconWave webhook
     */
    public function execute()
    {
        $rawBody = $this->getRequest()->getContent();
        $signature = $this->getRequest()->getHeader('X-AfconWave-Signature');

        // 1. Get webhook secret from Magento config
        $webhookSecret = $this->scopeConfig->getValue(
            'payment/afconwave_gateway/webhook_secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        // 2. Verify HMAC-SHA256 Signature
        $expectedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);

        if (!hash_equals($expectedSignature, (string) $signature)) {
            $this->getResponse()->setStatusCode(401)->setBody('Unauthorized: Invalid signature');
            return;
        }

        // 3. Parse payload
        $payload = json_decode($rawBody, true);
        $eventType = $payload['type'] ?? null;
        $magentoOrderId = $payload['data']['metadata']['magento_order_id'] ?? null;
        $afconwaveReference = $payload['data']['id'] ?? null;

        if (!$magentoOrderId || !$eventType) {
            $this->getResponse()->setStatusCode(400)->setBody('Bad Request: Missing data');
            return;
        }

        // 4. Load the Magento order
        $order = $this->orderFactory->create()->load($magentoOrderId);

        if (!$order->getId()) {
            $this->getResponse()->setStatusCode(404)->setBody('Order not found');
            return;
        }

        // 5. Update order status based on AfconWave event
        switch ($eventType) {
            case 'payment.success':
                $payment = $order->getPayment();
                $payment->setAdditionalInformation('afconwave_reference', $afconwaveReference);
                $payment->capture();
                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)->setStatus('processing');
                $order->addStatusHistoryComment("AfconWave payment confirmed. Reference: {$afconwaveReference}");
                $order->save();
                break;

            case 'payment.failed':
                $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED)->setStatus('canceled');
                $order->addStatusHistoryComment("AfconWave payment failed. Reference: {$afconwaveReference}");
                $order->save();
                break;
        }

        // Always return 200 to AfconWave to prevent retries
        $this->getResponse()->setStatusCode(200)->setBody('OK');
    }
}
