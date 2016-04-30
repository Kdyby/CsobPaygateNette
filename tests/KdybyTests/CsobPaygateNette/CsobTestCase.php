<?php

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Kdyby\CsobPaymentGateway\Configuration;
use Kdyby\CsobPaymentGateway\Certificate;
use Nette;
use Tester;



abstract class CsobTestCase extends Tester\TestCase
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
			throw new \LogicException('First call ' . get_called_class() . '::prepareContainer($configName) to initialize the container.');
		}

		return $this->container;
	}



	/**
	 * @return Nette\DI\Container
	 */
	protected function prepareContainer($configFile)
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addParameters(['appDir' => __DIR__, 'testsDir' => __DIR__ . '/../..']);
		$config->addConfig(sprintf(__DIR__ . '/../nette-reset.neon'));
		$config->addConfig(sprintf(__DIR__ . '/../nette-reset.%s.neon', !isset($config->defaultExtensions['nette']) ? 'v2.3' : 'v2.2'));
		$config->addConfig(__DIR__ . '/config/' . $configFile . '.neon');
		Kdyby\CsobPaygateNette\DI\CsobPaygateExtension::register($config);

		return $this->container = $config->createContainer();
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
		if ($this->presenter === NULL) {
			throw new \LogicException('Call first ' . get_called_class() . '::usePresenter($name) to initialize the presenter.');
		}

		if (is_array($method)) {
			$post = $params;
			$params = $method;
			$method = 'GET';
		}

		$requestStack = $this->getContainer()->getByType(Kdyby\RequestStack\RequestStack::class);

		$url = (new Nette\Http\UrlScript('https://kdyby.org'))->setQuery($params);
		$requestStack->pushRequest(new Nette\Http\Request($url, NULL, $post, NULL, NULL, NULL, $method));

		$request = new Nette\Application\Request($this->presenter->getName(), $method, ['action' => $action] + $params, $post);
		return $this->presenter->run($request);
	}



	protected function mockSignature($verified = TRUE)
	{
		$privateKey = new Certificate\PrivateKey(__DIR__ . '/../../../vendor/kdyby/csob-payment-gateway/examples/keys/rsa_A1029DTmM7.key', NULL);
		$publicKey = new Certificate\PublicKey(Configuration::DEFAULT_CSOB_SANDBOX_CERT);

		$signatureMock = \Mockery::mock(Kdyby\CsobPaymentGateway\Message\Signature::class, [$privateKey, $publicKey])->shouldDeferMissing();
		$signatureMock->shouldReceive('verifyResponse')->andReturn($verified);

		$this->replaceService('csobPaygate.signature', $signatureMock);

		return $signatureMock;
	}



	protected function replaceService($name, $service)
	{
		$sl = $this->getContainer();
		$sl->removeService($name);
		$sl->addService($name, $service);
	}

}
