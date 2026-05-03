<?php
/**
 * AfconWave Magento 2 Payment Gateway
 *
 * @module      AfconWave_Payment
 * @copyright   Copyright (c) AfconWave Ltd. (https://afconwave.com)
 * @license     GPLv3
 */

namespace AfconWave\Payment\Model\Payment;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment;

class AfconWave extends AbstractMethod
{
    const CODE = 'afconwave_gateway';

    /**
     * Payment method code (matches etc/config.xml)
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Enables redirect-based checkout (buyer is sent to hosted page)
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_canAuthorize = true;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canVoid = false;

    /**
     * @var bool
     */
    protected $_canUseCheckout = true;

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $curl;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

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

    /**
     * Get AfconWave API base URL depending on sandbox mode
     */
    private function getApiUrl(): string
    {
        $sandboxMode = $this->getConfigData('sandbox_mode');
        return $sandboxMode
            ? 'https://sandbox.api.afconwave.com/v1'
            : 'https://api.afconwave.com/v1';
    }

    /**
     * Get configured API secret key
     */
    private function getSecretKey(): string
    {
        return (string) $this->getConfigData('secret_key');
    }

    /**
     * Creates an AfconWave payment session.
     * Called by the redirect controller when buyer clicks "Place Order".
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return string The AfconWave hosted checkout URL
     * @throws LocalizedException
     */
    public function createCheckoutSession($order): string
    {
        $baseUrl = $this->getApiUrl();
        $secretKey = $this->getSecretKey();

        // Convert to minor units (e.g. 10.50 -> 1050)
        $amount = (int) round((float) $order->getGrandTotal() * 100);

        $payload = json_encode([
            'amount'        => $amount,
            'currency'      => $order->getOrderCurrencyCode(),
            'description'   => 'Magento Order #' . $order->getIncrementId(),
            'customer_email'=> $order->getCustomerEmail(),
            'callback_url'  => $order->getStore()->getBaseUrl() . 'afconwave/payment/success?order_id=' . $order->getId(),
            'metadata'      => [
                'magento_order_id'          => $order->getId(),
                'magento_order_increment_id' => $order->getIncrementId(),
            ],
        ]);

        $this->curl->addHeader('Authorization', 'Bearer ' . $secretKey);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->post($baseUrl . '/payments', $payload);

        $response = json_decode($this->curl->getBody(), true);

        if (empty($response['data']['checkout_url'])) {
            $error = $response['error'] ?? 'Unknown error';
            throw new LocalizedException(__('AfconWave: Failed to create payment session — ' . $error));
        }

        return $response['data']['checkout_url'];
    }

    /**
     * Process a capture — called programmatically after webhook confirms payment.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        /** @var Payment $payment */
        $payment->setTransactionId($payment->getAdditionalInformation('afconwave_reference'));
        $payment->setIsTransactionClosed(true);
        return $this;
    }

    /**
     * Process a refund via AfconWave API.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws LocalizedException
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $paymentId = $payment->getAdditionalInformation('afconwave_reference');
        $secretKey = $this->getSecretKey();

        $this->curl->addHeader('Authorization', 'Bearer ' . $secretKey);
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
