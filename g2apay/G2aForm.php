<?php

/*******************************************************************
 * G2A Pay gateway module for WHMCS.
 * (c) 2015 G2A.COM
 ******************************************************************/

class G2aForm
{
    private $params   = [];
    protected $code   = '';
    protected $checkoutUrl;

    /**
     * Set defined checkout URL.
     * @param $checkoutUrl
     */
    public function __construct($checkoutUrl)
    {
        $this->setCheckoutUrl($checkoutUrl);
        $this->initialize();
    }

    /**
     * Checkout url setter.
     * @param $checkoutUrl
     */
    public function setCheckoutUrl($checkoutUrl)
    {
        $this->checkoutUrl = $checkoutUrl;
    }

    /**
     * Checkout url getter.
     * @return mixed
     */
    public function getCheckoutUrl()
    {
        return $this->checkoutUrl;
    }

    /**
     * Params setter.
     * @param $params
     */
    public function setParams(array $params)
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
     * Start the form implementation.
     */
    public function initialize()
    {
        $this->code = '<form method="POST" id="createQuote" action="' . $this->getCheckoutUrl() . 'createQuote">';
    }

    /**
     * Add normal field to form.
     * @param $name
     * @param $value
     * @param string $type
     */
    public function addField($name, $value, $type = 'hidden')
    {
        $this->code .= '<input type="' . $type . '" name="' . $name . '" value="' . $value . '">';
    }

    /**
     * Add item field to form.
     * @param $key
     * @param $keyType
     * @param $value
     * @return string
     */
    public function addFieldItem($key, $keyType, $value)
    {
        $name = 'items[' . $key . '][' . $keyType . ']';

        return $this->addField($name, $value);
    }

    /**
     * Get existing form code and automatically generate last required fields.
     * Method return ready to show and use payment form.
     */
    public function render()
    {
        $log = new G2aLog($this->getParams());
        $log->save($this->code);

        $this->code .= '<input type="submit" value="Pay via G2A Pay"></form>
                        <script src="https://code.jquery.com/jquery-1.11.2.min.js"></script>
                        <script>
                        var jQueryPay = jQuery.noConflict();
                        jQueryPay(function() {
                            jQueryPay("#createQuote").submit(function(t) {
                                t.preventDefault();
                                var e = t.currentTarget.action;
                                jQueryPay.ajax({
                                    url: e,
                                    type: "post",
                                    dataType: "json",
                                    data: jQueryPay("#createQuote").serialize(),
                                    success: function(t) {
                                        var e = "' . $this->getCheckoutUrl() . 'gateway?token=" + t.token;
                                        jQueryPay(location).attr("href", e)
                                    }
                                })
                            })
                        });
                        </script>';

        return $this->code;
    }
}
