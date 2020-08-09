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
use OCP\Http\Client\IClientService;

require_once __DIR__ . '/../../vendor/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;
use Abraham\TwitterOAuth\TwitterOAuthException;

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
                                IClientService $clientService,
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
        $this->clientService = $clientService;
    }

    /**
     * set config values
     * @NoAdminRequired
     */
    public function setConfig($values) {
        foreach ($values as $key => $value) {
            $this->config->setUserValue($this->userId, 'twitter', $key, $value);
        }
        $response = new DataResponse(1);
        return $response;
    }

    /**
     * set admin config values
     */
    public function setAdminConfig($values) {
        foreach ($values as $key => $value) {
            $this->config->setAppValue('twitter', $key, $value);
        }
        $response = new DataResponse(1);
        return $response;
    }

    /**
     * perform 1st step of 3-legged twitter oauth
     * @NoAdminRequired
     */
    public function doOauthStep1() {
        $consumerKey = $this->config->getAppValue('twitter', 'consumer_key', '');
        $consumerSecret = $this->config->getAppValue('twitter', 'consumer_secret', '');

        $twitteroauth = new TwitterOAuth($consumerKey, $consumerSecret);
        // request token of application
        try {
            $request_token = $twitteroauth->oauth(
                'oauth/request_token', [
                    'oauth_callback' => 'web+nextcloudtwitter://auth-redirect'
                ]
            );
        } catch (TwitterOAuthException $e) {
            return new DataResponse($e->getMessage());
        }
        // save token of application to session
        $oauthToken = $request_token['oauth_token'];
        $oauthTokenSecret = $request_token['oauth_token_secret'];
        $this->config->setUserValue($this->userId, 'twitter', 'tmp_oauth_token', $oauthToken);
        $this->config->setUserValue($this->userId, 'twitter', 'tmp_oauth_token_secret', $oauthTokenSecret);

        // return the URL where user will be redirected to authenticate on twitter
        $url = $twitteroauth->url(
            'oauth/authorize', [
                'oauth_token' => $request_token['oauth_token']
            ]
        );

        return new DataResponse($url);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function oauthRedirect($url) {
        $parts = parse_url($url);
        parse_str($parts['query'], $params);
        $oauth_verifier = $params['oauth_verifier'];

        $consumerKey = $this->config->getAppValue('twitter', 'consumer_key', '');
        $consumerSecret = $this->config->getAppValue('twitter', 'consumer_secret', '');

        $oauthToken = $this->config->getUserValue($this->userId, 'twitter', 'tmp_oauth_token', '');
        $oauthTokenSecret = $this->config->getUserValue($this->userId, 'twitter', 'tmp_oauth_token_secret', '');

        if (empty($oauth_verifier) || $oauthToken === '' || $oauthTokenSecret === '') {
            $result = $this->l->t('Problem in OAuth first or second step');
            return new RedirectResponse(
                $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
                '?twitterToken=error&message=' . urlencode($result)
            );
        }

        $connection = new TwitterOAuth(
            $consumerKey,
            $consumerSecret,
            $oauthToken,
            $oauthTokenSecret
        );

        // request user token
        try {
            $token = $connection->oauth(
                'oauth/access_token', [
                    'oauth_verifier' => $oauth_verifier
                ]
            );
        } catch (TwitterOAuthException $e) {
            $result = $this->l->t('Problem in OAuth third step.');
            $result .= ' ' . $e->getMessage();
            return new RedirectResponse(
                $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
                '?twitterToken=error&message=' . urlencode($result)
            );
        }
        $oauthToken = $token['oauth_token'];
        $oauthTokenSecret = $token['oauth_token_secret'];
        $this->config->setUserValue($this->userId, 'twitter', 'oauth_token', $oauthToken);
        $this->config->setUserValue($this->userId, 'twitter', 'oauth_token_secret', $oauthTokenSecret);
        return new RedirectResponse(
            $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
            '?twitterToken=success'
        );
    }
}
