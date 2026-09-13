<?php
declare(strict_types=1);
function cciValidSignature(array $donation, array $body, string $secret): bool {
    $storedOrder = $donation['razorpay_order_id'] ?? '';
    if (!$storedOrder || $storedOrder !== ($body['razorpay_order_id'] ?? '')) return false;
    $expected = hash_hmac('sha256', $storedOrder . '|' . ($body['razorpay_payment_id'] ?? ''), $secret);
    return hash_equals($expected, $body['razorpay_signature'] ?? '');
}
function cciValidatePayment(array $donation, array $payment): void {
    if (($payment['order_id'] ?? '') !== ($donation['razorpay_order_id'] ?? null) ||
        (int)($payment['amount'] ?? -1) !== (int)$donation['amount_paise'] ||
        ($payment['currency'] ?? '') !== $donation['currency'] ||
        !in_array($payment['status'] ?? '', ['captured','refunded'], true) ||
        ($payment['captured'] ?? false) !== true ||
        !is_string($payment['id'] ?? null) || !preg_match('/^pay_[A-Za-z0-9]+$/',$payment['id']) ||
        (int)($payment['amount_refunded'] ?? 0) < 0 ||
        (int)($payment['amount_refunded'] ?? 0) > (int)$donation['amount_paise']) {
        throw new RuntimeException('Payment does not match the stored donation');
    }
}
