<?php
/**
 * @package		Mark As Sold
 * @author		Lurtz7
 * @copyright	2026
 */

namespace IPS\markassold;

class _Application extends \IPS\Application
{
	/**
	 * Icon for the application in AdminCP
	 *
	 * @return	string	Font Awesome icon name
	 */
	protected function get__icon(): string
	{
		return 'tag';
	}
}
