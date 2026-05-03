<?php

namespace AfconWave\Payment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

class Cancel extends Action
{
    public function execute()
    {
        $this->messageManager->addErrorMessage(__('Payment was cancelled. You can try again or choose a different method.'));
        $this->_redirect('checkout/cart');
    }
}
