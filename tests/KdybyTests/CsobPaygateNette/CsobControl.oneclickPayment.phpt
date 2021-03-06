<?php

/**
 * Test: Kdyby\CsobPaygateNette\CsobControl
 *
 * @testCase Kdyby\CsobPaygateNette\CsobControlOneclickPaymentTest
 */

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Kdyby\CsobPaygateNette\UI\CsobControl;
use Kdyby\CsobPaymentGateway\Configuration;
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
class CsobControlOneclickPaymentTest extends CsobTestCase
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
		$statusFinishedResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
			'payId' => $payId,
			'dttm' => (new \DateTime())->format('YmdGis'),
			'resultCode' => 0,
			'resultMessage' => 'OK',
			'paymentStatus' => Payment::STATUS_TO_CLEARING,
			'signature' => 'signature',
		]));

		$counter = 0;
		$httpClientMock->shouldReceive('request')
			->with('GET', \Mockery::on(function ($arg) { return strpos($arg, 'payment/status') !== FALSE; }), \Mockery::type('array'), NULL)
			->andReturnUsing(function () use (&$counter, $statusPendingResponse, $statusFinishedResponse) {
				return ++$counter > 2 ? $statusFinishedResponse : $statusPendingResponse;
			});

		$this->replaceService('csobPaygate.httpClient', $httpClientMock);

		$this->usePresenter('Test');
		$this->presenter['csob'];
	}



	public function testOneclickPayment()
	{
		$this->presenter['csob']->onInit[] = function (CsobControl $control, Payment $payment) {
			$payment->setOrderNo(15000002)
				->setOriginalPayId(15000001)
				->setDescription('Test payment')
				->addCartItem('Test item 1', 42 * 100, 1)
				->addCartItem('Test item 2', 158 * 100, 2);
		};

		$this->presenter['csob']->onCreated[] = function (CsobControl $control, Response $response) {
			Assert::notSame(NULL, $response->getPayId());
			Assert::same(0, $response->getResultCode());
			Assert::same('OK', $response->getResultMessage());
			Assert::same(Payment::STATUS_PENDING, $response->getPaymentStatus());
		};

		$this->presenter['csob']->onProcess[] = function (CsobControl $control, RedirectResponse $redirect) {
			Assert::fail('The process handler should not be triggered.');
		};

		$this->presenter['csob']->onResponse[] = function (CsobControl $control, Response $response) {
			Assert::notSame(NULL, $response->getPayId());
			Assert::same(0, $response->getResultCode());
			Assert::same('OK', $response->getResultMessage());

			if ($response->getPaymentStatus() === Payment::STATUS_PENDING) {
				$control->pollStatus($response->getPayId());

			} else {
				Assert::same(Payment::STATUS_TO_CLEARING, $response->getPaymentStatus());
			}

			$control->getPresenter()->terminate();
		};

		$this->presenter['csob']->onError[] = function (CsobControl $control, Kdyby\CsobPaymentGateway\Exception $exception, Response $response = NULL) {
			Assert::fail(sprintf('The error handler should not be triggered: %s', $exception->getMessage()));
		};

		$this->runPresenterAction('pay');
	}



	protected function tearDown()
	{
		\Mockery::close();
	}

}

\run(new CsobControlOneclickPaymentTest());
