<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\CsobPaygateNette\DI;

use Kdyby;
use Kdyby\CsobPaygateNette\InvalidConfigException;
use Kdyby\CsobPaymentGateway\Configuration;
use Kdyby\CsobPaymentGateway\Message\Request;
use Nette;
use Nette\DI\Statement;
use Nette\PhpGenerator as Code;
use Nette\Utils\Validators;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class CsobPaygateExtension extends Nette\DI\CompilerExtension
{

	/**
	 * @var array
	 */
	public $defaults = [
		'merchantId' => NULL,
		'shopName' => 'E-shop',
		'sandbox' => TRUE,
		'url' => NULL,
		'privateKey' => [
			'path' => NULL,
			'password' => NULL,
		],
		'publicKey' => NULL,
		'returnMethod' => Request::POST,
		'returnUrl' => NULL,
	];



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		if (empty($config['publicKey'])) {
			$config['publicKey'] = $config['sandbox']
				? Configuration::getCsobSandboxCertPath()
				: Configuration::getCsobProductionCertPath();
		}

		if (empty($config['url'])) {
			$config['url'] = $config['sandbox']
				? Configuration::DEFAULT_SANDBOX_URL
				: Configuration::DEFAULT_PRODUCTION_URL;
		}

		if (is_string($config['privateKey'])) {
			$config['privateKey'] = [
				'path' => $config['privateKey'],
				'password' => NULL
			];
		}

		if (empty($config['privateKey']['path']) || !file_exists($config['privateKey']['path'])) {
			throw new InvalidConfigException('Private key for not provided.');
		}

		Validators::assertField($config, 'merchantId', 'string:');

		$builder->addDefinition($this->prefix('config'))
			->setClass('Kdyby\CsobPaymentGateway\Configuration', [
				$config['merchantId'],
				$config['shopName'],
			])
			->addSetup('setUrl', [$config['url']])
			->addSetup('setReturnUrl', [$config['returnUrl']])
			->addSetup('setReturnMethod', [$config['returnMethod']]);

		$builder->addDefinition($this->prefix('client'))
			->setClass('Kdyby\CsobPaymentGateway\Client', [
				$this->prefix('@config'),
				new Statement('Kdyby\CsobPaymentGateway\Certificate\PrivateKey', [$config['privateKey']['path'], $config['privateKey']['password']]),
				new Statement('Kdyby\CsobPaymentGateway\Certificate\PublicKey', [$config['publicKey']]),
				new Statement('Bitbang\Http\Clients\CurlClient')
			]);

		$builder->addDefinition($this->prefix('control'))
			->setImplement('Kdyby\CsobPaygateNette\UI\ICsobControlFactory')
			->setArguments([
				$this->prefix('@client')
			]);
	}



	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('csobPaygate', new CsobPaygateExtension());
		};
	}

}

