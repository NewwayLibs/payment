<?php

namespace Newway\Payment\Providers;

use Newway\Payment\Exceptions\HackException;
use Newway\Payment\Exceptions\ProviderException;
use Newway\Payment\Validation\ValidationException;

class Wayforpay extends AbstractProvider
{

    protected $data;

    protected $_supportedCurrencies = array('EUR', 'UAH', 'USD', 'RUB', 'RUR');

    protected $_supportedParams = array(
        'merchantAccount',
        'merchantDomainName',
        'currency',
        'amount',
        'returnUrl',
        'serviceUrl',
        'orderReference',
        'orderDate',
        'productName',
        'productCount',
        'productPrice',
        'merchantSignature'
    );

    /**
     * @var array
     */
    private $credentials;

    /**
     * @param array $credentials
     * @throws ProviderException | ValidationException
     */
    public function __construct(array $credentials = array())
    {

        parent::__construct($credentials);

        $this->credentials = $credentials;

        $rules = array(
            'merchantAccount'  => 'required|min:5',
            'secretKey' => 'required|min:5',
        );

        $this->validator->validate($credentials, $rules);
    }

    public function getForm(array $parameters, $description = null)
    {
        $rules = array(
            'amount'                => 'required|numeric',
            'merchantDomainName'    => 'required',
            'orderDate'             => 'required',
            'currency'              => 'required|in:USD,EUR,RUB,UAH',
            'orderReference'        => 'required',
            'serviceUrl'            => 'url',
            'returnUrl'             => 'url',
            'language'              => 'in:ru,en',
            'productName'           => 'required|array',
            'productCount'          => 'required|array',
            'productPrice'          => 'required|array',
        );

        $this->validator->validate($parameters, $rules);

        return $this->_getForm($parameters, $description);
    }

    public function setData(array $data)
    {
        $this->data['merchantSignature'] = array_get($data, 'merchantSignature');
        $this->data['merchantAccount'] = array_get($data, 'merchantAccount');
        $this->data['authCode'] = array_get($data, 'authCode');
        $this->data['transactionStatus'] = array_get($data, 'transactionStatus');
        $this->data['orderReference'] = array_get($data, 'orderReference');
        $this->data['amount'] = array_get($data, 'amount');
        $this->data['currency'] = array_get($data, 'currency');
        $this->data['cardPan'] = array_get($data, 'cardPan');
        $this->data['reasonCode'] = array_get($data, 'reasonCode');

    }

    public function getData()
    {
        return $this->data;
    }

    public function getSign()
    {
        return $this->_strToSign(
            $this->merchantAccount . ';' .
            $this->orderReference . ';' .
            $this->amount . ';' .
            $this->currency . ';' .
            $this->authCode . ';' .
            $this->cardPan . ';' .
            $this->transactionStatus . ';' .
            $this->reasonCode,
            $this->credentials['secretKey']
        );
    }

    public function validateSignature()
    {
        $sign = $this->getSign();

        // проверяем подпись
        if ($sign != $this->merchantSignature) {
            throw new HackException(
                Lang::get('payment::messages.hack_attempt') . ': ' .
                Lang::get('payment::messages.invalid_signature')
            );
        }
    }

    public function validateAmount($amount)
    {
        if ($amount != $this->amount) {
            throw new HackException(
                Lang::get('payment::messages.hack_attempt') . ': ' .
                Lang::get('payment::messages.invalid_amount')
            );
        }
    }

    public function validateCurrency($currency)
    {
        if ($currency != $this->currency) {
            throw new HackException(
                Lang::get('payment::messages.hack_attempt') . ': ' .
                Lang::get('payment::messages.invalid_currency')
            );
        }
    }

    public function __get($field)
    {
        return array_get($this->data, $field);
    }

    /**
     *
     * Формирование ответа для платежной системы
     *
     * @return array
     */
    public function getResponse()
    {
        $time = time();



        $sign = $this->_strToSign(
            $this->orderReference . ';'.
            'accept' . ';' .
            $time,
            $this->credentials['secretKey']
        );

        return [
            'orderReference' => $this->orderReference,
            'status' => 'accept',
            'time' => $time,
            'signature' => $sign
        ];
    }


    /**
     * cnb_form
     *
     * @param array $params
     *
     * @param null $desciption
     * @throws ValidationException
     * @return string
     */
    private function _getForm($params, $desciption = null)
    {

        $merchantAccount = $params['merchantAccount'] = $this->credentials['merchantAccount'];
        $secretKey = $this->credentials['secretKey'];

        if (!isset($params['amount'])) {
            throw new ValidationException(
                Lang::get('payment::messages.required_fields_not_provided'),
                ['amount' => Lang::get('payment::messages.amount_is_null')]
            );
        }
        if (!isset($params['currency'])) {
            throw new ValidationException(
                Lang::get('payment::messages.required_fields_not_provided'),
                ['currency' => Lang::get('payment::messages.currency_is_null')]
            );

        }
        if (!in_array($params['currency'], $this->_supportedCurrencies)) {
            throw new ValidationException(
                Lang::get('payment::messages.required_fields_not_provided'),
                ['currency' => Lang::get('payment::messages.currency_is_not_supported')]
            );

        }
        if ($params['currency'] == 'RUR') {
            $params['currency'] = 'RUB';
        }

        $params['merchantSignature'] = $this->_signature($params);


        $language = 'ru';
        if (isset($params['language']) && $params['language'] == 'en') {
            $language = 'en';
        }

        $inputs = array();

        foreach ($params as $key => $value) {
            if (!in_array($key, $this->_supportedParams)) {
                continue;
            }
            if(is_array($value)){
                $key .= '[]';
                foreach($value as $param){
                    $inputs[] = sprintf('<input type="hidden" name="%s" value="%s" />', $key, $param);
                }
            }else{
                $inputs[] = sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value);
            }
        }

        return sprintf(
            '
                <form method="post" action="https://secure.wayforpay.com/pay">
                    %s
                    <input type="submit" value="send">
                </form>
            ',
            join("\r\n", $inputs),
            $language
        );
    }

    /**
     * _signature
     *
     * @param array $params
     *
     * @return string
     */
    public function _signature($params)
    {

        $merchantAccount = $params['merchantAccount'] = $this->credentials['merchantAccount'];
        $secretKey = $this->credentials['secretKey'];


        $merchantDomainName =  $params['merchantDomainName'];

        if ($params['currency'] == 'RUR') {
            $params['currency'] = 'RUB';
        }

        $amount = $params['amount'];
        $currency = $params['currency'];

        $orderDate = $params['orderDate'];
        $orderReference = $params['orderReference'];

        $productName = '';

        if(isset($params['productName'])){
            foreach ($params['productName'] as $key => $param){
                $productName .= $param;
                if($key + 1  < count($params['productName'])){
                    $productName .= ';';
                }
            }
        }

        $productCount = '';

        if(isset($params['productCount'])){
            foreach ($params['productCount'] as $key => $param){
                $productCount .= $param;
                if($key + 1  < count($params['productCount'])){
                    $productCount .= ';';
                }
            }
        }

        $productPrice = '';

        if(isset($params['productPrice'])){
            foreach ($params['productPrice'] as $key => $param){
                $productPrice .= $param;
                if($key + 1 < count($params['productPrice'])){
                    $productPrice .= ';';
                }
            }
        }

        $signature = $this->_strToSign(
            $merchantAccount . ';' .
            $merchantDomainName . ';' .
            $orderReference . ';' .
            $orderDate . ';' .
            $amount . ';' .
            $currency . ';' .
            $productName . ';' .
            $productCount . ';' .
            $productPrice,
            $secretKey
        );

        return $signature;
    }

    /**
     * generate signature from string
     *
     * @param $str
     * @param $secretKey
     *
     * @return string
     */
    private function _strToSign($str, $secretKey)
    {

        return hash_hmac("md5", $str, $secretKey);
    }
}

