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
use Kdyby\CsobPaygateNette\InvalidStateException;
use Kdyby\CsobPaymentGateway as Csob;
use Nette;
use Nette\Http\IRequest;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @method onInit(CsobControl $control, Csob\Payment $request)
 * @method onCreated(CsobControl $control, Csob\Message\Response $response)
 * @method onProcess(CsobControl $control, Csob\Message\RedirectResponse $redirectUrl)
 * @method onResponse(CsobControl $control, Csob\Message\Response $response)
 * @method onError(CsobControl $control, \Exception $e, Csob\Message\Response $response = NULL)
 */
class CsobControl extends Nette\Application\UI\Control
{

	/**
	 * Event on pay creating.
	 *
	 * @var array|callable[]|\Closure[]
	 */
	public $onInit = [];

	/**
	 * Event on pay creating.
	 *
	 * @var array|callable[]|\Closure[]
	 */
	public $onCreated = [];

	/**
	 * Event on pay creating.
	 *
	 * @var array|callable[]|\Closure[]
	 */
	public $onProcess = [];

	/**
	 * Event on success response from gateway.
	 *
	 * @var array|callable[]|\Closure[]
	 */
	public $onResponse = [];

	/**
	 * Event on error response from gateway. It shows flashMessage by default.
	 *
	 * @var array|callable[]|\Closure[]
	 */
	public $onError = [];

	/**
	 * @var Csob\Client
	 */
	private $client;

	/**
	 * @var IRequest
	 */
	private $httpRequest;



	public function __construct(Csob\Client $client, IRequest $httpRequest)
	{
		parent::__construct();
		$this->client = $client;
		$this->httpRequest = $httpRequest;
	}



	/**
	 * Creates/sends request to gateway.
	 */
	public function pay()
	{
		$this->check();

		$payment = $this->client->createPayment();
		$payment->setReturnUrl($this->link('//response!'));
		$this->onInit($this, $payment);

		if ($payment->getOriginalPayId()) {
			$response = NULL;
			try {
				$response = $this->client->paymentRecurrent($payment);

			} catch (Csob\Exception $e) {
				if ($response === NULL && $e instanceof Csob\ExceptionWithResponse) {
					$response = $e->getResponse();
				}

				if ($response !== NULL && $response->getPayId()) {
					$this->onCreated($this, $response);
				}

				$this->onError($this, $e, $response);
				return;
			}

			$this->onCreated($this, $response);
			$this->onResponse($this, $response);

		} else {
			$response = NULL;
			try {
				$response = $this->client->paymentInit($payment);

			} catch (Csob\Exception $e) {
				if ($response === NULL && $e instanceof Csob\ExceptionWithResponse) {
					$response = $e->getResponse();
				}

				if ($response !== NULL && $response->getPayId()) {
					$this->onCreated($this, $response);
				}

				$this->onError($this, $e, $response);
				return;
			}

			$this->onCreated($this, $response);

			$redirect = $this->client->paymentProcess($response->getPayId());
			$this->onProcess($this, $redirect);
		}
	}



	/**
	 * Signal for receive a response from gateway.
	 */
	public function handleResponse()
	{
		$data = $this->httpRequest->isMethod(IRequest::POST)
			? $this->httpRequest->getPost()
			: $this->httpRequest->getQuery();

		$response = NULL;
		try {
			$response = $this->client->receiveResponse($data);

		} catch (Csob\Exception $e) {
			if ($response === NULL && $e instanceof Csob\ExceptionWithResponse) {
				$response = $e->getResponse();
			}

			$this->onError($this, $e, $response);
			return;
		}

		$this->onResponse($this, $response);
	}



	/**
	 * Checks all important attributes of control. If some is missing throws an exception.
	 */
	protected function check()
	{
		if (!$this->getPresenter(FALSE)) {
			throw new InvalidStateException(sprintf("%s is not attached to Presenter.", get_called_class()));
		}
		if (!count($this->onInit)) {
			throw new InvalidStateException("You must specify at least one 'onInit' event.");
		}
		if (!count($this->onResponse)) {
			throw new InvalidStateException("You must specify at least one 'onResponse' event.");
		}
		if (!count($this->onError)) {
			throw new InvalidStateException("You must specify at least one 'onError' event.");
		}
	}

}
