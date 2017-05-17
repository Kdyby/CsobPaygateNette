<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\CsobPaygateNette\UI;

use Kdyby;
use Kdyby\CsobPaymentGateway as Csob;
use Nette;
use Nette\Http\IRequest;



/**
 * @author Jiří Pudil <me@jiripudil.cz>
 *
 * @method onRender(CsobCheckoutControl $control, Nette\Utils\Html $iframe)
 * @method onCheckoutRequest(CsobCheckoutControl $control, Csob\CheckoutRequest $checkoutRequest)
 * @method onReturnCheckout(CsobCheckoutControl $control, Csob\Message\Response $response)
 * @method onError(CsobCheckoutControl $control, \Exception $e, Csob\Message\Response $response = NULL)
 */
class CsobCheckoutControl extends Nette\Application\UI\Control
{

	/**
	 * @var array|callable[]|\Closure[]
	 */
	public $onRender = [];

	/**
	 * @var array|callable[]|\Closure[]
	 */
	public $onCheckoutRequest = [];

	/**
	 * @var array|callable[]|\Closure[]
	 */
	public $onReturnCheckout = [];

	/**
	 * @var array|callable[]|\Closure[]
	 */
	public $onError = [];

	/**
	 * @var string
	 */
	private $paymentId;

	/**
	 * @var Csob\Client
	 */
	private $client;

	/**
	 * @var IRequest
	 */
	private $httpRequest;



	/**
	 * @param string $paymentId
	 */
	public function __construct($paymentId, Csob\Client $client, IRequest $httpRequest)
	{
		parent::__construct();
		$this->paymentId = $paymentId;
		$this->client = $client;
		$this->httpRequest = $httpRequest;
	}



	public function handleReturnCheckout()
	{
		$data = $this->httpRequest->getQuery();

		$response = NULL;
		try {
			$response = $this->client->receiveCheckout($data);

		} catch (Csob\Exception $e) {
			if ($response === NULL && $e instanceof Csob\ExceptionWithResponse) {
				$response = $e->getResponse();
			}

			$this->onError($this, $e, $response);
			return;
		}

		$this->onReturnCheckout($this, $response);
	}



	public function render()
	{
		$checkoutRequest = new Csob\CheckoutRequest($this->paymentId, Csob\CheckoutRequest::ONECLICK_CHECKBOX_HIDE);
		$checkoutRequest->setReturnCheckoutUrl($this->link('//returnCheckout!'));
		$this->onCheckoutRequest($this, $checkoutRequest);

		$iframe = Nette\Utils\Html::el('iframe', [
			'src' => $this->client->paymentCheckout($checkoutRequest)->getUrl(),
			'width' => 500,
			'height' => 600,
		]);

		$this->onRender($this, $iframe);
		echo $iframe;
	}

}
