Quickstart
==========

This extension integrates ČSOB payment gateway [client library](https://github.com/Kdyby/CsobPaymentGateway) into Nette Framework.


Installation
------------

The best way to install Kdyby/CsobPaygateNette is using [Composer](http://getcomposer.org/):
                                                  
```sh
$ composer require kdyby/csob-paygate-nette
```

and enable it in `config.neon`:

```yml
extensions:
	csob: Kdyby\CsobPaygateNette\DI\CsobPaygateExtension
```


Minimal configuration
---------------------

```yml
csob:
	version: '1.6'
	merchantId: 'myMerchantId'
	shopName: 'MyShop, Inc.'
	sandbox:
		privateKey: /path/to/your/sandbox/private.key
```

This configuration enables ČSOB eAPI v1.6 in sandbox environment.


Payment processing
------------------

The extension provides the `Kdyby\CsobPaygateNette\UI\CsobControl` component. You can create it in your presenter using a generated factory. Simply call its `pay()` method to initiate the payment process.

```php
use Kdyby\CsobPaygateNette\UI\ICsobControlFactory;
use Nette\Application\UI\Presenter;

class CheckoutPresenter extends Presenter
{

	private $factory;

	public function __construct(ICsobControlFactory $factory)
	{
		$this->factory = $factory;
	}


	public function handlePay()
	{
		$this['csob']->pay();
	}


	protected function createComponentCsob()
	{
		$control = $this->factory->create();

		$control->onInit[] = function () { /* ... */ };
		// setup event handlers (see below)

		return $control;
	}

}
```

The component exposes several events during the payment lifecycle:

- `onInit($control, Payment $payment)`: here you should configure the Payment object (see [client library docs](https://github.com/Kdyby/CsobPaymentGateway/blob/master/docs/en/index.md#processing-a-payment) for reference);
- `onCreated($control, Response $response)`: this gives you access to the created payment including its `payId` which you will need later to match the payment response against your pament;
- `onProcess($control, RedirectResponse $redirect)`: here you should redirect the user to the provided payment gateway URL;
- `onResponse($control, Response $response)`: this is called when the component receives the information about the payment (identified by its `payId`) - and whether it is approved or not;
- `onError($control, \Exception $e, Response $response = NULL)`: this is called in case of any error in the process. You can distinguish between [different types of exceptions](https://github.com/Kdyby/CsobPaymentGateway/blob/master/src/exceptions.php) to give the user information about the exact reason of failure.


Going to production
-------------------

Once you are done testing and are ready to go to production, switch the mode in the configuration and configure the production key:

```yml
csob:
	productionMode: true
	production:
		privateKey:
			path: /path/to/your/production/private.key
			password: "key passphrase if required"
```
