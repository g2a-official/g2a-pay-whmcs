<?php

/*******************************************************************
 * G2A Pay gateway module for WHMCS.
 * (c) 2015 G2A.COM
 ******************************************************************/

class G2aLog
{
    protected $params = [];

    /**
     * Initialize log class.
     * @param $params
     */
    public function __construct($params)
    {
        $this->setParams($params);
    }

    /**
     * Params setter.
     * @param $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * Params getter.
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Get single param value from all specified.
     * @param $param
     * @return mixed|void
     */
    public function getParam($param)
    {
        if (array_key_exists($param, $this->params) && !empty($this->params[$param])) {
            return $this->params[$param];
        }

        return;
    }

    /**
     * Get full file name where actual log will be stored.
     * @return string
     */
    public function getLogFileName()
    {
        return realpath(getcwd()) . G2aHelper::MODULE_DIR . G2aHelper::GATE_NAME . '/logs/g2apay_' . date('d-m-Y') . '.log';
    }

    /**
     * Check that logs are enabled.
     * @return bool
     */
    public function checkLogsEnabled()
    {
        return $this->getParam('enable_logs') === G2aHelper::RESPONSE_ON;
    }

    /**
     * Save single log to log file.
     * @param $message
     * @return string
     */
    public function save($message)
    {
        if (!$this->checkLogsEnabled()) {
            return;
        }

        $logFile = $this->getLogFileName();

        if (file_put_contents($logFile, date('d-m-Y H:i:s') . "\n" . $message . "\n\n", FILE_APPEND) === false) {
            return false;
        }
    }
}
