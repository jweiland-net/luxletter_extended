<?php
declare(strict_types=1);
namespace JWeiland\LuxletterExtended\Domain\Service;

use In2code\Luxletter\Domain\Model\User;
use In2code\Luxletter\Utility\ConfigurationUtility;
use In2code\Luxletter\Utility\ObjectUtility;
use In2code\Luxletter\Utility\TemplateUtility;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * SF/JW: Contains this patch: https://github.com/in2code-de/luxletter/pull/46
 * It adds simulate TSFE for variable rendering
 */
class ParseNewsletterUrlService extends \In2code\Luxletter\Domain\Service\ParseNewsletterUrlService
{
    /**
     * Hold origin
     *
     * @var string
     */
    protected $origin = '';

    /**
     * Contains a backup of the current $GLOBALS['TSFE'] if used in BE mode
     *
     * @var TypoScriptFrontendController
     */
    protected static $tsfeBackup;

    /**
     * ParseNewsletterUrlService constructor.
     * @param string $origin can be a page uid or a complete url
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     * @throws Exception
     */
    public function __construct(string $origin)
    {
        $this->setOrigin($origin);
        parent::__construct($origin);
    }

    /**
     * @param string $content
     * @param User $user
     * @return string
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     * @throws InvalidConfigurationTypeException
     * @throws Exception
     */
    protected function getNewsletterContainerAndContent(string $content, User $user): string
    {
        $templateName = 'Mail/NewsletterContainer.html';
        if ($this->isParsingActive()) {
            $configuration = ConfigurationUtility::getExtensionSettings();
            $this->signalDispatch(__CLASS__, __FUNCTION__ . 'BeforeParsing', [$content, &$configuration, $user, $this]);
            $standaloneView = ObjectUtility::getObjectManager()->get(StandaloneView::class);
            $standaloneView->setTemplateRootPaths($configuration['view']['templateRootPaths']);
            $standaloneView->setLayoutRootPaths($configuration['view']['layoutRootPaths']);
            $standaloneView->setPartialRootPaths($configuration['view']['partialRootPaths']);
            $standaloneView->setTemplate($templateName);
            $standaloneView->assignMultiple($this->getContentObjectVariables($configuration ?? []));
            $standaloneView->assignMultiple(
                [
                    'content' => $content,
                    'user' => $user,
                    'settings' => $configuration['settings'] ?? []
                ]
            );
            $html = $standaloneView->render();
        } else {
            $container = file_get_contents(TemplateUtility::getExistingFilePathOfTemplateFileByName($templateName));
            $html = str_replace('{content}', $content, $container);
        }
        $this->signalDispatch(__CLASS__, __FUNCTION__, [$html, $content, $user, $this]);
        return $html;
    }

    /**
     * Compile rendered content objects in variables array ready to assign to the view
     *
     *  Example TypoScript:
     *      plugin {
     *          tx_luxletter_fe {
     *              variables {
     *                  subject = TEXT
     *                  subject.value = My own Newsletter
     *              }
     *          }
     *      }
     *
     * @param array $configuration TypoScript configuration array
     * @return array the variables to be assigned
     * @throws Exception
     */
    protected function getContentObjectVariables(array $configuration): array
    {
        if (TYPO3_MODE === 'BE') {
            self::simulateFrontendEnvironment();
        }

        $variables = parent::getContentObjectVariables($configuration);

        if (TYPO3_MODE === 'BE') {
            self::resetFrontendEnvironment();
        }

        return $variables;
    }

    /**
     * Sets the $TSFE->cObjectDepthCounter in Backend mode
     * This somewhat hacky work around is currently needed because the cObjGetSingle() function of \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer relies on this setting
     */
    protected static function simulateFrontendEnvironment()
    {
        if (!$GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
            $GLOBALS['TSFE'] = new \stdClass();
            $GLOBALS['TSFE']->cObj = ObjectUtility::getContentObject();
            $GLOBALS['TSFE']->cObjectDepthCounter = 100;
        }
    }

    /**
     * Resets $GLOBALS['TSFE'] if it was previously changed by simulateFrontendEnvironment()
     *
     * @see simulateFrontendEnvironment()
     */
    protected static function resetFrontendEnvironment()
    {
        if (!$GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
            $GLOBALS['TSFE'] = null;
        }
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function setOrigin(string $origin)
    {
        $this->origin = $origin;
    }
}
