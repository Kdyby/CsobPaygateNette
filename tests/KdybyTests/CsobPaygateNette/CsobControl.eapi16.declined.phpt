<?php

/**
 * Test: Kdyby\CsobPaygateNette\CsobControl
 *
 * @testCase Kdyby\CsobPaygateNette\CsobControlEapi16DeclinedTest
 */

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Kdyby\CsobPaygateNette\UI\CsobControl;
use Kdyby\CsobPaymentGateway\Configuration;
use Kdyby\CsobPaymentGateway\Message\RedirectResponse;
use Kdyby\CsobPaymentGateway\Message\Response;
use Kdyby\CsobPaymentGateway\Payment;
use Kdyby\CsobPaymentGateway\PaymentDeclinedException;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Jiří Pudil <me@jiripudil.cz>
 */
class CsobControlEapi16DeclinedTest extends CsobTestCase
{

	protected function setUp()
	{
		parent::setUp();
		$this->prepareContainer('eapi16');
		$this->mockSignature();
		$payId = Nette\Utils\Random::generate();

		$httpClientMock = \Mockery::mock(Kdyby\CsobPaymentGateway\IHttpClient::class);

		$initResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
			'payId' => $payId,
			'dttm' => (new \DateTime())->format('YmdGis'),
			'resultCode' => 0,
			'resultMessage' => 'OK',
			'paymentStatus' => Payment::STATUS_REQUESTED,
			'signature' => 'signature',
		]));
		$httpClientMock->shouldReceive('request')
			->with('POST', Configuration::DEFAULT_SANDBOX_URL . '/v1.6/payment/oneclick/init', \Mockery::type('array'), \Mockery::type('string'))
			->andReturn($initResponse);

		$startResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
			'payId' => $payId,
			'dttm' => (new \DateTime())->format('YmdGis'),
			'resultCode' => 0,
			'resultMessage' => 'OK',
			'paymentStatus' => Payment::STATUS_PENDING,
			'signature' => 'signature',
		]));
		$httpClientMock->shouldReceive('request')
			->with('POST', Configuration::DEFAULT_SANDBOX_URL . '/v1.6/payment/oneclick/start', \Mockery::type('array'), \Mockery::type('string'))
			->andReturn($startResponse);

		$statusPendingResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
			'payId' => $payId,
			'dttm' => (new \DateTime())->format('YmdGis'),
			'resultCode' => 0,
			'resultMessage' => 'OK',
			'paymentStatus' => Payment::STATUS_PENDING,
			'signature' => 'signature',
		]));
		$statusDeclinedResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
			'payId' => $payId,
			'dttm' => (new \DateTime())->format('YmdGis'),
			'resultCode' => 0,
			'resultMessage' => 'OK',
			'paymentStatus' => Payment::STATUS_DECLINED,
			'signature' => 'signature',
		]));

		$counter = 0;
		$httpClientMock->shouldReceive('request')
			->with('GET', \Mockery::on(function ($arg) { return strpos($arg, 'payment/status') !== FALSE; }), \Mockery::type('array'), NULL)
			->andReturnUsing(function () use (&$counter, $statusPendingResponse, $statusDeclinedResponse) {
				return ++$counter > 2 ? $statusDeclinedResponse : $statusPendingResponse;
			});

		$this->replaceService('csobPaygate.httpClient', $httpClientMock);

		$this->usePresenter('Test');
		$this->presenter['csob'];
	}



	public function testOneclickDeclined()
	{
		$this->presenter['csob']->onInit[] = function (CsobControl $control, Payment $payment) {
			$payment->setOrderNo(15000002)
				->setOriginalPayId(15000001)
				->setDescription('Test payment')
				->addCartItem('Test item 1', 42 * 100, 1)
				->addCartItem('Test item 2', 158 * 100, 2);
		};

		$this->presenter['csob']->onCreated[] = function (CsobControl $control, Response $response) {
			Assert::same(0, $response->getResultCode());
			Assert::same("OK", $response->getResultMessage());
			Assert::same(Payment::STATUS_DECLINED, $response->getPaymentStatus());
		};

		$this->presenter['csob']->onProcess[] = function (CsobControl $control, RedirectResponse $redirect) {
			Assert::fail('The process handler should not be triggered.');
		};

		$this->presenter['csob']->onResponse[] = function (CsobControl $control, Response $response) {
			Assert::fail('The response handler should not be triggered.');
		};

		$this->presenter['csob']->onError[] = function (CsobControl $control, Kdyby\CsobPaymentGateway\Exception $exception, Response $response = NULL) {
			Assert::type(PaymentDeclinedException::class, $exception);
			Assert::same(0, $exception->getCode());
			Assert::same("OK", $exception->getMessage());

			Assert::notSame(NULL, $response);
			Assert::same(0, $response->getResultCode());
			Assert::same("OK", $response->getResultMessage());
			Assert::same(Payment::STATUS_DECLINED, $response->getPaymentStatus());

			$control->getPresenter()->terminate();
		};

		$this->runPresenterAction('pay');
	}

}

\run(new CsobControlEapi16DeclinedTest());
