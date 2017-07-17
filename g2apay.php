<?php

/*******************************************************************
 * G2A Pay gateway module for WHMCS.
 * (c) 2015 G2A.COM
 ******************************************************************/

require_once 'g2apay/G2aHelper.php';
require_once 'g2apay/G2aForm.php';
require_once 'g2apay/G2aLog.php';
require_once 'g2apay/G2aEnv.php';

/**
 * Set configuration gateway.
 *
 * @return array
 */
function g2apay_config()
{
    $ipn = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $ipn .= $_SERVER['SERVER_NAME'];

    if ($_SERVER['SERVER_PORT'] != G2aHelper::PORT_HTTP && $_SERVER['SERVER_PORT'] != G2aHelper::PORT_HTTPS) {
        $ipn .= ':' . $_SERVER['SERVER_PORT'];
    }

    $ipn .= '/modules/gateways/callback/g2apay.php';

    $configArray = [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'G2A Pay',
        ],
        'api_hash' => [
            'FriendlyName' => 'API Hash',
            'Type'         => 'text',
            'Size'         => '40',
            'Description'  => sprintf('Get "Your API hash" from %s', '<a href="' . G2aHelper::G2A_URL_PROD . 'setting/merchant">' . G2aHelper::G2A_URL_PROD . 'setting/merchant</a>'),
        ],
        'secret' => [
            'FriendlyName' => 'Secret',
            'Type'         => 'text',
            'Size'         => '40',
            'Description'  => sprintf('Get "Your Secret" from %s', '<a href="' . G2aHelper::G2A_URL_PROD . 'setting/merchant">' . G2aHelper::G2A_URL_PROD . 'setting/merchant</a>'),
        ],
        'merchant_email' => [
            'FriendlyName' => 'Merchant e-mail',
            'Type'         => 'text',
            'Size'         => '20',
            'Description'  => 'This e-mail address is login name to your account G2APay.',
        ],
        'admin_user' => [
            'FriendlyName' => 'Administrator user',
            'Type'         => 'text',
            'Size'         => '20',
            'Description'  => 'This is WHMCS user name who have administrator rights (local API operations).',
            'Default'      => 'admin',
        ],
        'test_mode' => [
            'FriendlyName' => 'Test mode',
            'Type'         => 'yesno',
            'Description'  => sprintf('Test mode (SandBox mode) <br>Enter the "Your IPN URI" in the configuration of your store in %s: <code>' . $ipn . '</code>', '<a href="' . G2aHelper::G2A_URL_PROD . 'setting/merchant">G2APay</a>'),
        ],
        'enable_logs' => [
            'FriendlyName' => 'Enable logs',
            'Type'         => 'yesno',
            'Description'  => 'Decide that logs will be enabled or not.',
        ],
    ];

    return $configArray;
}

/**
 * Third Party Gateways - generate html form button/link.
 *
 * @param $params
 * @return string
 */
function g2apay_link($params)
{
    try {
        G2aHelper::validateParams(
            ['test_mode', 'systemurl', 'clientdetails', 'api_hash', 'invoiceid', 'amount', 'currency', 'secret', 'description', 'returnurl', 'admin_user'],
            $params
        );

        $checkoutUrl = G2aHelper::GATE_URL_PROD;
        if ($params['test_mode'] == G2aHelper::RESPONSE_ON) {
            $checkoutUrl = G2aHelper::GATE_URL_TEST;
        }

        $apiParams['invoiceid']  = $params['invoiceid'];
        $apiQueryResult          = G2aHelper::sendLocalApiQuery('getinvoice', $apiParams, $params['admin_user']);
        $orderItems              = G2aHelper::getItemsFromLocalApi($apiQueryResult, $params['systemurl']);

        $paymentForm = new G2aForm($checkoutUrl);
        $paymentForm->setParams($params);
        $paymentForm->addField('email', $params['clientdetails']['email']);
        $paymentForm->addField('api_hash', $params['api_hash']);
        $paymentForm->addField('hash', hash('sha256', $params['invoiceid'] . $params['amount'] . $params['currency'] . $params['api_secret']));
        $paymentForm->addField('amount', $params['amount']);
        $paymentForm->addField('currency', $params['currency']);

        foreach ($orderItems as $key => $item) {
            $paymentForm->addFieldItem($key, 'sku', $item['sku']);
            $paymentForm->addFieldItem($key, 'name', $item['name']);
            $paymentForm->addFieldItem($key, 'amount', $item['amount']);
            $paymentForm->addFieldItem($key, 'qty', $item['qty']);
            $paymentForm->addFieldItem($key, 'id', $item['id']);
            $paymentForm->addFieldItem($key, 'price', $item['price']);
            $paymentForm->addFieldItem($key, 'url', $item['url']);
        }

        $paymentForm->addField('description', $params['description']);
        $paymentForm->addField('order_id', $params['invoiceid']);
        $paymentForm->addField('url_failure', $params['returnurl']);
        $paymentForm->addField('url_ok', $params['returnurl']);

        if (strtolower(G2aHelper::getTaxType()) === G2aHelper::INCLUSIVE_TAX_NAME) {
            return $paymentForm->render();
        }

        $countryCode = $params['clientdetails']['countrycode'];
        $stateCode   = $params['clientdetails']['fullstate'];

        if ($apiQueryResult['tax'] > 0) {
            ++$key;
            $paymentForm->addFieldItem($key, 'sku', 'taxlvl1');
            $paymentForm->addFieldItem($key, 'name', G2aHelper::getTaxName(1, $countryCode, $stateCode));
            $paymentForm->addFieldItem($key, 'amount', $apiQueryResult['tax']);
            $paymentForm->addFieldItem($key, 'qty', 1);
            $paymentForm->addFieldItem($key, 'id', 1);
            $paymentForm->addFieldItem($key, 'price', $apiQueryResult['tax']);
            $paymentForm->addFieldItem($key, 'url', $params['systemurl']);
        }

        if ($apiQueryResult['tax2'] > 0) {
            ++$key;
            $paymentForm->addFieldItem($key, 'sku', 'taxlvl2');
            $paymentForm->addFieldItem($key, 'name', G2aHelper::getTaxName(2, $countryCode, $stateCode));
            $paymentForm->addFieldItem($key, 'amount', $apiQueryResult['tax2']);
            $paymentForm->addFieldItem($key, 'qty', 1);
            $paymentForm->addFieldItem($key, 'id', 1);
            $paymentForm->addFieldItem($key, 'price', $apiQueryResult['tax2']);
            $paymentForm->addFieldItem($key, 'url', $params['systemurl']);
        }

        return $paymentForm->render();
    } catch (Exception $e) {
        $response = G2aHelper::ERROR_TEXT . $e->getMessage();

        $log = new G2aLog($params);
        $log->save($response);

        return $response;
    }
}

/**
 * Refund gateway method.
 *
 * @param $params
 * @return array
 */
function g2apay_refund($params)
{
    try {
        G2aHelper::validateParams(['amount', 'transid', 'invoiceid', 'secret', 'api_hash', 'merchant_email'], $params);
        $amount = number_format(round($params['amount'], 2), 2, '.', '');

        $putData = [
            'action' => 'refund',
            'amount' => $amount,
            'hash'   => hash('sha256', $params['transid'] . $params['invoiceid'] . $amount . $amount . htmlspecialchars_decode($params['secret'])),
        ];

        $restUrl = G2aHelper::GATE_URL_REST_PROD;
        if ($params['test_mode'] == G2aHelper::RESPONSE_ON) {
            $restUrl = G2aHelper::GATE_URL_REST_TEST;
        }

        $ch = curl_init($restUrl . $params['transid']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($putData));
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Authorization: ' . $params['api_hash'] . ';' . hash('sha256', $params['api_hash'] . $params['merchant_email'] . htmlspecialchars_decode($params['secret']))]
        );
        $response = curl_exec($ch);
        curl_close($ch);

        if (curl_error($ch)) {
            throw new Exception('Failed to connect with cURL. Reason: ' . curl_errno($ch) . curl_error($ch));
        }

        $response = json_decode($response, true);

        if (empty($response['status']) || $response['status'] !== G2aHelper::RESPONSE_OK) {
            throw new Exception('Invalid response from API (operation declined or connection error).');
        }

        return ['status' => 'success', 'transid' => $response['transactionId'], 'rawdata' => $response];
    } catch (Exception $e) {
        $log = new G2aLog($params);
        $log->save($e->getMessage());

        return ['status' => 'declined', 'rawdata' => $e->getMessage()];
    }
}
