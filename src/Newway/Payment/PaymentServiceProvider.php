<?php namespace Newway\Payment;

use Illuminate\Support\ServiceProvider;

/**
 * Class PaymentServiceProvider
 * @package Newway\Payment
 */
class PaymentServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{

	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
    public function register()
    {

        $this->app->bind(
            'Newway\Payment\Validation\FactoryInterface',
            'Newway\Payment\Validation\LaravelValidator'
        );
    }

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
