<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

call_user_func(function () {
    // SF/JW: Add simulate TSFE while rendering variables
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\In2code\Luxletter\Domain\Service\ParseNewsletterUrlService::class]['className']
        = \JWeiland\LuxletterExtended\Domain\Service\ParseNewsletterUrlService::class;

    $signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
        \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
    );

    // SF/JW: Initialize Extbase BackendConfiguration with correct page instead of using the first found site.
    $signalSlotDispatcher->connect(
        \In2code\Luxletter\Domain\Service\ParseNewsletterUrlService::class,
        'constructor',
        \JWeiland\LuxletterExtended\SignalSlot\BeforeParsingSignalSlot::class,
        'constructor'
    );
    // SF/JW: Initialize TSFE in BE with page related TS (Configuration/TypoScript/*.typoscript)
    // instead of using ext_typoscript_setup.txt only.
    $signalSlotDispatcher->connect(
        \JWeiland\LuxletterExtended\Domain\Service\ParseNewsletterUrlService::class,
        'getNewsletterContainerAndContentBeforeParsing',
        \JWeiland\LuxletterExtended\SignalSlot\BeforeParsingSignalSlot::class,
        'getNewsletterContainerAndContentBeforeParsing'
    );

    // SF/JW: Reset page-related TSFE back to global TSFE (ext_typoscript_setup.txt)
    $signalSlotDispatcher->connect(
        \JWeiland\LuxletterExtended\Domain\Service\ParseNewsletterUrlService::class,
        'getNewsletterContainerAndContent',
        \JWeiland\LuxletterExtended\SignalSlot\BeforeParsingSignalSlot::class,
        'resetTsfe'
    );
});
