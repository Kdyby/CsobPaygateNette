<?php

/**
 * Test: Kdyby\CsobPaygateNette\Extension.
 *
 * @testCase Kdyby\CsobPaygateNette\ExtensionTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\CsobPaygateNette
 */

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtensionTest extends Tester\TestCase
{

	/**
	 * @param string $configFile
	 * @return \SystemContainer|Nette\DI\Container
	 */
	public function createContainer($configFile)
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->addParameters(array('container' => array('class' => 'SystemContainer_' . md5($configFile))));
		$config->addParameters(array('appDir' => __DIR__, 'testsDir' => __DIR__ . '/../..'));
		$config->addConfig(__DIR__ . '/../nette-reset.neon', !isset($config->defaultExtensions['nette']) ? 'v23' : 'v22');
		$config->addConfig(__DIR__ . '/config/' . $configFile . '.neon');
		Kdyby\CsobPaygateNette\DI\CsobPaygateExtension::register($config);

		return $config->createContainer();
	}



	public function testFunctional()
	{
		$container = $this->createContainer('default');
		Assert::truthy($container->getByType('Kdyby\CsobPaymentGateway\Client'));
		Assert::truthy($container->getByType('Kdyby\CsobPaygateNette\UI\ICsobControlFactory'));
	}

}

\run(new ExtensionTest());
