<?php

/**
 * Test: Kdyby\CsobPaygateNette\CsobControl
 *
 * @testCase Kdyby\CsobPaygateNette\CsobControlRecurrentPaymentTest
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
class CsobControlRecurrentPaymentTest extends CsobTestCase
{

	protected function setUp()
	{
		parent::setUp();
		$this->mockSignature();

		$apiResponse = new \GuzzleHttp\Psr7\Response(200, [], json_encode([
			'payId' => Nette\Utils\Random::generate(),
			'dttm' => (new \DateTime())->format('YmdGis'),
			'resultCode' => 0,
			'resultMessage' => 'OK',
			'paymentStatus' => 7,
			'authCode' => 123456,
			'signature' => 'signature',
		]));

		$httpClientMock = \Mockery::mock(Kdyby\CsobPaymentGateway\IHttpClient::class);
		$httpClientMock->shouldReceive('request')
			->with('POST', Configuration::DEFAULT_SANDBOX_URL . '/payment/recurrent', \Mockery::type('array'), \Mockery::type('string'))
			->andReturn($apiResponse);

		$this->replaceService('csobPaygate.httpClient', $httpClientMock);

		$this->usePresenter('Test');
		$this->presenter['csob'];
	}



	public function testRecurrentPayment()
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
			Assert::same(Payment::STATUS_TO_CLEARING, $response->getPaymentStatus());
		};

		$this->presenter['csob']->onProcess[] = function (CsobControl $control, RedirectResponse $redirect) {
			Assert::fail('The process handler should not be triggered.');
		};

		$this->presenter['csob']->onResponse[] = function (CsobControl $control, Response $response) {
			Assert::notSame(NULL, $response->getPayId());
			Assert::same(0, $response->getResultCode());
			Assert::same('OK', $response->getResultMessage());
			Assert::same(Payment::STATUS_TO_CLEARING, $response->getPaymentStatus());

			$control->getPresenter()->terminate();
		};

		$this->presenter['csob']->onError[] = function (CsobControl $control, Kdyby\CsobPaymentGateway\Exception $exception, Response $response = NULL) {
			Assert::fail('The error handler should not be triggered.');
		};

		$this->runPresenterAction('pay');
	}



	protected function tearDown()
	{
		\Mockery::close();
	}

}

\run(new CsobControlRecurrentPaymentTest());
