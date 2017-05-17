<?php

namespace KdybyTests\CsobPaygateNette;

use Kdyby\CsobPaygateNette\UI\ICsobCheckoutControlFactory;
use Nette\Application\UI\Presenter;



class TestCheckoutPresenter extends Presenter
{

	/**
	 * @var ICsobCheckoutControlFactory
	 */
	private $checkoutControlFactory;


	public function __construct(ICsobCheckoutControlFactory $checkoutControlFactory)
	{
		$this->checkoutControlFactory = $checkoutControlFactory;
	}



	public function renderCheckout()
	{
		$this['checkout']->render();
	}



	protected function createComponentCheckout()
	{
		return $this->checkoutControlFactory->create('42');
	}

}
