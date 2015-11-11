<?php

namespace KdybyTests\CsobPaygateNette;

use Kdyby\CsobPaygateNette\UI\ICsobControlFactory;
use Nette\Application\UI\Presenter;



class TestPresenter extends Presenter
{

	/**
	 * @var ICsobControlFactory
	 */
	private $csobFactory;



	public function __construct(ICsobControlFactory $csobFactory)
	{
		$this->csobFactory = $csobFactory;
	}



	public function renderPay()
	{
		$this['csob']->pay();
	}



	protected function createComponentCsob()
	{
		return $this->csobFactory->create();
	}

}
