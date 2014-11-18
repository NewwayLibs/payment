<?php namespace Newway\Payment\Validation;

use Illuminate\Validation\Factory as IlluminateValidator;

class LaravelValidator implements FactoryInterface {

	/**
	 * @var \Illuminate\Validation\Factory
	 */
	private $validator;

	/**
	 * @param IlluminateValidator $validator
	 */
	function __construct(IlluminateValidator $validator)
	{
		$this->validator = $validator;
	}

	/**
	 * Initialize validation
	 *
	 * @param array $formData
	 * @param array $rules
	 * @param array $messages
	 * @return \Illuminate\Validation\Validator
	 */
	public function make(array $formData, array $rules, array $messages = [])
	{
		return $this->validator->make($formData, $rules, $messages);
	}

}
