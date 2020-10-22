<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('luxletter_extended', 'Configuration/TypoScript', 'jweiland.net Luxletter Extended');
