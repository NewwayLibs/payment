<?php namespace Newway\Payment;

use Newway\Payment\Exceptions\ProviderException;
use Newway\Payment\Interfaces\ProviderInterface;
use Newway\Payment\Providers\Liqpay;
use Newway\Payment\Validation\ValidationException;

/**
 * Class Factory
 * @package Newway\Payment
 */
class Factory {

    /**
     * @param $provider
     * @param array $credentials
     * @throws ProviderException | ValidationException
     * @return ProviderInterface
     */
    public static function make($provider, array $credentials = array()){


        switch ($provider){
            case 'liqpay':
                return new Liqpay($credentials);
                break;
            default:
                // there is no such provider
                throw new ProviderException('Cannot find provider: '.$provider);
        }


    }
}
 