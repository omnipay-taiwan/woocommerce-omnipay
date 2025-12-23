<?php

namespace WooCommerceOmnipay\Gateways;

use WooCommerceOmnipay\Adapters\ECPayAdapter;

/**
 * ECPay Gateway
 *
 * 處理 ECPay 特有的邏輯，包含 ATM/CVS/BARCODE 付款資訊
 */
class ECPayGateway extends OmnipayGateway
{
    /**
     * 處理統一的 callback 結果
     *
     * ECPay 有額外的信用卡資訊儲存與模擬付款處理
     */
    protected function handleCallbackResult(array $result)
    {
        // 處理付款資訊通知
        if ($result['isPaymentInfo']) {
            $order = $this->orders->findByTransactionId($result['transactionId']);

            if ($order) {
                $this->savePaymentInfo($order, $result['data']);
            }

            $this->sendCallbackResultResponse($result, true);

            return;
        }

        $order = $this->orders->findByTransactionIdOrFail($result['transactionId']);

        // 金額驗證
        if (! $this->adapter->validateAmount($result['data'], (int) $order->get_total())) {
            $this->sendCallbackResponse(false, 'Amount mismatch');

            return;
        }

        // 儲存信用卡資訊
        $this->saveCreditCardInfo($order, $result['data']);

        // 模擬付款處理：不改變訂單狀態
        if ($this->isSimulatedPayment($result['data'])) {
            $this->orders->addNote($order, __('ECPay simulated payment (SimulatePaid=1)', 'woocommerce-omnipay'));
            $this->sendCallbackResultResponse($result, true);

            return;
        }

        if (! $this->shouldProcessOrder($order)) {
            $this->sendCallbackResponse(true);

            return;
        }

        if (! $result['isSuccessful']) {
            $errorMessage = $result['message'] ?: 'Payment failed';
            $this->onPaymentFailed($order, $errorMessage, 'callback', false);
            $this->sendCallbackResponse(false, $errorMessage);

            return;
        }

        $this->completeOrderPayment($order, $result['transactionReference'], 'callback');
        $this->sendCallbackResultResponse($result, true);
    }

    /**
     * 檢查是否為模擬付款
     */
    protected function isSimulatedPayment(array $data): bool
    {
        return $this->adapter instanceof ECPayAdapter
            && $this->adapter->isSimulatedPayment($data);
    }

    /**
     * 儲存信用卡資訊
     */
    protected function saveCreditCardInfo($order, array $data): void
    {
        if (! $this->adapter instanceof ECPayAdapter) {
            return;
        }

        $cardInfo = $this->adapter->getCreditCardInfo($data);

        if (empty($cardInfo)) {
            return;
        }

        foreach ($cardInfo as $key => $value) {
            $order->update_meta_data('_omnipay_'.$key, $value);
        }

        $order->save();
    }

    /**
     * 處理付款資訊通知
     */
    protected function savePaymentInfo($order, array $data)
    {
        parent::savePaymentInfo($order, $data);

        $paymentType = $data['PaymentType'] ?? '';
        $this->orders->addNote($order, sprintf('ECPay 取號成功 (%s)，等待付款', $paymentType));
    }
}
