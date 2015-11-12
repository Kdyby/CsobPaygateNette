<?php

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Kdyby\CsobPaymentGateway\Configuration;
use Nette;
use Tester;



class CsobTestCase extends Tester\TestCase
{

	/**
	 * @var \SystemContainer[]|Nette\DI\Container[]
	 */
	private $containers;

	/**
	 * @var Nette\Application\UI\Presenter
	 */
	protected $presenter;



	protected function getContainer($configFile)
	{
		if (!isset($this->containers[$configFile])) {
			return $this->createContainer($configFile);
		}

		return $this->containers[$configFile];
	}



	/**
	 * @param string $configFile
	 * @return \SystemContainer|Nette\DI\Container
	 */
	protected function createContainer($configFile)
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addParameters(array('container' => array('class' => 'SystemContainer_' . md5($configFile))));
		$config->addParameters(array('appDir' => __DIR__, 'testsDir' => __DIR__ . '/../..'));
		$config->addConfig(__DIR__ . '/../nette-reset.neon', !isset($config->defaultExtensions['nette']) ? 'v23' : 'v22');
		$config->addConfig(__DIR__ . '/config/' . $configFile . '.neon');
		Kdyby\CsobPaygateNette\DI\CsobPaygateExtension::register($config);

		return $this->containers[$configFile] = $config->createContainer();
	}



	protected function usePresenter($name)
	{
		$sl = $this->getContainer('default');

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
		$sl = $this->getContainer('default');

		if (is_array($method)) {
			$post = $params;
			$params = $method;
			$method = 'GET';
		}

		$url = (new Nette\Http\UrlScript('https://kdyby.org'))->setQuery($params);
		$httpRequest = new Nette\Http\Request($url, NULL, $post, NULL, NULL, NULL, $method);

		// force httpRequest to the presenter
		$refl = new \ReflectionProperty(Nette\Application\UI\Presenter::class, 'httpRequest');
		$refl->setAccessible(TRUE);
		$refl->setValue($this->presenter, $httpRequest);

		// force httpRequest to the CsobControl
		$refl = new \ReflectionProperty(Kdyby\CsobPaygateNette\UI\CsobControl::class, 'httpRequest');
		$refl->setAccessible(TRUE);
		$refl->setValue($this->presenter['csob'], $httpRequest);

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
		$sl = $this->getContainer('default');
		$sl->removeService($name);
		$sl->addService($name, $service);
	}

}
