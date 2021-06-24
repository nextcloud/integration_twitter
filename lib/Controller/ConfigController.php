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

use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Twitter\Service\TwitterAPIService;
use OCA\Twitter\AppInfo\Application;

class ConfigController extends Controller {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var TwitterAPIService
	 */
	private $twitterAPIService;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IURLGenerator $urlGenerator,
								IL10N $l,
								TwitterAPIService $twitterAPIService,
								?string $userId) {
        parent::__construct($appName, $request);
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->l = $l;
		$this->twitterAPIService = $twitterAPIService;
		$this->userId = $userId;
	}

    /**
     * @NoAdminRequired
     *
     * @return DataResponse
     */
    public function getUsername(): DataResponse {
        $username = $this->config->getUserValue($this->userId, Application::APP_ID, 'screen_name');
        return new DataResponse($username);
    }

    /**
     * set config values
     * @NoAdminRequired
     *
     * @param array key/val pairs of config values
     * @return DataResponse useless result
     */
    public function setConfig(array $values): DataResponse {
        foreach ($values as $key => $value) {
            $this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
        }
        return new DataResponse(1);
    }

    /**
     * set admin config values
     *
     * @param array key/val pairs of config values
     * @return DataResponse useless result
     */
    public function setAdminConfig(array $values): DataResponse {
        foreach ($values as $key => $value) {
            $this->config->setAppValue(Application::APP_ID, $key, $value);
        }
        return new DataResponse(1);
    }

    /**
     * perform 1st step of 3-legged twitter oauth
     * @NoAdminRequired
     *
     * @return DataResponse the URL to the next OAuth step
     */
    public function doOauthStep1(): DataResponse {
        $consumerKey = $this->config->getAppValue(Application::APP_ID, 'consumer_key', Application::DEFAULT_TWITTER_CONSUMER_KEY);
        $consumerSecret = $this->config->getAppValue(Application::APP_ID, 'consumer_secret', Application::DEFAULT_TWITTER_CONSUMER_SECRET);
        $consumerKey = $consumerKey ?: Application::DEFAULT_TWITTER_CONSUMER_KEY;
        $consumerSecret = $consumerSecret ?: Application::DEFAULT_TWITTER_CONSUMER_SECRET;

        $requestToken = $this->twitterAPIService->requestTokenOAuthStep1($consumerKey, $consumerSecret);
        if (!isset($requestToken['oauth_token'], $requestToken['oauth_token_secret'])) {
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
     *
     * @param ?string $url the url parameter contained in the custom protocol redirection request
     * @return RedirectResponse redirecting to the connected account settings
     */
    public function oauthRedirect(?string $url = ''): RedirectResponse {
        if ($url === '') {
            $message = $this->l->t('Problem in OAuth third step.');
            return new RedirectResponse(
                $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
                '?twitterToken=error&message=' . urlencode($message)
            );
        }
        $parts = parse_url($url);
        parse_str($parts['query'], $params);
        $oauthVerifier = $params['oauth_verifier'];

        $consumerKey = $this->config->getAppValue(Application::APP_ID, 'consumer_key', Application::DEFAULT_TWITTER_CONSUMER_KEY);
        $consumerSecret = $this->config->getAppValue(Application::APP_ID, 'consumer_secret', Application::DEFAULT_TWITTER_CONSUMER_SECRET);
        $consumerKey = $consumerKey === '' ? Application::DEFAULT_TWITTER_CONSUMER_KEY : $consumerKey;
        $consumerSecret = $consumerSecret === '' ? Application::DEFAULT_TWITTER_CONSUMER_SECRET : $consumerSecret;

        $oauthToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'tmp_oauth_token');
        $oauthTokenSecret = $this->config->getUserValue($this->userId, Application::APP_ID, 'tmp_oauth_token_secret');

        if (empty($oauthVerifier) || $oauthToken === '' || $oauthTokenSecret === '') {
            $result = $this->l->t('Problem in OAuth first or second step');
            return new RedirectResponse(
                $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
                '?twitterToken=error&message=' . urlencode($result)
            );
        }

        $token = $this->twitterAPIService->requestTokenOAuthStep3($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, $oauthVerifier);
        if (!isset($token['oauth_token'], $token['oauth_token_secret'])) {
            $result = $this->l->t('Problem in OAuth third step.');
//            $result .= ' ' . $e->getMessage();
            return new RedirectResponse(
                $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
                '?twitterToken=error&message=' . urlencode($result)
            );
        }
        $oauthToken = $token['oauth_token'];
        $oauthTokenSecret = $token['oauth_token_secret'];
        $this->config->setUserValue($this->userId, Application::APP_ID, 'oauth_token', $oauthToken);
        $this->config->setUserValue($this->userId, Application::APP_ID, 'oauth_token_secret', $oauthTokenSecret);
        // get my username
        $creds = $this->twitterAPIService->classicRequest(
            $consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret,
            'account/verify_credentials.json', [], 'GET'
        );
        if (isset($creds['name'], $creds['screen_name'])) {
            $this->config->setUserValue($this->userId, Application::APP_ID, 'name', $creds['name']);
            $this->config->setUserValue($this->userId, Application::APP_ID, 'screen_name', $creds['screen_name']);
        }
        return new RedirectResponse(
            $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
            '?twitterToken=success#twitter_prefs'
        );
    }
}
