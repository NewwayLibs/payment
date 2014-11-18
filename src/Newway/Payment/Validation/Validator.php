<?php namespace Newway\Payment\Validation;

use Newway\Payment\Validation\ValidatorInterface as ValidatorInstance;


class Validator {

	/**
	 * @var ValidatorFactory
	 */
	protected $validator;

	/**
	 * @var ValidatorInstance
	 */
	protected $validation;

	/**
	 * @var array
	 */
	protected $messages = [];

    /**
     * @param FactoryInterface $validator
     */
	function __construct(FactoryInterface $validator)
	{
		$this->validator = $validator;
	}

    /**
     * Validate the form data
     *
     * @param  mixed $formData
     * @param array $rules
     * @param array $messages
     * @throws ValidationException
     * @return mixed
     */
	public function validate($formData, $rules = array(), $messages = array())
	{
		$formData = $this->normalizeFormData($formData);

		$this->validation = $this->validator->make(
			$formData,
            $rules,
            $messages
		);

		if ($this->validation->fails())
		{
			throw new ValidationException('Validation failed', $this->getValidationErrors());
		}

		return true;
	}

	/**
	 * @return mixed
	 */
	public function getValidationErrors()
	{
		return $this->validation->errors();
	}

	/**
	 * Normalize the provided data to an array.
	 *
	 * @param  mixed $formData
	 * @return array
	 */
	protected function normalizeFormData($formData)
	{
		// If an object was provided, maybe the user
		// is giving us something like a DTO.
		// In that case, we'll grab the public properties
		// off of it, and use that.
		if (is_object($formData))
		{
        	return get_object_vars($formData);
		}

		// Otherwise, we'll just stick with what they provided.
		return $formData;
	}

}
