<?php
/**
 * Nextcloud - twitter
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Twitter\Controller;

use OCP\App\IAppManager;
use OCP\Files\IAppData;
use OCP\AppFramework\Http\DataDisplayResponse;

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\AppFramework\Http\ContentSecurityPolicy;

use OCP\ILogger;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Twitter\Service\TwitterAPIService;
use OCA\Twitter\AppInfo\Application;

require_once __DIR__ . '/../constants.php';

class TwitterAPIController extends Controller {


    private $userId;
    private $config;
    private $dbconnection;
    private $dbtype;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IL10N $l10n,
                                IAppManager $appManager,
                                IAppData $appData,
                                ILogger $logger,
                                TwitterAPIService $twitterAPIService,
                                $userId) {
        parent::__construct($AppName, $request);
        $this->userId = $userId;
        $this->l10n = $l10n;
        $this->appData = $appData;
        $this->serverContainer = $serverContainer;
        $this->config = $config;
        $this->logger = $logger;
        $this->twitterAPIService = $twitterAPIService;
        $this->consumerKey = $this->config->getAppValue(Application::APP_ID, 'consumer_key', DEFAULT_TWITTER_CONSUMER_KEY);
        $this->consumerSecret = $this->config->getAppValue(Application::APP_ID, 'consumer_secret', DEFAULT_TWITTER_CONSUMER_SECRET);
        $this->consumerKey = $this->consumerKey ? $this->consumerKey : DEFAULT_TWITTER_CONSUMER_KEY;
        $this->consumerSecret = $this->consumerSecret ? $this->consumerSecret : DEFAULT_TWITTER_CONSUMER_SECRET;
        $this->oauthToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_token', '');
        $this->oauthTokenSecret = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_token_secret', '');
    }

    /**
     * get notification list
     * @NoAdminRequired
     */
    public function getNotifications($since = null) {
        if ($this->oauthToken === '') {
            return new DataResponse([], 400);
        }
        $result = $this->twitterAPIService->getNotifications($this->consumerKey, $this->consumerSecret, $this->oauthToken, $this->oauthTokenSecret, $since);
        if (!is_string($result)) {
            $response = new DataResponse($result);
        } else {
            $response = new DataResponse($result, 401);
        }
        return $response;
    }

    /**
     * get repository avatar
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getAvatar($url) {
        $response = new DataDisplayResponse($this->twitterAPIService->getAvatar($url));
        $response->cacheFor(60*60*24);
        return $response;
    }

}
