<?php

namespace AfconWave\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(PaymentHelper $paymentHelper)
    {
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $method = $this->paymentHelper->getMethodInstance(\AfconWave\Payment\Model\Payment\AfconWave::CODE);
        
        return [
            'payment' => [
                \AfconWave\Gateway\Model\Payment\AfconWave::CODE => [
                    'isActive' => $method->isActive(),
                    'title' => $method->getConfigData('title'),
                    'logoUrl' => $method->getConfigData('logo_url') ?: ''
                ]
            ]
        ];
    }
}
