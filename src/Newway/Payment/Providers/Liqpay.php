<?php namespace Newway\Payment\Providers;

use Newway\Vendor\LiqPay as LiqPayProvider;
use Newway\Payment\Exceptions\HackException;
use Newway\Payment\Exceptions\ProviderException;
use Newway\Payment\Validation\ValidationException;


class Liqpay extends AbstractProvider
{

    protected $provider;

    protected $data;

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
                'public_key'  => 'required',
                'private_key' => 'required',
        );

        $this->validator->validate($credentials, $rules);


        try {
            $this->provider = new LiqPayProvider($this->credentials['public_key'], $this->credentials['private_key']);
        } catch (\Exception $e) {
            throw new ProviderException('Provider init error: ' . $e->getMessage());
        }

    }


    /**
     * @param array $credentials
     * @throws ValidationException
     * @return string
     */
    public function getForm(array $credentials)
    {


        $rules = array(
                'amount'                => 'required|numeric',
                'currency'              => 'required|in:USD,EUR,RUB,UAH,GEL',
                'description'           => 'required',
                'order_id'              => 'required',
                'type'                  => 'in:buy,donate,subscribe',
                'subscribe_date_start'  => 'required_if:type,subscribe|date|date_format:Y-m-d H:i:s',
                'subscribe_periodicity' => 'required_if:type,subscribe|in:month,year',
                'server_url'            => 'url',
                'result_url'            => 'url',
                'pay_way'               => 'in:card,delayed',
                'language'              => 'in:ru,en',
                'sandbox'               => 'boolean',
        );

        $this->validator->validate($credentials, $rules);


        return $this->provider->cnb_form($credentials);
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
        $this->data['order_id'] = intval(array_get($data, 'order_id'));
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

        return base64_encode(
                sha1(
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
                        ,
                        true
                )
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
            throw new HackException(trans('payments.hack_attempt') . ': ' . trans('payments.invalid_signature'));
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
            throw new HackException(trans('payments.hack_attempt') . ': ' . trans('payments.invalid_amount'));
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
            throw new HackException(trans('payments.hack_attempt') . ': ' . trans('payments.invalid_currency'));
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


}
