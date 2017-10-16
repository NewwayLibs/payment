<?php namespace Newway\Payment\Providers;

use Illuminate\Support\Facades\Lang;
use Newway\Payment\Exceptions\HackException;
use Newway\Payment\Exceptions\ProviderException;
use Newway\Payment\Validation\ValidationException;


class Liqpay extends AbstractProvider
{

    protected $data;

    protected $_supportedCurrencies = array('EUR', 'UAH', 'USD', 'RUB', 'RUR');

    protected $_supportedParams = array(
            'public_key',
            'amount',
            'currency',
            'description',
            'order_id',
            'result_url',
            'server_url',
            'type',
            'signature',
            'language',
            'subscribe',
            'subscribe_date_start',
            'subscribe_periodicity',
            'sandbox'
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
                'public_key'  => 'required|min:5',
                'private_key' => 'required|min:5',
        );

        $this->validator->validate($credentials, $rules);
    }


    /**
     * @param array $credentials
     * @param null $desciption
     * @throws ValidationException
     * @return string
     */
    public function getForm(array $credentials, $desciption = null)
    {


        $rules = array(
                'amount'                => 'required|numeric',
                'currency'              => 'required|in:USD,EUR,RUB,UAH,GEL',
                'description'           => 'required',
                'order_id'              => 'required',
                'type'                  => 'in:buy,donate',
                'subscribe'             => 'in:1',
                'subscribe_date_start'  => 'required_if:subscribe,1|date|date_format:Y-m-d H:i:s',
                'subscribe_periodicity' => 'required_if:subscribe,1|in:month,year',
                'server_url'            => 'url',
                'result_url'            => 'url',
                'pay_way'               => 'in:card,delayed',
                'language'              => 'in:ru,en',
                'sandbox'               => 'boolean',
        );

        $this->validator->validate($credentials, $rules);


        return $this->_getForm($credentials, $desciption);
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


    /**
     * Получение подписи транзакции по данным
     *
     * @return string
     */
    public function getSign()
    {

        return $this->_strToSign(
                $this->credentials['private_key'] .
                $this->amount .
                $this->currency .
                $this->credentials['public_key'] .
                $this->order_id .
                $this->type .
                $this->description .
                $this->status .
                $this->transaction_id .
                $this->sender_phone
        );
    }

    /**
     *
     * Проверяем подпись запроса
     * @throws HackException
     *
     */
    public function validateSignature()
    {

        $sign = $this->getSign();

        // проверяем подпись
        if ($sign != $this->signature) {
            throw new HackException(
                    Lang::get('payment::messages.hack_attempt') . ': ' .
                    Lang::get('payment::messages.invalid_signature')
            );
        }

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
     * @param string $url
     * @param array $params
     *
     * @return string
     */
    public function api($url, $params = array())
    {

        $url = 'https://www.liqpay.ua/api/' . $url;

        $public_key = $this->credentials['public_key'];
        $private_key = $this->credentials['private_key'];
        $data = json_encode(array_merge(compact('public_key'), $params));
        $signature = base64_encode(sha1($private_key . $data . $private_key, 1));
        $postfields = "data={$data}&signature={$signature}";

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $server_output = curl_exec($ch);

        curl_close($ch);

        return json_decode($server_output);
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

        $public_key = $params['public_key'] = $this->credentials['public_key'];
        $private_key = $this->credentials['private_key'];

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
        if (!isset($params['description'])) {
            throw new ValidationException(
                    Lang::get('payment::messages.required_fields_not_provided'),
                    ['description' => Lang::get('payment::messages.description_is_null')]
            );

        }

        $params['signature'] = $this->_signature($params);


        $language = 'ru';
        if (isset($params['language']) && $params['language'] == 'en') {
            $language = 'en';
        }

        $inputs = array();

        if (!empty($desciption)){
            $inputs [] = $desciption;
        }

        foreach ($params as $key => $value) {
            if (!in_array($key, $this->_supportedParams)) {
                continue;
            }
            $inputs[] = sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value);
        }

        return sprintf(
                '
                <form method="post" action="https://www.liqpay.ua/api/pay" accept-charset="utf-8">
                    %s
                    <input type="image" src="//static.liqpay.ua/buttons/p1%s.radius.png" name="btn_text" />
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

        $public_key = $params['public_key'] = $this->credentials['public_key'];
        $private_key = $this->credentials['private_key'];


        if ($params['currency'] == 'RUR') {
            $params['currency'] = 'RUB';
        }

        $amount = $params['amount'];
        $currency = $params['currency'];
        $description = $params['description'];

        $order_id = '';
        if (isset($params['order_id'])) {
            $order_id = $params['order_id'];
        }

        $type = '';
        if (isset($params['type'])) {
            $type = $params['type'];
        }

        $result_url = '';
        if (isset($params['result_url'])) {
            $result_url = $params['result_url'];
        }

        $server_url = '';
        if (isset($params['server_url'])) {
            $server_url = $params['server_url'];
        }

        $signature = $this->_strToSign(
                $private_key .
                $amount .
                $currency .
                $public_key .
                $order_id .
                $type .
                $description .
                $result_url .
                $server_url
        );

        return $signature;
    }


    /**
     * generate signature from string
     *
     * @param $str
     *
     * @return string
     */
    private function _strToSign($str)
    {

        return base64_encode(sha1($str, 1));
    }


}
