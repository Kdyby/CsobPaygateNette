<?php

/**
 * Test: Kdyby\CsobPaygateNette\CsobControl
 *
 * @testCase Kdyby\CsobPaygateNette\CsobControlEapi16ErrorTest
 */

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Kdyby\CsobPaygateNette\UI\CsobControl;
use Kdyby\CsobPaymentGateway\Configuration;
use Kdyby\CsobPaymentGateway\Message\RedirectResponse;
use Kdyby\CsobPaymentGateway\Message\Response;
use Kdyby\CsobPaymentGateway\Payment;
use Kdyby\CsobPaymentGateway\PaymentException;
use Kdyby\CsobPaymentGateway\PaymentNotInValidStateException;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Jiří Pudil <me@jiripudil.cz>
 */
class CsobControlEapi16ErrorTest extends CsobTestCase
{

	protected function setUp()
	{
		parent::setUp();
		$this->prepareContainer('eapi16');

		$this->mockSignature();

		$oneclickResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
			'payId' => Nette\Utils\Random::generate(),
			'dttm' => (new \DateTime())->format('YmdGis'),
			'resultCode' => PaymentException::PAYMENT_NOT_IN_VALID_STATE,
			'resultMessage' => "orig payment not authorized",
			'signature' => 'signature',
		]));

		$httpClientMock = \Mockery::mock(Kdyby\CsobPaymentGateway\IHttpClient::class);
		$httpClientMock->shouldReceive('request')
			->with('POST', Configuration::DEFAULT_SANDBOX_URL . '/v1.6/payment/oneclick/init', \Mockery::type('array'), \Mockery::type('string'))
			->andReturn($oneclickResponse);

		$this->replaceService('csobPaygate.httpClient', $httpClientMock);

		$this->usePresenter('Test');
		$this->presenter['csob'];
	}



	public function testOneclickError()
	{
		$this->presenter['csob']->onInit[] = function (CsobControl $control, Payment $payment) {
			$payment->setOrderNo(15000002)
				->setOriginalPayId(15000001)
				->setDescription('Test payment')
				->addCartItem('Test item 1', 42 * 100, 1)
				->addCartItem('Test item 2', 158 * 100, 2);
		};

		$this->presenter['csob']->onCreated[] = function (CsobControl $control, Response $response) {
			Assert::same(PaymentException::PAYMENT_NOT_IN_VALID_STATE, $response->getResultCode());
			Assert::same("orig payment not authorized", $response->getResultMessage());
			Assert::null($response->getPaymentStatus());
		};

		$this->presenter['csob']->onProcess[] = function (CsobControl $control, RedirectResponse $redirect) {
			Assert::fail('The process handler should not be triggered.');
		};

		$this->presenter['csob']->onResponse[] = function (CsobControl $control, Response $response) {
			Assert::fail('The response handler should not be triggered.');
		};

		$this->presenter['csob']->onError[] = function (CsobControl $control, Kdyby\CsobPaymentGateway\Exception $exception, Response $response = NULL) {
			Assert::type(PaymentNotInValidStateException::class, $exception);
			Assert::same(PaymentException::PAYMENT_NOT_IN_VALID_STATE, $exception->getCode());
			Assert::same("orig payment not authorized", $exception->getMessage());

			Assert::notSame(NULL, $response);
			Assert::same(PaymentException::PAYMENT_NOT_IN_VALID_STATE, $response->getResultCode());
			Assert::same("orig payment not authorized", $response->getResultMessage());
			Assert::null($response->getPaymentStatus());

			$control->getPresenter()->terminate();
		};

		$this->runPresenterAction('pay');
	}

}

\run(new CsobControlEapi16ErrorTest());
