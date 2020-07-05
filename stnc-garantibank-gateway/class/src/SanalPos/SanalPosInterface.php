<?php


namespace SanalPos;

interface SanalPosInterface {
    public function setCard($number, $expMonth, $expYear, $cvv);
    public function setOrder($orderId, $customerEmail, $total, $taksit = '', $extra = []);

    public function check();

    public function getMode();
    public function setMode($mode);

    public function getServer();

    public function pay($pre = false);
    public function postAuth($orderId);
    public function refund($orderId, $amount = NULL);
    public function cancel($orderId);
} 