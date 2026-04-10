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

	/**
	 * Output CSS custom properties for tag colors
	 *
	 * Called from IPS output. Adds an inline style block with the
	 * admin-configured colors as CSS custom properties.
	 *
	 * @return void
	 */
	public function outputCss(): void
	{
		$bgColor   = \IPS\Settings::i()->markassold_bg_color ?: '#e74c3c';
		$textColor = \IPS\Settings::i()->markassold_text_color ?: '#ffffff';
		$tagName   = \IPS\Settings::i()->markassold_tag ?: 'Sold';

		\IPS\Output::i()->headCss .= <<<CSS
<style>
:root {
	--markassold-bg: {$bgColor};
	--markassold-text: {$textColor};
}
.ipsTag[data-tag="{$tagName}"] {
	background-color: var(--markassold-bg) !important;
	color: var(--markassold-text) !important;
	font-weight: 700;
	border: none !important;
	text-transform: uppercase;
	letter-spacing: 0.5px;
}
</style>
CSS;
	}
}
