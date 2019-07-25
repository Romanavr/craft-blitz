<?php
/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace putyourlightson\blitz\console\controllers;

use Craft;
use craft\helpers\Console;
use putyourlightson\blitz\Blitz;
use putyourlightson\blitz\helpers\CacheTagHelper;
use putyourlightson\blitz\helpers\SiteUriHelper;
use putyourlightson\blitz\models\SiteUriModel;
use putyourlightson\blitz\utilities\CacheUtility;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Performs functions on the Blitz cache.
 */
class CacheController extends Controller
{
    // Properties
    // =========================================================================

    /**
     * @var array
     */
    private $_actions = [];

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        foreach (CacheUtility::getActions() as $action) {
            $this->_actions[$action['id']] = $action;
        }
    }

    public function getActionHelp($action): string
    {
        return $this->_actions[$action->id]['instructions'] ?? parent::getActionHelp($action);
    }

    /**
     * Lists the actions that can be taken.
     *
     * @return int
     */
    public function actionIndex(): int
    {
        $this->stdout(Craft::t('blitz','The following actions can be taken:').PHP_EOL.PHP_EOL, Console::FG_YELLOW);

        $actions = CacheUtility::getActions();

        $actions[] = [
            'id' => 'generate-expiry-dates',
            'label' => Craft::t('blitz', 'Generate Expiry Dates'),
            'instructions' => Craft::t('blitz', 'Generates expiry dates for entries and stores them to enable refreshing expired cache (this generally happens automatically).'),
        ];

        $lengths = [];
        foreach ($actions as $action) {
            $lengths[] = strlen($action['id']);
        }
        $maxLength = max($lengths);

        foreach ($actions as $action) {
            $this->stdout('- ');
            $this->stdout(str_pad($action['id'], $maxLength, ' '), Console::FG_YELLOW);
            $this->stdout('  '.$action['instructions'].PHP_EOL);
        }

        $this->stdout(PHP_EOL);

        return ExitCode::OK;
    }

    /**
     * Clears the cache (pages only).
     *
     * @return int
     */
    public function actionClear(): int
    {
        $this->_clearCache();

        return ExitCode::OK;
    }

    /**
     * Flushes the cache (database records only).
     *
     * @return int
     */
    public function actionFlush(): int
    {
        $this->_flushCache();

        return ExitCode::OK;
    }

    /**
     * Purges the cache (using reverse proxy purger).
     *
     * @return int
     */
    public function actionPurge(): int
    {
        $this->_purgeCache();

        return ExitCode::OK;
    }

    /**
     * Warms the entire cache.
     *
     * @return int
     */
    public function actionWarm(): int
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            $this->stderr(Craft::t('blitz', 'Blitz caching is disabled.').PHP_EOL, Console::FG_RED);

            return ExitCode::OK;
        }

        $this->_warmCache(SiteUriHelper::getAllSiteUris());

        return ExitCode::OK;
    }

    /**
     * Refreshes the entire cache.
     *
     * @return int
     */
    public function actionRefresh(): int
    {
        if (!Blitz::$plugin->settings->cachingEnabled) {
            $this->stderr(Craft::t('blitz', 'Blitz caching is disabled.').PHP_EOL, Console::FG_RED);

            return ExitCode::OK;
        }

        // Get cached site URIs before flushing the cache
        $siteUris = SiteUriHelper::getAllSiteUris();

        $this->_clearCache();
        $this->_flushCache();
        $this->_purgeCache();

        $this->_warmCache($siteUris);

        return ExitCode::OK;
    }

    /**
     * Refreshes expired cache.
     *
     * @return int
     */
    public function actionRefreshExpired(): int
    {
        Blitz::$plugin->refreshCache->refreshExpiredCache();

        Craft::$app->getQueue()->run();

        $this->stdout(Craft::t('blitz', 'Expired Blitz cache successfully refreshed.').PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Refreshes tagged cache.
     *
     * @param string
     *
     * @return int
     */
    public function actionRefreshTagged(string $tags = null): int
    {
        if ($tags === null) {
            $this->stderr(Craft::t('blitz', 'One or more tags must be provided as an argument.').PHP_EOL, Console::FG_RED);

            return ExitCode::OK;
        }

        $tags = CacheTagHelper::getTags($tags);
        Blitz::$plugin->refreshCache->refreshTaggedCache($tags);

        Craft::$app->getQueue()->run();

        $this->stdout(Craft::t('blitz', 'Tagged Blitz cache successfully refreshed.').PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Generates expiry dates for entries.
     *
     * @return int
     */
    public function actionGenerateExpiryDates(): int
    {
        Blitz::$plugin->refreshCache->generateExpiryDates();

        Craft::$app->getQueue()->run();

        $this->stdout(Craft::t('blitz', 'Entry expiry dates successfully generated.').PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Sets the request progress.
     *
     * @param int $count
     * @param int $total
     */
    public function setRequestsProgress(int $count, int $total)
    {
        Console::updateProgress($count, $total);
    }

    // Private Methods
    // =========================================================================

    private function _clearCache()
    {
        Blitz::$plugin->clearCache->clearAll();

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully cleared.').PHP_EOL, Console::FG_GREEN);
    }

    private function _flushCache()
    {
        Blitz::$plugin->flushCache->flushAll();

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully flushed.').PHP_EOL, Console::FG_GREEN);
    }

    private function _purgeCache()
    {
        Blitz::$plugin->cachePurger->purgeAll();

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully purged.').PHP_EOL, Console::FG_GREEN);
    }

    /**
     * @param SiteUriModel[] $siteUris
     */
    private function _warmCache(array $siteUris)
    {
        $this->stdout(Craft::t('blitz', 'Warming Blitz cache.').PHP_EOL, Console::FG_GREEN);

        $urls = SiteUriHelper::getUrls($siteUris);

        Console::startProgress(0, count($urls), '', 0.8);

        $success = Blitz::$plugin->warmCache->requestUrls($urls, [$this, 'setRequestsProgress']);

        Console::endProgress();

        $this->stdout(Craft::t('blitz', 'Blitz cache successfully warmed {success} pages.', ['success' => $success]).PHP_EOL, Console::FG_GREEN);

        // Check if there are any URLs beginning with `@web`
        if (!empty(preg_grep('/@web/i', $urls))) {
            $this->stderr(Craft::t('blitz', 'One or more sites use `@web` in their base URL which cannot be parsed by console commands.').PHP_EOL, Console::FG_RED);
        }
    }

}
