<?php

/**
 * Test: Kdyby\CsobPaygateNette\CsobControl
 *
 * @testCase Kdyby\CsobPaygateNette\CsobControlEapi16Test
 */

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Kdyby\CsobPaygateNette\UI\CsobControl;
use Kdyby\CsobPaymentGateway\Message\RedirectResponse;
use Kdyby\CsobPaymentGateway\Message\Response;
use Kdyby\CsobPaymentGateway\Payment;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Jiří Pudil <me@jiripudil.cz>
 */
class CsobControlEapi16Test extends CsobTestCase
{

	protected function setUp()
	{
		parent::setUp();
		$this->prepareContainer('eapi16');

		$this->usePresenter('Test');
		$this->presenter['csob'];
	}



	public function testPayment()
	{
		$this->presenter['csob']->onInit[] = function (CsobControl $control, Payment $payment) {
			$payment->setOrderNo(15000001)
				->setDescription('Test payment')
				->addCartItem('Test item 1', 42 * 100, 1)
				->addCartItem('Test item 2', 158 * 100, 2);

			Assert::same($this->presenter->link('//csob-response!'), $payment->toArray()['returnUrl']);
		};

		$this->presenter['csob']->onCreated[] = function (CsobControl $control, Response $response) {
			Assert::notSame(NULL, $response->getPayId());
			Assert::same(0, $response->getResultCode());
			Assert::same('OK', $response->getResultMessage());
			Assert::same(Payment::STATUS_REQUESTED, $response->getPaymentStatus());
		};

		$this->presenter['csob']->onProcess[] = function (CsobControl $control, RedirectResponse $redirect) {
			$control->getPresenter()->redirectUrl($redirect->getUrl());
		};

		$this->presenter['csob']->onResponse[] = function (CsobControl $control, Response $response) {
			Assert::fail('The response handler should not be triggered.');
		};

		$this->presenter['csob']->onError[] = function (CsobControl $control, Kdyby\CsobPaymentGateway\Exception $exception, Response $response = NULL) {
			Assert::fail(sprintf('The error handler should not be triggered: %s', $exception->getMessage()));
		};

		$response = $this->runPresenterAction('pay');
		Assert::type(Nette\Application\Responses\RedirectResponse::class, $response);
	}

}

\run(new CsobControlEapi16Test());
