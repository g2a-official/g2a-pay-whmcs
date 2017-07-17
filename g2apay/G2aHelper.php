<?php

/*******************************************************************
 * G2A Pay gateway module for WHMCS.
 * (c) 2015 G2A.COM
 ******************************************************************/
use Illuminate\Database\Capsule\Manager as Capsule;

class G2aHelper
{
    const GATE_NAME            = 'g2apay';
    const G2A_URL_TEST         = 'https://www.test.pay.g2a.com/';
    const G2A_URL_PROD         = 'https://pay.g2a.com/';
    const GATE_URL_TEST        = 'https://checkout.test.pay.g2a.com/index/';
    const GATE_URL_PROD        = 'https://checkout.pay.g2a.com/index/';
    const GATE_URL_REST_TEST   = 'https://www.test.pay.g2a.com/rest/transactions/';
    const GATE_URL_REST_PROD   = 'https://pay.g2a.com/rest/transactions/';

    const STATUS_COMPLETE         = 'complete';
    const STATUS_REJECTED         = 'rejected';
    const STATUS_CANCELLED        = 'cancelled';
    const STATUS_PENDING          = 'pending';
    const STATUS_REFUNDED         = 'refunded';
    const STATUS_UNKNOWN          = 'unknown';
    const STATUS_PARTIAL_REFUNDED = 'partial_refunded';

    const ADMIN_USER                   = 'admin';
    const CART_PAGE                    = 'cart.php';
    const PORT_HTTP                    = 80;
    const PORT_HTTPS                   = 443;
    const RESPONSE_ERROR               = 'error';
    const RESPONSE_OK                  = 'ok';
    const RESPONSE_ON                  = 'on';

    const ERROR_TEXT                   = 'ERROR: ';
    const STATUS_PARTIAL_REFUNDED_TEXT = 'Partially refunded';
    const INCLUSIVE_TAX_NAME           = 'inclusive';
    const DEFAULT_TAX_NAME             = 'Tax';

    const MODULE_DIR = '/modules/gateways/';

    public static $allowedStatuses = [
        self::STATUS_COMPLETE,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_PENDING,
        self::STATUS_REFUNDED,
        self::STATUS_PARTIAL_REFUNDED,
    ];

    /**
     * Verify hash for security.
     *
     * @param $postData
     * @param $gateway
     * @return bool
     */
    public static function isHashValid($postData, $gateway)
    {
        $hash = hash('sha256', $postData['transactionId'] . $postData['userOrderId'] . $postData['amount'] . htmlspecialchars_decode($gateway['secret']));

        return $hash === $postData['hash'];
    }

    /**
     * Send Local API query using default system admin username.
     * @param $command
     * @param $values
     * @return mixed
     * @throws Exception
     */
    public static function sendLocalApiQuery($command, $values, $adminUser)
    {
        $response = localAPI($command, $values, $adminUser);

        if (!isset($response['status']) || $response['status'] === self::RESPONSE_ERROR) {
            throw new Exception('Invalid result from local API.');
        }

        return $response;
    }

    /**
     * Returns items list for G2A Pay from invoice.
     * @param $data
     * @param $url
     * @return array
     * @throws Exception
     */
    public static function getItemsFromLocalApi($data, $url)
    {
        $response = [];

        if (!isset($data['items'])) {
            throw new Exception('Invalid input data format to get items list.');
        }

        foreach ($data['items'] as $item) {
            $response += self::prepareItems($item, $url);
        }

        return $response;
    }

    /**
     * Prepare array with single item details.
     * @param $input
     * @param $url
     * @return array $response
     */
    private static function prepareItems($input, $url)
    {
        $response = [];

        foreach ($input as $row) {
            $response[] = [
                    'qty'    => 1,
                    'name'   => $row['description'],
                    'sku'    => $row['id'],
                    'amount' => $row['amount'],
                    'price'  => $row['amount'],
                    'id'     => $row['id'],
                    'url'    => $url . '/cart.php',
            ];
        }

        return $response;
    }

    /**
     * Check that params array specified for action is valid.
     * @param $requiredParams
     * @param $params
     * @return bool
     * @throws Exception
     */
    public static function validateParams($requiredParams, $params)
    {
        foreach ($requiredParams as $key) {
            if (!array_key_exists($key, $params)) {
                throw new Exception('Invalid input params - missing required values.');
            }
        }

        return true;
    }

    /**
     * get tax type from configuration table.
     *
     * @return string
     */
    public static function getTaxType()
    {
        $taxType = Capsule::table('tblconfiguration')->where('setting', 'TaxType')->first();

        return $taxType->value;
    }

    /**
     * get tax name by tax level applied for specified country and state.
     *
     * @param $taxLevel
     * @param string $countryCode
     * @param string $stateCode
     * @return string
     */
    public static function getTaxName($taxLevel, $countryCode = '', $stateCode = '')
    {
        $taxName = Capsule::table('tbltax')
            ->where('level', $taxLevel)
            ->where('country', $countryCode)
            ->where('state', $stateCode)
            ->first();

        if (!empty($taxName->name)) {
            return $taxName->name;
        }

        if (empty($taxName->name) && empty($countryCode) && empty($stateCode)) {
            return self::DEFAULT_TAX_NAME;
        }

        return empty($stateCode) ? self::getTaxName($taxLevel) : self::getTaxName($taxLevel, $countryCode);
    }
}
