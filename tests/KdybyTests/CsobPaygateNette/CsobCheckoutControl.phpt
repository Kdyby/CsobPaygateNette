<?php

/**
 * Test: Kdyby\CsobPaygateNette\CsobCheckoutControl
 *
 * @testCase
 */

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Kdyby\CsobPaygateNette\UI\CsobCheckoutControl;
use Kdyby\CsobPaymentGateway\CheckoutRequest;
use Kdyby\CsobPaymentGateway\Configuration;
use Kdyby\CsobPaymentGateway\Message\RedirectResponse;
use Kdyby\CsobPaymentGateway\Message\Response;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Jiří Pudil <me@jiripudil.cz>
 */
class CsobCheckoutControlTest extends CsobTestCase
{

	protected function setUp()
	{
		parent::setUp();
		$this->prepareContainer('checkout');

		$this->mockSignature();

		$this->usePresenter('TestCheckout');
		$this->presenter['checkout'];
	}



	public function testCheckout()
	{
		$this->presenter['checkout']->onCheckoutRequest[] = function (CsobCheckoutControl $control, CheckoutRequest $request) {
			Assert::same('fb425174783f9AK', $request->getPaymentId());
			Assert::same(CheckoutRequest::ONECLICK_CHECKBOX_HIDE, $request->getOneclickPaymentCheckbox());
			Assert::false($request->getDisplayOmnibox());
			Assert::same($this->presenter->link('//checkout-returnCheckout!'), $request->getReturnCheckoutUrl());
		};

		$this->presenter['checkout']->onRender[] = function (CsobCheckoutControl $control, Nette\Utils\Html $iframe) {
			Assert::match(Configuration::DEFAULT_SANDBOX_URL . '/v1.5/payment/checkout/%A%', $iframe->attrs['src']);
			Assert::same(500, $iframe->attrs['width']);
			Assert::same(600, $iframe->attrs['height']);

			$control->getPresenter()->redirect('this');
		};

		$this->presenter['checkout']->onReturnCheckout[] = function (CsobCheckoutControl $control, $paymentId) {
			Assert::fail('The returnCheckout handler should not be triggered.');
		};

		$this->presenter['checkout']->onError[] = function (CsobCheckoutControl $control, Kdyby\CsobPaymentGateway\Exception $exception, Response $response = NULL) {
			Assert::fail('The error handler should not be triggered.');
		};

		$this->setPaymentId('fb425174783f9AK');
		$response = $this->runPresenterAction('checkout', [
			'payId' => 'fb425174783f9AK',
		]);
		Assert::type(Nette\Application\Responses\RedirectResponse::class, $response);
	}



	public function testReturnCheckout()
	{
		$this->presenter['checkout']->onCheckoutRequest[] = function (CsobCheckoutControl $control, CheckoutRequest $request) {
			Assert::same('ee4c7266dca71AK', $request->getPaymentId());
			Assert::same(CheckoutRequest::ONECLICK_CHECKBOX_HIDE, $request->getOneclickPaymentCheckbox());
			Assert::false($request->getDisplayOmnibox());
			Assert::same($this->presenter->link('//checkout-returnCheckout!'), $request->getReturnCheckoutUrl());
		};

		$this->presenter['checkout']->onRender[] = function (CsobCheckoutControl $control, Nette\Utils\Html $iframe) {
			Assert::fail('The render handler should not be triggered.');
		};

		$this->presenter['checkout']->onReturnCheckout[] = function (CsobCheckoutControl $control, Response $response) {
			Assert::same('ee4c7266dca71AK', $response->getPayId());
			$control->getPresenter()->redirect('this');
		};

		$this->presenter['checkout']->onError[] = function (CsobCheckoutControl $control, Kdyby\CsobPaymentGateway\Exception $exception, Response $response = NULL) {
			Assert::fail('The error handler should not be triggered.');
		};

		$this->setPaymentId('ee4c7266dca71AK');
		$response = $this->runPresenterAction('checkout', [
			'do' => 'checkout-returnCheckout',
			'payId' => 'ee4c7266dca71AK',
			'dttm' => '20160710121314',
			'signature' => 'signature',
		]);
		Assert::type(Nette\Application\Responses\RedirectResponse::class, $response);
	}



	private function setPaymentId($paymentId)
	{
		$checkoutControl = $this->presenter['checkout'];
		$reflection = new \ReflectionClass($checkoutControl);

		$property = $reflection->getProperty('paymentId');
		$property->setAccessible(TRUE);
		$property->setValue($checkoutControl, $paymentId);
	}

}



\run(new CsobCheckoutControlTest());
