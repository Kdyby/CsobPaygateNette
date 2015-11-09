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
		'productionMode' => FALSE,
		'sandbox' => [
			'url' => Configuration::DEFAULT_SANDBOX_URL,
			'privateKey' => [
				'path' => NULL,
				'password' => NULL,
			],
			'publicKey' => NULL,
		],
		'production' => [
			'url' => Configuration::DEFAULT_PRODUCTION_URL,
			'privateKey' => [
				'path' => NULL,
				'password' => NULL,
			],
			'publicKey' => NULL,
		],
		'returnMethod' => Request::POST,
		'returnUrl' => NULL,
		'logging' => FALSE,
	];



	public function __construct()
	{
		$this->defaults['sandbox']['publicKey'] = Configuration::getCsobSandboxCertPath();
		$this->defaults['production']['publicKey'] = Configuration::getCsobProductionCertPath();
	}



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		Validators::assertField($config, 'merchantId', 'string');
		Validators::assertField($config, 'shopName', 'string');
		Validators::assertField($config, 'productionMode', 'bool');
		Validators::assertField($config, 'returnMethod', 'string|pattern:(GET|POST)');

		$envConfig = $config['productionMode'] ? $config['production'] : $config['sandbox'];

		if (is_string($envConfig['privateKey'])) {
			$envConfig['privateKey'] = [
				'path' => $envConfig['privateKey'],
				'password' => NULL
			];
		}

		if (empty($envConfig['privateKey']['path']) || !file_exists($envConfig['privateKey']['path'])) {
			throw new InvalidConfigException('Private key for not provided.');
		}

		$builder->addDefinition($this->prefix('config'))
			->setClass('Kdyby\CsobPaymentGateway\Configuration', [
				$config['merchantId'],
				$config['shopName'],
			])
			->addSetup('setUrl', [$envConfig['url']])
			->addSetup('setReturnUrl', [$config['returnUrl']])
			->addSetup('setReturnMethod', [$config['returnMethod']]);

		$builder->addDefinition($this->prefix('httpClient'))
			->setClass('Kdyby\CsobPaymentGateway\IHttpClient')
			->setFactory('Kdyby\CsobPaymentGateway\Http\GuzzleClient')
			->setAutowired(FALSE);

		$builder->addDefinition($this->prefix('signature'))
			->setClass('Kdyby\CsobPaymentGateway\Message\Signature', [
				new Statement('Kdyby\CsobPaymentGateway\Certificate\PrivateKey', [$envConfig['privateKey']['path'], $envConfig['privateKey']['password']]),
				new Statement('Kdyby\CsobPaymentGateway\Certificate\PublicKey', [$envConfig['publicKey']]),
			])
			->setAutowired(FALSE);

		$client = $builder->addDefinition($this->prefix('client'))
			->setClass('Kdyby\CsobPaymentGateway\Client', [
				$this->prefix('@config'),
				$this->prefix('@signature'),
				$this->prefix('@httpClient')
			]);

		if ($config['logging']) {
			if (!is_bool($config['logging']) && class_exists('Kdyby\Monolog\Logger')) {
				$client->addSetup('setLogger', [new Statement('@Kdyby\Monolog\Logger::channel', [$config['logging']])]);

			} else {
				$client->addSetup('setLogger'); // autowire
			}
		}

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
