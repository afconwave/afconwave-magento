<?php

namespace AfconWave\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;

class Success extends Action
{
    protected $checkoutSession;

    public function __construct(Context $context, CheckoutSession $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
    }

    public function execute()
    {
        // Clear the checkout session since payment is complete
        $this->checkoutSession->clearQuote();
        $this->_redirect('checkout/onepage/success');
    }
}
