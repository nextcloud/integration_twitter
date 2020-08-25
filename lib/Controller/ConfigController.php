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

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IL10N;
use OCP\ILogger;

use OCP\IRequest;
use OCP\IDBConnection;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Controller;

require_once __DIR__ . '/../constants.php';

use OCA\Twitter\Service\TwitterAPIService;
use OCA\Twitter\AppInfo\Application;

class ConfigController extends Controller {


    private $userId;
    private $config;
    private $dbconnection;
    private $dbtype;

    public function __construct($AppName,
                                IRequest $request,
                                IServerContainer $serverContainer,
                                IConfig $config,
                                IAppManager $appManager,
                                IAppData $appData,
                                IDBConnection $dbconnection,
                                IURLGenerator $urlGenerator,
                                IL10N $l,
                                ILogger $logger,
                                TwitterAPIService $twitterAPIService,
                                $userId) {
        parent::__construct($AppName, $request);
        $this->l = $l;
        $this->appName = $AppName;
        $this->userId = $userId;
        $this->appData = $appData;
        $this->serverContainer = $serverContainer;
        $this->config = $config;
        $this->dbconnection = $dbconnection;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->twitterAPIService = $twitterAPIService;
    }

    /**
     * set config values
     * @NoAdminRequired
     */
    public function setConfig($values) {
        foreach ($values as $key => $value) {
            $this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
        }
        $response = new DataResponse(1);
        return $response;
    }

    /**
     * set admin config values
     */
    public function setAdminConfig($values) {
        foreach ($values as $key => $value) {
            $this->config->setAppValue(Application::APP_ID, $key, $value);
        }
        $response = new DataResponse(1);
        return $response;
    }

    /**
     * perform 1st step of 3-legged twitter oauth
     * @NoAdminRequired
     */
    public function doOauthStep1() {
        $consumerKey = $this->config->getAppValue(Application::APP_ID, 'consumer_key', DEFAULT_CONSUMER_KEY);
        $consumerSecret = $this->config->getAppValue(Application::APP_ID, 'consumer_secret', DEFAULT_CONSUMER_SECRET);
        $consumerKey = $consumerKey ? $consumerKey : DEFAULT_CONSUMER_KEY;
        $consumerSecret = $consumerSecret ? $consumerSecret : DEFAULT_CONSUMER_SECRET;

        $requestToken = $this->twitterAPIService->requestTokenOAuthStep1($consumerKey, $consumerSecret);
        if (!isset($requestToken['oauth_token']) or !isset($requestToken['oauth_token_secret'])) {
            return new DataResponse($this->l->t('Problem in OAuth first step'));
        }

        // save token of application to session
        $oauthToken = $requestToken['oauth_token'];
        $oauthTokenSecret = $requestToken['oauth_token_secret'];
        $this->config->setUserValue($this->userId, Application::APP_ID, 'tmp_oauth_token', $oauthToken);
        $this->config->setUserValue($this->userId, Application::APP_ID, 'tmp_oauth_token_secret', $oauthTokenSecret);

        return new DataResponse('https://api.twitter.com/oauth/authorize?oauth_token=' . $oauthToken);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function oauthRedirect($url) {
        $parts = parse_url($url);
        parse_str($parts['query'], $params);
        $oauthVerifier = $params['oauth_verifier'];

        $consumerKey = $this->config->getAppValue(Application::APP_ID, 'consumer_key', DEFAULT_CONSUMER_KEY);
        $consumerSecret = $this->config->getAppValue(Application::APP_ID, 'consumer_secret', DEFAULT_CONSUMER_SECRET);
        $consumerKey = $consumerKey === '' ? DEFAULT_CONSUMER_KEY : $consumerKey;
        $consumerSecret = $consumerSecret === '' ? DEFAULT_CONSUMER_SECRET : $consumerSecret;

        $oauthToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'tmp_oauth_token', '');
        $oauthTokenSecret = $this->config->getUserValue($this->userId, Application::APP_ID, 'tmp_oauth_token_secret', '');

        if (empty($oauthVerifier) || $oauthToken === '' || $oauthTokenSecret === '') {
            $result = $this->l->t('Problem in OAuth first or second step');
            return new RedirectResponse(
                $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
                '?twitterToken=error&message=' . urlencode($result)
            );
        }

        $token = $this->twitterAPIService->requestTokenOAuthStep3($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, $oauthVerifier);
        if (!isset($token['oauth_token']) or !isset($token['oauth_token_secret'])) {
            $result = $this->l->t('Problem in OAuth third step.');
            $result .= ' ' . $e->getMessage();
            return new RedirectResponse(
                $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
                '?twitterToken=error&message=' . urlencode($result)
            );
        }
        $oauthToken = $token['oauth_token'];
        $oauthTokenSecret = $token['oauth_token_secret'];
        $this->config->setUserValue($this->userId, Application::APP_ID, 'oauth_token', $oauthToken);
        $this->config->setUserValue($this->userId, Application::APP_ID, 'oauth_token_secret', $oauthTokenSecret);
        return new RedirectResponse(
            $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
            '?twitterToken=success'
        );
    }
}
