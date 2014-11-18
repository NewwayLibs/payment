<?php namespace Newway\Payment\Interfaces;


use Newway\Payment\Exceptions\HackException;
use Newway\Payment\Exceptions\ProviderException;
use Newway\Payment\Validation\ValidationException;

interface ProviderInterface
{

    /**
     * @param array $credentials
     * @throws ProviderException | ValidationException
     */
    public function __construct(array $credentials = array());


    /**
     * @param array $credentials
     * @throws ValidationException
     * @return string
     */
    public function getForm(array $credentials);

    /**
     * Сохраняем данные транзакции в объект
     *
     * @param array $data
     */
    public function setData(array $data);

    /**
     * Получаем данные транзакции
     *
     * @return array $data
     */
    public function getData();

    /**
     * Получение подписи транзакции по данным
     *
     * @return string
     */
    public function getSign();

    /**
     *
     * Проверяем подпись запроса
     * @throws HackException
     *
     */
    public function validateSignature();

    /**
     *
     * Проверяем сумму запроса
     * @throws HackException
     *
     */
    public function validateAmount($amount);

    /**
     *
     * Проверяем валюту запроса
     * @throws HackException
     *
     */
    public function validateCurrency($currency);

    /**
     *
     * Получение свойств тразнакции по прямому обращению к ним
     *
     * @param $field
     * @return mixed
     */
    public function __get($field);
}