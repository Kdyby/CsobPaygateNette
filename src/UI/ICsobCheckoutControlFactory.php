<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\CsobPaygateNette\UI;



/**
 * @author Jiří Pudil <me@jiripudil.cz>
 */
interface ICsobCheckoutControlFactory
{

	/**
	 * @param string $paymentId
	 * @return CsobCheckoutControl
	 */
	public function create($paymentId);

}
