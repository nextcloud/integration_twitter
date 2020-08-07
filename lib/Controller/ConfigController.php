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
    public function doOauthStep1($nonce) {
        $this->config->setUserValue($this->userId, 'twitter', 'nonce', $nonce);

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
        return new DataResponse($request_token);
    }

    /**
     * perform 1st step of 3-legged twitter oauth
     * @NoAdminRequired
     */
    public function doOauthStep1BAD($nonce) {
        $this->config->setUserValue($this->userId, 'twitter', 'nonce', $nonce);

        $url = 'https://api.twitter.com/oauth/request_token';
        $consumerKey = $this->config->getAppValue('twitter', 'consumer_key', '');
        $consumerSecret = $this->config->getAppValue('twitter', 'consumer_secret', '');
        $oauthToken = $this->config->getAppValue('twitter', 'oauth_token', '');
        $oauthTokenSecret = $this->config->getAppValue('twitter', 'oauth_token_secret', '');

        $ts = (new \Datetime())->getTimestamp();
        $params = [
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce' => base64_encode($nonce),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $ts,
            //'oauth_token' => $oauthToken,
            'oauth_version' => '1.0',
            'oauth_callback' => 'web+nextcloudtwitter://',
        ];
        // build Signature Base String
        $paramString = http_build_query($params);
        $baseString = 'POST&' . urlencode($url) . '&' . urlencode($paramString);
        //return new DataResponse($baseString);

        // generate signature
        //$signingKey = urlencode($consumerSecret) . '&' . urlencode($oauthTokenSecret);
        $signingKey = urlencode($consumerSecret) . '&';
        //return new DataResponse($signingKey);
        $signature = urlencode(base64_encode(hash_hmac('sha1', $baseString, $signingKey, true)));
        $b64Signature = base64_encode($signature);
        $params['oauth_signature'] = $signature;
        //return new DataResponse($signature);

        // generate header string
        $authHeader = 'OAuth ';
        foreach ($params as $k => $v) {
            $authHeader .= $k . '="' . urlencode($v) . '", ';
        }
        $authHeader = preg_replace('/, $/', '', $authHeader);
        //return new DataResponse($authHeader);

        //$response = $this->request('https://api.twitter.com/oauth/request_token', $params, 'POST');
        $response = $this->request($url, [], 'POST', $authHeader);
        if (is_string($response)) {
            error_log('RESPOOOOOOO '.$response.'||||||||||');
            return new DataResponse($response);
            //parse_str($response, $)
        } else {
            return new DataResponse($response);
        }
    }

    /**
     * receive oauth code and get oauth access token
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function oauthRedirect($code, $state) {
        $configState = $this->config->getUserValue($this->userId, 'twitter', 'oauth_state', '');
        $clientID = $this->config->getAppValue('twitter', 'client_id', '');
        $clientSecret = $this->config->getAppValue('twitter', 'client_secret', '');

        // anyway, reset state
        $this->config->setUserValue($this->userId, 'twitter', 'oauth_state', '');

        if ($clientID and $clientSecret and $configState !== '' and $configState === $state) {
            $result = $this->requestOAuthAccessToken([
                'client_id' => $clientID,
                'client_secret' => $clientSecret,
                'code' => $code,
                'state' => $state
            ], 'POST');
            if (is_array($result) and isset($result['access_token'])) {
                $accessToken = $result['access_token'];
                $this->config->setUserValue($this->userId, 'twitter', 'token', $accessToken);
                return new RedirectResponse(
                    $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
                    '?twitterToken=success'
                );
            }
            $result = $this->l->t('Error getting OAuth access token');
        } else {
            $result = $this->l->t('Error during OAuth exchanges');
        }
        return new RedirectResponse(
            $this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'linked-accounts']) .
            '?twitterToken=error&message=' . urlencode($result)
        );
    }

    private function request($url, $params = [], $method = 'GET', $authHeader = null) {
        $client = $this->clientService->newClient();
        try {
            $options = [
                'headers' => [
                    'User-Agent' => 'Nextcloud Twitter integration'
                ],
            ];
            if (!is_null($authHeader)) {
                $options['headers']['Authorization'] = $authHeader;
            }

            if (count($params) > 0) {
                if ($method === 'GET') {
                    $paramsContent = http_build_query($params);
                    $url .= '?' . $paramsContent;
                } else {
                    $options['body'] = $params;
                }
            }

            if ($method === 'GET') {
                $response = $client->get($url, $options);
            } else if ($method === 'POST') {
                $response = $client->post($url, $options);
            } else if ($method === 'PUT') {
                $response = $client->put($url, $options);
            } else if ($method === 'DELETE') {
                $response = $client->delete($url, $options);
            }
            $body = $response->getBody();
            $respCode = $response->getStatusCode();

            if ($respCode >= 400) {
                return $this->l->t('Request failed');
            } else {
                return $body;
            }
        } catch (\Exception $e) {
            $this->logger->warning('Twitter request error : '.$e, array('app' => $this->appName));
            $response = $e->getResponse();
            $headers = $response->getHeaders();
            $code = $response->getStatusCode();
            //var_dump($headers);
            //return $code;
            return $headers['www-authenticate'];
        }
    }
}
