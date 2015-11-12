<?php

/**
 * Test: Kdyby\CsobPaygateNette\CsobControl
 *
 * @testCase Kdyby\CsobPaygateNette\CsobControlHandleResponseTest
 */

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Kdyby\CsobPaygateNette\UI\CsobControl;
use Kdyby\CsobPaymentGateway\Message\Response;
use Kdyby\CsobPaymentGateway\Payment;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Jiří Pudil <me@jiripudil.cz>
 */
class CsobControlHandleResponseTest extends CsobTestCase
{

	protected function setUp()
	{
		parent::setUp();
		$this->mockSignature();

		$this->usePresenter('Test');
		$this->presenter['csob'];
	}



	public function testHandleGetResponse()
	{
		$this->presenter['csob']->onInit[] = function (CsobControl $control, Payment $payment) {
			Assert::fail('The init handler should not be triggered.');
		};

		$this->presenter['csob']->onCreated[] = function (CsobControl $control, Response $response) {
			Assert::fail('The created handler should not be triggered.');
		};

		$this->presenter['csob']->onResponse[] = function (CsobControl $control, Response $response) {
			Assert::same('fb425174783f9AK', $response->getPayId());
			Assert::same(0, $response->getResultCode());
			Assert::same('OK', $response->getResultMessage());
			Assert::same(Payment::STATUS_TO_CLEARING, $response->getPaymentStatus());
			Assert::same('637413', $response->getAuthCode());

			$control->getPresenter()->terminate();
		};

		$this->presenter['csob']->onError[] = function (CsobControl $control, Kdyby\CsobPaymentGateway\Exception $exception, Response $response = NULL) {
			Assert::fail('The error handler should not be triggered.');
		};

		$this->runPresenterAction('pay', [
			'do' => 'csob-response',
			'payId' => 'fb425174783f9AK',
			'dttm' => '20151109153917',
			'resultCode' => 0,
			'resultMessage' => 'OK',
			'paymentStatus' => Payment::STATUS_TO_CLEARING,
			'signature' => 'signature',
			'authCode' => '637413',
		]);
	}



	public function testHandlePostResponse()
	{
		$this->presenter['csob']->onInit[] = function (CsobControl $control, Payment $payment) {
			Assert::fail('The init handler should not be triggered.');
		};

		$this->presenter['csob']->onCreated[] = function (CsobControl $control, Response $response) {
			Assert::fail('The created handler should not be triggered.');
		};

		$this->presenter['csob']->onResponse[] = function (CsobControl $control, Response $response) {
			Assert::same('fb425174783f9AK', $response->getPayId());
			Assert::same(0, $response->getResultCode());
			Assert::same('OK', $response->getResultMessage());
			Assert::same(Payment::STATUS_TO_CLEARING, $response->getPaymentStatus());
			Assert::same('637413', $response->getAuthCode());

			$control->getPresenter()->terminate();
		};

		$this->presenter['csob']->onError[] = function (CsobControl $control, Kdyby\CsobPaymentGateway\Exception $exception, Response $response = NULL) {
			Assert::fail('The error handler should not be triggered.');
		};

		$this->runPresenterAction('pay', 'POST', ['do' => 'csob-response'], [
			'payId' => 'fb425174783f9AK',
			'dttm' => '20151109153917',
			'resultCode' => 0,
			'resultMessage' => 'OK',
			'paymentStatus' => Payment::STATUS_TO_CLEARING,
			'signature' => 'signature',
			'authCode' => '637413',
		]);
	}



	protected function tearDown()
	{
		\Mockery::close();
	}

}

\run(new CsobControlHandleResponseTest());
