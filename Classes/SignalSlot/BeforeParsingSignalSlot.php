<?php
declare(strict_types=1);
namespace JWeiland\LuxletterExtended\SignalSlot;

use In2code\Luxletter\Domain\Model\User;
use JWeiland\LuxletterExtended\Domain\Service\ParseNewsletterUrlService;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\WorkspaceAspect;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Aspect\PreviewAspect;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * SF/JW: A solution to build correct page-related TS configuration
 * Must be a SingleTon, as we have to reset TSFE in a later SignalSlot
 */
class BeforeParsingSignalSlot implements SingletonInterface
{
    /**
     * @var TypoScriptFrontendController
     */
    protected $backupTsfe;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var SiteMatcher
     */
    protected $matcher;

    public function __construct(Context $context, SiteMatcher $matcher)
    {
        $this->context = $context;
        $this->matcher = $matcher;
    }

    public function getNewsletterContainerAndContentBeforeParsing(
        string $content,
        array &$configuration,
        User $user,
        ParseNewsletterUrlService $pObj
    ): void {
        $this->backupTsfe = $GLOBALS['TSFE'] ?? null;
        $this->initializeTSFE((int)$pObj->getOrigin());
        $setup = $GLOBALS['TSFE']->tmpl->setup;
        if (isset($setup['plugin.']['tx_luxletter_fe.'])) {
            $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
            ArrayUtility::mergeRecursiveWithOverrule(
                $configuration,
                $typoScriptService->convertTypoScriptArrayToPlainArray($setup['plugin.']['tx_luxletter_fe.'])
            );
        }
    }

    public function resetTsfe(): void
    {
        $GLOBALS['TSFE'] = $this->backupTsfe;
    }

    protected function initializeTSFE(int $pageUid): void
    {
        if (
            !$GLOBALS['TSFE'] instanceof TypoScriptFrontendController
            || $GLOBALS['TSFE']->id != $pageUid
        ) {
            $request = $this->getTypo3Request($pageUid);
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($pageUid);
            $this->context->setAspect('frontend.preview', GeneralUtility::makeInstance(PreviewAspect::class));
            $pageArguments = $request->getAttribute('routing', null);

            $controller = GeneralUtility::makeInstance(
                TypoScriptFrontendController::class,
                $this->context,
                $site,
                $request->getAttribute('language', $site->getDefaultLanguage()),
                $pageArguments,
                $request->getAttribute('frontend.user', null)
            );
            if ($pageArguments->getArguments()['no_cache'] ?? $request->getParsedBody()['no_cache'] ?? false) {
                $controller->set_no_cache('&no_cache=1 has been supplied, so caching is disabled! URL: "' . (string)$request->getUri() . '"');
            }
            // Usually only set by the PageArgumentValidator
            if ($request->getAttribute('noCache', false)) {
                $controller->no_cache = 1;
            }

            $controller->determineId();

            // No access? Then remove user and re-evaluate the page id
            if ($controller->isBackendUserLoggedIn() && !$GLOBALS['BE_USER']->doesUserHaveAccess($controller->page, Permission::PAGE_SHOW)) {
                unset($GLOBALS['BE_USER']);
                // Register an empty backend user as aspect
                $this->setBackendUserAspect(null);
                $controller->determineId();
            }

            $controller->getFromCache($request);
            $controller->getConfigArray();
            $controller->settingLanguage();

            $GLOBALS['TSFE'] = $controller;
        }
    }

    protected function getTypo3Request(int $pageUid): Request
    {
        $request = $GLOBALS['TYPO3_REQUEST'];
        $queryParams = $request->getQueryParams();
        $queryParams['id'] = $pageUid;
        $queryParams['L'] = 0;
        $request = $request->withQueryParams($queryParams);

        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->start();
        $frontendUser->unpack_uc();
        $this->context->setAspect(
            'frontend.user',
            GeneralUtility::makeInstance(UserAspect::class, $frontendUser)
        );
        $request = $request->withAttribute('frontend.user', $frontendUser);

        /** @var SiteRouteResult $routeResult */
        $routeResult = $this->matcher->matchRequest($request);
        $site = $routeResult->getSite();
        $request = $request->withAttribute('site', $site);
        $request = $request->withAttribute('language', $routeResult->getLanguage());
        $request = $request->withAttribute('routing', $routeResult);
        if ($routeResult->getLanguage() instanceof SiteLanguage) {
            Locales::setSystemLocaleFromSiteLanguage($routeResult->getLanguage());
        }

        if ($site instanceof Site) {
            /** @var SiteRouteResult $previousResult */
            $previousResult = $request->getAttribute('routing', null);
            $pageArguments = $site->getRouter()->matchRequest($request, $previousResult);
            $request = $request->withAttribute('routing', $pageArguments);
        }

        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $request;
    }

    /**
     * Register the backend user as aspect
     *
     * @param BackendUserAuthentication|null $user
     */
    protected function setBackendUserAspect(?BackendUserAuthentication $user): void
    {
        $this->context->setAspect('backend.user', GeneralUtility::makeInstance(UserAspect::class, $user));
        $this->context->setAspect('workspace', GeneralUtility::makeInstance(WorkspaceAspect::class, $user ? $user->workspace : 0));
    }
}
