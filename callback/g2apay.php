<?php

/*******************************************************************
 * G2A Pay gateway module for WHMCS.
 * (c) 2015 G2A.COM
 ******************************************************************/

require_once '../../../init.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/gatewayfunctions.php';
require_once '../../../includes/invoicefunctions.php';
require_once '../g2apay/G2aHelper.php';
require_once '../g2apay/G2aLog.php';
require_once '../g2apay/G2aEnv.php';

try {
    $gatewayData = getGatewayVariables(G2aHelper::GATE_NAME);

    if (!isset($gatewayData['type'])) {
        throw new Exception('Module not activated yet.');
    }

    G2aHelper::validateParams(['transactionId', 'userOrderId', 'amount', 'hash'], $_POST);

    $invoiceId = checkCbInvoiceID($_POST['userOrderId'], $gatewayData['name']);
    checkCbTransID($_POST['transactionId']);

    if (!G2aHelper::isHashValid($_POST, $gatewayData)) {
        throw new Exception('Invalid hash for this transaction.');
    }

    $status     = in_array($_POST['status'], G2aHelper::$allowedStatuses) ? $_POST['status'] : ucfirst(G2aHelper::STATUS_UNKNOWN);
    $statusText = $status === G2aHelper::STATUS_PARTIAL_REFUNDED ? G2aHelper::STATUS_PARTIAL_REFUNDED_TEXT : ucfirst($status);

    logTransaction($gatewayData['name'], $_POST, $statusText);

    if ($status === G2aHelper::STATUS_COMPLETE) {
        addInvoicePayment($invoiceId, $_POST['transactionId'], $_POST['amount'], $_POST['provisionAmount'], G2aHelper::GATE_NAME);
    }

    return 'Change status to: ' . $status;
} catch (Exception $e) {
    $response = $e->getMessage();
    $log      = new G2aLog($gatewayData);
    $log->save($response);

    return $response;
}
