<?php

namespace AfconWave\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Callback extends Action implements CsrfAwareActionInterface
{
    protected $orderFactory;
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

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    public function execute()
    {
        $rawBody = $this->getRequest()->getContent();
        $signature = $this->getRequest()->getHeader('X-AfconWave-Signature');

        $webhookSecret = $this->scopeConfig->getValue(
            'payment/afconwave_gateway/webhook_secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $expectedSignature = hash_hmac('sha256', $rawBody, (string) $webhookSecret);

        if (!hash_equals($expectedSignature, (string) $signature)) {
            $this->getResponse()->setStatusCode(401)->setBody('Unauthorized: Invalid signature');
            return;
        }

        $payload = json_decode($rawBody, true);
        $eventType = $payload['type'] ?? $payload['event'] ?? null;
        $magentoOrderId = $payload['data']['metadata']['magento_order_id'] ?? null;
        $afconwaveReference = $payload['data']['id'] ?? null;

        if (isset($payload['timestamp'])) {
            $ts = (int) $payload['timestamp'];
            $webhookTime = $ts > 10000000000 ? (int) ($ts / 1000) : $ts;
            if (abs(time() - $webhookTime) > 300) {
                $this->getResponse()->setStatusCode(401)->setBody('Timestamp tolerance exceeded');
                return;
            }
        }

        if (!$magentoOrderId || !$eventType) {
            $this->getResponse()->setStatusCode(400)->setBody('Bad Request: Missing data');
            return;
        }

        $order = $this->orderFactory->create()->load($magentoOrderId);

        if (!$order->getId()) {
            $this->getResponse()->setStatusCode(404)->setBody('Order not found');
            return;
        }

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

        $this->getResponse()->setStatusCode(200)->setBody('OK');
    }
}
