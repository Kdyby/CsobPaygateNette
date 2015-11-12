<?php

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Kdyby\CsobPaymentGateway\Configuration;
use Nette;
use Tester;



class CsobTestCase extends Tester\TestCase
{

	/**
	 * @var Nette\DI\Container
	 */
	private $container;

	/**
	 * @var Nette\Application\UI\Presenter
	 */
	protected $presenter;



	protected function getContainer()
	{
		if ($this->container === NULL) {
			return $this->container = $this->createContainer();
		}

		return $this->container;
	}



	/**
	 * @return Nette\DI\Container
	 */
	protected function createContainer()
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addParameters(array('appDir' => __DIR__, 'testsDir' => __DIR__ . '/../..'));
		$config->addConfig(__DIR__ . '/../nette-reset.neon', !isset($config->defaultExtensions['nette']) ? 'v23' : 'v22');
		$config->addConfig(__DIR__ . '/config/default.neon');
		Kdyby\RequestStack\DI\RequestStackExtension::register($config);
		Kdyby\CsobPaygateNette\DI\CsobPaygateExtension::register($config);

		return $config->createContainer();
	}



	protected function usePresenter($name)
	{
		$sl = $this->getContainer();

		$presenterFactory = $sl->getByType(Nette\Application\IPresenterFactory::class);
		$presenter = $presenterFactory->createPresenter($name);

		if ($presenter instanceof Nette\Application\UI\Presenter) {
			$presenter->invalidLinkMode = $presenter::INVALID_LINK_EXCEPTION;
			$presenter->autoCanonicalize = FALSE;

			// force the name to the presenter
			$refl = new \ReflectionProperty(Nette\ComponentModel\Component::class, 'name');
			$refl->setAccessible(TRUE);
			$refl->setValue($presenter, $name);
		}

		$this->presenter = $presenter;
	}



	protected function runPresenterAction($action, $method = 'GET', $params = [], $post = [])
	{
		if (is_array($method)) {
			$post = $params;
			$params = $method;
			$method = 'GET';
		}

		$url = (new Nette\Http\UrlScript('https://kdyby.org'))->setQuery($params);
		$httpRequest = new Nette\Http\Request($url, NULL, $post, NULL, NULL, NULL, $method);
		$this->getContainer()->getByType(Kdyby\RequestStack\RequestStack::class)
			->pushRequest($httpRequest);

		$request = new Nette\Application\Request($this->presenter->getName(), $method, ['action' => $action] + $params, $post);
		$response = $this->presenter->run($request);
		return $response;
	}



	protected function mockSignature($verified = TRUE)
	{
		$privateKey = new Kdyby\CsobPaymentGateway\Certificate\PrivateKey(__DIR__ . '/../../../vendor//kdyby/csob-payment-gateway/examples/keys/rsa_A1029DTmM7.key', '');
		$publicKey = new Kdyby\CsobPaymentGateway\Certificate\PublicKey(Configuration::DEFAULT_CSOB_SANDBOX_CERT);

		$signatureMock = \Mockery::mock(Kdyby\CsobPaymentGateway\Message\Signature::class, [$privateKey, $publicKey])->shouldDeferMissing();
		$signatureMock->shouldReceive('verifyResponse')->andReturn($verified);

		$this->replaceService('csobPaygate.signature', $signatureMock);
	}



	protected function replaceService($name, $service)
	{
		$sl = $this->getContainer();
		$sl->removeService($name);
		$sl->addService($name, $service);
	}

}
