<?php

/**
 * Test: Kdyby\CsobPaygateNette\CsobControl
 *
 * @testCase Kdyby\CsobPaygateNette\CsobControlCheckTest
 */

namespace KdybyTests\CsobPaygateNette;

use Kdyby;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Jiří Pudil <me@jiripudil.cz>
 */
class CsobControlCheckTest extends CsobTestCase
{

	protected function setUp()
	{
		parent::setUp();
		$this->usePresenter('Test');
		$this->presenter['csob'];
	}



	public function testNotAttached()
	{
		Assert::throws(function () {
			$this->getContainer('default')->getByType(Kdyby\CsobPaygateNette\UI\ICsobControlFactory::class)->create()->pay();
		}, Kdyby\CsobPaygateNette\InvalidStateException::class, "WebPay control '' is not attached to 'Presenter'.");
	}



	public function testNoInitHandler()
	{
		Assert::throws(function () {
			$this->runPresenterAction('pay');
		}, Kdyby\CsobPaygateNette\InvalidStateException::class, "You must specify at least one 'onInit' event.");
	}



	public function testNoResponseHandler()
	{
		$this->presenter['csob']->onInit[] = function () {};

		Assert::throws(function () {
			$this->runPresenterAction('pay');
		}, Kdyby\CsobPaygateNette\InvalidStateException::class, "You must specify at least one 'onResponse' event.");
	}



	public function testNoErrorHandler()
	{
		$this->presenter['csob']->onInit[] = function () {};
		$this->presenter['csob']->onResponse[] = function () {};

		Assert::throws(function () {
			$this->runPresenterAction('pay');
		}, Kdyby\CsobPaygateNette\InvalidStateException::class, "You must specify at least one 'onError' event.");
	}

}

\run(new CsobControlCheckTest());
