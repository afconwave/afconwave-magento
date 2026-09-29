<?php
namespace AfconWave\Payment\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment;

class AfconWave extends AbstractMethod
{
    const CODE = 'afconwave_gateway';
    protected $_code = self::CODE;
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canVoid = false;
    protected $_canUseCheckout = true;
    protected $curl;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\HTTP\Client\Curl $curl,
        array $data = []
    ) {
        $this->curl = $curl;
        parent::__construct(
            $context, $registry, $extensionFactory, $customAttributeFactory,
            $paymentData, $scopeConfig, $logger, null, null, $data
        );
    }

    private function getApiUrl(): string
    {
        $sandboxMode = $this->getConfigData('sandbox_mode');
        return $sandboxMode
            ? 'https://sandbox.api.afconwave.com/api/v1'
            : 'https://api.afconwave.com/api/v1';
    }

    private function getSecretKey(): string
    {
        return (string) $this->getConfigData('secret_key');
    }

    public function createCheckoutSession($order): string
    {
        $amount = (int) round((float) $order->getGrandTotal() * 100);
        $payload = json_encode([
            'amount' => $amount,
            'currency' => $order->getOrderCurrencyCode(),
            'description' => 'Magento Order #' . $order->getIncrementId(),
            'customer_email' => $order->getCustomerEmail(),
            'callback_url' => $order->getStore()->getBaseUrl() . 'afconwave/payment/success?order_id=' . $order->getId(),
            'metadata' => [
                'magento_order_id' => $order->getId(),
                'magento_order_increment_id' => $order->getIncrementId(),
            ],
        ]);

        $this->curl->addHeader('Authorization', 'Bearer ' . $this->getSecretKey());
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->post($this->getApiUrl() . '/payments', $payload);
        $response = json_decode($this->curl->getBody(), true);

        if (empty($response['data']['checkout_url'])) {
            throw new LocalizedException(__('AfconWave: Failed to create payment session — ' . ($response['error'] ?? 'Unknown error')));
        }
        return $response['data']['checkout_url'];
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /** @var Payment $payment */
        $payment->setTransactionId($payment->getAdditionalInformation('afconwave_reference'));
        $payment->setIsTransactionClosed(true);
        return $this;
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $paymentId = $payment->getAdditionalInformation('afconwave_reference');
        $this->curl->addHeader('Authorization', 'Bearer ' . $this->getSecretKey());
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->post(
            $this->getApiUrl() . '/refunds',
            json_encode(['paymentId' => $paymentId, 'amount' => $amount, 'reason' => 'Magento refund'])
        );
        $response = json_decode($this->curl->getBody(), true);
        if (empty($response['success'])) {
            throw new LocalizedException(__('AfconWave: Refund failed — ' . ($response['error'] ?? 'Unknown error')));
        }
        return $this;
    }
}
