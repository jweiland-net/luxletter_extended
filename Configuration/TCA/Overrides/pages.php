<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::registerPageTSConfigFile(
    'luxletter_extended',
    'Configuration/TSconfig/Page.tsconfig',
    'jweiland.net Luxletter Extended'
);
