<?php
$output = '';
switch ($options[xPDOTransport::PACKAGE_ACTION]) {
	case xPDOTransport::ACTION_INSTALL:
		$output = '<h2>Mirror Installer</h2><p>Thanks for installing Mirror! Please review the setup options below before proceeding.</p><br />';
		break;
	case xPDOTransport::ACTION_UPGRADE:
	case xPDOTransport::ACTION_UNINSTALL:
		break;
}
return $output;
