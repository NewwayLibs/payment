<?php namespace Newway\Payment\Validation;

use Illuminate\Support\MessageBag;

/**
 * Class ValidationException
 * @package Newway\Payment\Validation
 */
class ValidationException extends \Exception
{

    /**
     * @var mixed
     */
    protected $errors;

    /**
     * @param string $message
     * @param array | MessageBag  $errors
     */
    function __construct($message, $errors)
    {

        if (!$errors instanceof MessageBag) {

            $errors = new MessageBag((array)$errors);
        }


        $this->errors = $errors;

        parent::__construct($message);
    }

    /**
     * Get form validation errors
     *
     * @return MessageBag
     */
    public function getErrors()
    {

        return $this->errors;
    }

}