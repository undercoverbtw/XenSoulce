<?php

namespace XenSoluce\InviteSystem\XF\Payment;

use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Mvc\Controller;
use XF\Purchasable\Purchase;

use XF\Payment\CallbackState;

class PayPal extends XFCP_PayPal
{
    public function completeTransaction(CallbackState $state)
    {
        $purchaseRequest = $state->getPurchaseRequest();
        if ($purchaseRequest &&
            !$purchaseRequest->user_id &&
            !empty($purchaseRequest->extra_data['email']) &&
            $purchaseRequest->purchasable_type_id == 'user_upgrade')
        {
            if ($state->paymentResult == CallbackState::PAYMENT_RECEIVED)
            {
                $invitation = \XF::em()->create('XenSoluce\InviteSystem:CodeInvitation');
                $invitation->user_id = 0;
                $invitation->token_id = 0;
                $invitation->token = '';
                $invitation->type_code = 6;
                $invitation->save();
                $purchaseRequest = $state->getPurchaseRequest();
                $extraData = $purchaseRequest->extra_data;
                $extraData['invitation_code'] = $invitation->code;
                $purchaseRequest->fastUpdate('extra_data', $extraData);

            }
        }
        parent::completeTransaction($state);
    }
}