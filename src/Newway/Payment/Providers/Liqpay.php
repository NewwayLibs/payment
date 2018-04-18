<?php namespace Newway\Payment\Providers;

use Illuminate\Support\Facades\Lang;
use Newway\Payment\Exceptions\HackException;
use Newway\Payment\Exceptions\ProviderException;
use Newway\Payment\Validation\ValidationException;


class Liqpay extends AbstractProvider
{
    const CURRENCY_EUR = 'EUR';
    const CURRENCY_USD = 'USD';
    const CURRENCY_UAH = 'UAH';
    const CURRENCY_RUB = 'RUB';
    const CURRENCY_RUR = 'RUR';

    private $_api_url = 'https://www.liqpay.ua/api/';
    private $_checkout_url = 'https://www.liqpay.ua/api/3/checkout';
    protected $_supportedCurrencies = array(
        self::CURRENCY_EUR,
        self::CURRENCY_USD,
        self::CURRENCY_UAH,
        self::CURRENCY_RUB,
        self::CURRENCY_RUR,
    );
    protected $data;
    /**
     * @var array
     */
    private $credentials;
    
    private $_server_response_code = null;


    /**
     * @param array $credentials
     * @throws ProviderException | ValidationException
     */
    public function __construct(array $credentials = array())
    {

        parent::__construct($credentials);

        $this->credentials = $credentials;

        $rules = array(
                'public_key'  => 'required|min:5',
                'private_key' => 'required|min:5',
        );

        $this->validator->validate($credentials, $rules);
    }

    /**
     * getForm
     *
     * @param array $params
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function getForm(array $params, $desciption = null)
    {
        $language = 'ru';
        if (isset($params['language']) && $params['language'] == 'en') {
            $language = 'en';
        }

        $params    = $this->cnb_params($params);
        $data      = $this->encode_params($params);
        $signature = $this->getSign($params);

        return sprintf('
            <form method="POST" action="%s" accept-charset="utf-8">
                %s
                %s
                <input type="image" src="//static.liqpay.ua/buttons/p1%s.radius.png" name="btn_text" />
            </form>
            ',
            $this->_checkout_url,
            sprintf('<input type="hidden" name="%s" value="%s" />', 'data', $data),
            sprintf('<input type="hidden" name="%s" value="%s" />', 'signature', $signature),
            $language
        );
    }

    /**
     * Сохраняем данные транзакции в объект
     *
     * @param array $data
     */
    public function setData(array $data)
    {

        $this->data['signature'] = array_get($data, 'signature');
        $this->data['sender_phone'] = array_get($data, 'sender_phone');
        $this->data['transaction_id'] = array_get($data, 'transaction_id');
        $this->data['status'] = array_get($data, 'status');
        $this->data['order_id'] = array_get($data, 'order_id');
        $this->data['amount'] = array_get($data, 'amount');
        $this->data['currency'] = array_get($data, 'currency');
        $this->data['type'] = array_get($data, 'type');
        $this->data['description'] = array_get($data, 'description');

    }

    /**
     * Получаем данные транзакции
     *
     * @return array $data
     */
    public function getData()
    {

        return $this->data;

    }

    public function validateSignature(){}

    /**
     *
     * Получение свойств тразнакции по прямому обращению к ним
     *
     * @param $field
     * @return mixed
     */
    public function __get($field)
    {

        return array_get($this->data, $field);
    }

    /**
     *
     * Проверяем сумму запроса
     * @throws HackException
     *
     */
    public function validateAmount($amount)
    {

        // проверяем подпись
        if ($amount != $this->amount) {
            throw new HackException(
                Lang::get('payment::messages.hack_attempt') . ': ' .
                Lang::get('payment::messages.invalid_amount')
            );
        }

    }

    /**
     *
     * Проверяем валюту запроса
     * @throws HackException
     *
     */
    public function validateCurrency($currency)
    {

        // проверяем подпись
        if ($currency != $this->currency) {
            throw new HackException(
                Lang::get('payment::messages.hack_attempt') . ': ' .
                Lang::get('payment::messages.invalid_currency')
            );
        }

    }

    /**
     * Return last api response http code
     *
     * @return string|null
     */
    public function get_response_code()
    {
        return $this->_server_response_code;
    }

    /**
     *
     * Формирование ответа для платежной системы
     *
     * @return array
     */
    public function getResponse()
    {
        return [];
    }

    /**
     * Call API
     *
     * @param string $path
     * @param array $params
     * @param int $timeout
     *
     * @return string
     */
    public function api($path, $params = array(), $timeout = 5)
    {
        if (!isset($params['version'])) {
            throw new InvalidArgumentException('version is null');
        }
        $url         = $this->_api_url . $path;
        $public_key  = $this->credentials['public_key'];
        $private_key = $this->credentials['private_key'];
        $data        = $this->encode_params(array_merge(compact('public_key'), $params));
        $signature   = $this->str_to_sign($private_key.$data.$private_key);
        $postfields  = http_build_query(array(
            'data'  => $data,
            'signature' => $signature
        ));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Avoid MITM vulnerability http://phpsecurity.readthedocs.io/en/latest/Input-Validation.html#validation-of-input-sources
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    // Check the existence of a common name and also verify that it matches the hostname provided
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,$timeout);   // The number of seconds to wait while trying to connect
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);          // The maximum number of seconds to allow cURL functions to execute
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        $this->_server_response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return json_decode($server_output);
    }


    /**
     * getSign
     *
     * @param null $params
     *
     * @return string
     */
    public function getSign($params = null)
    {
        $params      = $this->cnb_params($params);
        $private_key = $this->credentials['private_key'];

        $json      = $this->encode_params($params);
        $signature = $this->str_to_sign($private_key . $json . $private_key);

        return $signature;
    }

    /**
     * cnb_params
     *
     * @param array $params
     *
     * @return array $params
     */
    private function cnb_params($params)
    {
        $params['public_key'] = $this->credentials['public_key'];

        if (!isset($params['version'])) {
            throw new InvalidArgumentException('version is null');
        }
        if (!isset($params['amount'])) {
            throw new InvalidArgumentException('amount is null');
        }
        if (!isset($params['currency'])) {
            throw new InvalidArgumentException('currency is null');
        }
        if (!in_array($params['currency'], $this->_supportedCurrencies)) {
            throw new InvalidArgumentException('currency is not supported');
        }
        if ($params['currency'] == self::CURRENCY_RUR) {
            $params['currency'] = self::CURRENCY_RUB;
        }
        if (!isset($params['description'])) {
            throw new InvalidArgumentException('description is null');
        }

        return $params;
    }

    /**
     * encode_params
     *
     * @param array $params
     * @return string
     */
    private function encode_params($params)
    {
        return base64_encode(json_encode($params));
    }

    /**
     * decode_params
     *
     * @param string $params
     * @return array
     */
    public function decode_params($params)
    {
        return json_decode(base64_decode($params), true);
    }

    /**
     * str_to_sign
     *
     * @param string $str
     *
     * @return string
     */
    public function str_to_sign($str)
    {
        $signature = base64_encode(sha1($str, 1));

        return $signature;
    }

}
