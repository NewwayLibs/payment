<?php namespace Newway\Payment\Providers;

use App;
use Newway\Payment\Interfaces\ProviderInterface;
use Newway\Payment\Validator;

abstract class AbstractProvider implements ProviderInterface
{

    protected $validator;

    /**
     * @var array
     */
    public function __construct(array $credentials = array())
    {

        $this->validator = App::make('Newway\Payment\Validation\Validator');

    }

}
