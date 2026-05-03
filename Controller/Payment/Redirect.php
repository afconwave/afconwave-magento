<?php
/**
 * AfconWave Payment Redirect Controller
 * Redirects the buyer from the Magento order page to the AfconWave hosted checkout.
 */

namespace AfconWave\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use AfconWave\Payment\Model\Payment\AfconWave as AfconWaveModel;

class Redirect extends Action
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var AfconWaveModel
     */
    protected $paymentModel;

    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        AfconWaveModel $paymentModel
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->paymentModel = $paymentModel;
        parent::__construct($context);
    }

    /**
     * Redirect buyer to AfconWave hosted checkout URL
     */
    public function execute()
    {
        try {
            $order = $this->checkoutSession->getLastRealOrder();

            if (!$order || !$order->getId()) {
                $this->messageManager->addErrorMessage(__('Order not found. Please try again.'));
                $this->_redirect('checkout/cart');
                return;
            }

            $checkoutUrl = $this->paymentModel->createCheckoutSession($order);

            $this->getResponse()->setRedirect($checkoutUrl);

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Payment failed: ' . $e->getMessage()));
            $this->_redirect('checkout/cart');
        }
    }
}
