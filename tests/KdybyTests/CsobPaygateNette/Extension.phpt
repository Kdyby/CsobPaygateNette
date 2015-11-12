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
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtensionTest extends CsobTestCase
{

	public function testFunctional()
	{
		$container = $this->getContainer();
		Assert::truthy($container->getByType('Kdyby\CsobPaymentGateway\Client'));
		Assert::truthy($container->getByType('Kdyby\CsobPaygateNette\UI\ICsobControlFactory'));
		Assert::type('Kdyby\CsobPaygateNette\UI\CsobControl', $container->getByType('Kdyby\CsobPaygateNette\UI\ICsobControlFactory')->create());
	}

}

\run(new ExtensionTest());
