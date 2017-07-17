<?php

/*******************************************************************
 * G2A Pay gateway module for WHMCS.
 * (c) 2015 G2A.COM
 ******************************************************************/

// json_encode in PHP < 5.2
if (!function_exists('json_encode')) {
    require_once 'JSON.php';
    function json_encode($data)
    {
        $json = new Services_JSON();

        return $json->encode($data);
    }
}

// json_decode in PHP < 5.2
if (!function_exists('json_decode')) {
    require_once 'JSON.php';
    function json_decode($data)
    {
        $json = new Services_JSON();

        return $json->decode($data);
    }
}
