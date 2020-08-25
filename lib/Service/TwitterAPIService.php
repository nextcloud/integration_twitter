<?php
/**
 * Nextcloud - twitter
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Twitter\Service;

use OCP\IL10N;
use OCP\ILogger;
use OCP\IConfig;
use OCP\Http\Client\IClientService;

use OCA\Twitter\AppInfo\Application;

require_once __DIR__ . '/../constants.php';

class TwitterAPIService {

	private $l10n;
	private $logger;

	/**
	 * Service to make requests to Twitter v3 (JSON) API
	 */
	public function __construct (
		string $appName,
		ILogger $logger,
		IL10N $l10n,
		IConfig $config,
		IClientService $clientService,
		string $userId
	) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->config = $config;
		$this->userId = $userId;
		$this->clientService = $clientService;
		$this->client = $clientService->newClient();
		if (!is_null($userId) and $userId !== '') {
			$this->consumerKey = $this->config->getAppValue(Application::APP_ID, 'consumer_key', DEFAULT_CONSUMER_KEY);
			$this->consumerSecret = $this->config->getAppValue(Application::APP_ID, 'consumer_secret', DEFAULT_CONSUMER_SECRET);
			$this->consumerKey = $this->consumerKey ? $this->consumerKey : DEFAULT_CONSUMER_KEY;
			$this->consumerSecret = $this->consumerSecret ? $this->consumerSecret : DEFAULT_CONSUMER_SECRET;
			$this->oauthToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_token', '');
			$this->oauthTokenSecret = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_token_secret', '');
		}
	}

	public function getAvatar($url) {
		return $this->client->get($url)->getBody();
	}

	public function getNotifications($since = null) {
		$results = [];
		$missingUsers = [];

		// !!! this will probably work when API v2 will be released
		// i found it in the web interface (already using unreleased APIv2)
		//$result = $this->classicRequest('notifications/all.json', [], 'GET', '2');

		//////////////// GET MY CREDENTIALS
		$result = $this->classicRequest('account/verify_credentials.json', [], 'GET');
		$myId = $result['id'];
		$myIdStr = $result['id_str'];

		// my home timeline
		//$result = $this->classicRequest('statuses/home_timeline.json', $params);

		////////////////// MENTIONS
		$params = [
			'count' => 20,
			// 'since_id' =>
		];
		$result = $this->classicRequest('statuses/mentions_timeline.json', $params);
		if (is_array($result)) {
			foreach ($result as $mention) {
				$ts = (new \Datetime($mention['created_at']))->getTimestamp();
				$resMention = [
					'type' => 'mention',
					'id' => $mention['id'],
					'id_str' => $mention['id_str'],
					'timestamp' => $ts,
					'text' => $mention['text'],
					'sender_id' => $mention['user']['id'],
					'sender_name' => $mention['user']['name'],
					'sender_screen_name' => $mention['user']['screen_name'],
					'profile_image_url_https' => $mention['user']['profile_image_url_https'],
				];
				array_push($results, $resMention);
			}
		}

		////////////////// RETWEETS
		$params = [
			'count' => 20,
			// 'since_id' =>
		];
		$result = $this->classicRequest('statuses/retweets_of_me.json', $params);
		if (is_array($result)) {
			foreach ($result as $retweet) {
				$ts = (new \Datetime($retweet['created_at']))->getTimestamp();
				$resRetweet = [
					'type' => 'retweet',
					'id' => $retweet['id'],
					'id_str' => $retweet['id_str'],
					'timestamp' => $ts,
					'text' => $retweet['text'],
					'sender_id' => $retweet['user']['id'],
					'sender_name' => $retweet['user']['name'],
					'sender_screen_name' => $retweet['user']['screen_name'],
					'profile_image_url_https' => $retweet['user']['profile_image_url_https'],
				];
				array_push($results, $resRetweet);
			}
		}

		////////////// FOLLOW REQUESTS
		$result = $this->classicRequest('friendships/incoming.json', $params);
		$nbFollowRequests = 0;
		if (isset($result['ids']) and is_array($result['ids'])) {
			$nbFollowRequests = count($result['ids']);
		}
		array_push($results, [
			'type' => 'follow_request',
			'number' => $nbFollowRequests,
			'timestamp' => (new \Datetime())->getTimestamp()
		]);

		/////////////////// PRIVATE MESSAGES
		$params = [
			'count' => 20,
		];
		$result = $this->classicRequest('direct_messages/events/list.json', $params);
		if (isset($result['events']) and is_array($result['events'])) {
			$msgs = $result['events'];
			foreach ($msgs as $msg) {
				if (isset($msg['type']) and $msg['type'] === 'message_create' and isset($msg['message_create'])) {
					// ignore what I sent
					if ($msg['message_create']['sender_id'] !== $myIdStr) {
						//return [$msg->message_create->sender_id , $myId, $myIdStr, $msg];
						$resMsg = [
							'type' => 'message',
							'id' => $msg['id'],
							'timestamp' => intval($msg['created_timestamp'] / 1000),
							'sender_id' => $msg['message_create']['sender_id'],
							'text' => $msg['message_create']['message_data']['text'],
						];
						array_push($results, $resMsg);
						if (!in_array($msg['message_create']['sender_id'], $missingUsers)) {
							array_push($missingUsers, $msg['message_create']['sender_id']);
						}
					}
				}
			}
		}

		// get missing user info
		$userInfo = [];
		foreach ($missingUsers as $user_id) {
			$params = [
				'user_id' => $user_id,
			];
			$result = $this->classicRequest('users/show.json', $params);
			if (isset($result['name']) and isset($result['screen_name']) and isset($result['profile_image_url_https'])) {
				$userInfo[$user_id] = [
					'sender_name' => $result['name'],
					'sender_screen_name' => $result['screen_name'],
					'profile_image_url_https' => $result['profile_image_url_https'],
				];
			}
		}
		// fill missing info
		foreach ($results as $i => $res) {
			if (in_array($res['type'], ['message', 'follow_request'])) {
				$user_id = $res['sender_id'];
				$results[$i]['sender_name'] = $userInfo[$user_id]['sender_name'];
				$results[$i]['sender_screen_name'] = $userInfo[$user_id]['sender_screen_name'];
				$results[$i]['profile_image_url_https'] = $userInfo[$user_id]['profile_image_url_https'];
			}
		}

		// filter by date
		if (!is_null($since)) {
			$results = array_filter($results, function($elem) use ($since) {
				$elemTs = $elem['timestamp'];
				return $elemTs > $since;
			});
		}

		// sort by date
		$a = usort($results, function($a, $b) {
			$ta = $a['timestamp'];
			$tb = $b['timestamp'];
			return ($ta > $tb) ? -1 : 1;
		});

		return $results;
	}

	/**
	 * manually signed API request
	 * @NoAdminRequired
	 */
	public function classicRequest($endPoint, $params = [], $method = 'GET', $apiVersion = '1.1') {
		$url = 'https://api.twitter.com/' . $apiVersion . '/' . $endPoint;

		$ts = (new \Datetime())->getTimestamp();
		$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
		$nonce = substr(str_shuffle($permitted_chars), 0, 32);
		$headerParams = [
			'oauth_consumer_key' => $this->consumerKey,
			'oauth_nonce' => base64_encode($nonce),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp' => $ts,
			'oauth_token' => $this->oauthToken,
			'oauth_version' => '1.0',
		];

		$combinedParams = array_merge($headerParams, $params);
		// build Signature Base String
		// get sorted keys
		$keys = array_keys($combinedParams);
		sort($keys);
		$paramStringArray = [];
		foreach ($keys as $k) {
			array_push($paramStringArray, urlencode($k) . '=' . urlencode($combinedParams[$k]));
		}
		$paramString = implode('&', $paramStringArray);
		$baseString = $method . '&' . urlencode($url) . '&' . urlencode($paramString);

		// generate signature
		$signingKey = urlencode($this->consumerSecret) . '&' . urlencode($this->oauthTokenSecret);
		$signature = hash_hmac('sha1', $baseString, $signingKey, true);
		$b64Signature = base64_encode($signature);
		$headerParams['oauth_signature'] = $b64Signature;

		// generate header string
		$keys = array_keys($headerParams);
		sort($keys);
		$authHeaderParts = [];
		foreach ($keys as $k) {
			array_push($authHeaderParts, urlencode($k) . '="' . urlencode($headerParams[$k]) . '"');
		}
		$authHeader = 'OAuth ' . implode(', ', $authHeaderParts);

		$response = $this->request($url, $params, $method, $authHeader);
		if (is_array($response)) {
			return $response;
		} else {
			return $response;
		}
	}

	/**
	 * manually signed OAuth step1 request
	 * @NoAdminRequired
	 */
	public function requestTokenOAuthStep1($consumerKey, $consumerSecret) {
		$method = 'POST';
		$url = 'https://api.twitter.com/oauth/request_token';

		$ts = (new \Datetime())->getTimestamp();
		$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
		$nonce = substr(str_shuffle($permitted_chars), 0, 32);
		$headerParams = [
			'oauth_consumer_key' => $consumerKey,
			'oauth_nonce' => base64_encode($nonce),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp' => $ts,
			'oauth_version' => '1.0',
			'oauth_callback' => 'web+nextcloudtwitter://auth-redirect',
		];

		$params = [];
		$combinedParams = array_merge($headerParams, $params);
		// build Signature Base String
		// get sorted keys
		$keys = array_keys($combinedParams);
		sort($keys);
		$paramStringArray = [];
		foreach ($keys as $k) {
			array_push($paramStringArray, urlencode($k) . '=' . urlencode($combinedParams[$k]));
		}
		$paramString = implode('&', $paramStringArray);
		$baseString = $method . '&' . urlencode($url) . '&' . urlencode($paramString);

		// generate signature
		//$signingKey = urlencode($this->consumerSecret) . '&' . urlencode($this->oauthTokenSecret);
		$signingKey = urlencode($consumerSecret) . '&';
		$signature = hash_hmac('sha1', $baseString, $signingKey, true);
		$b64Signature = base64_encode($signature);
		$headerParams['oauth_signature'] = $b64Signature;

		// generate header string
		$keys = array_keys($headerParams);
		sort($keys);
		$authHeaderParts = [];
		foreach ($keys as $k) {
			array_push($authHeaderParts, urlencode($k) . '="' . urlencode($headerParams[$k]) . '"');
		}
		$authHeader = 'OAuth ' . implode(', ', $authHeaderParts);

		$response = $this->request($url, $params, $method, $authHeader, false);
		parse_str($response, $values);
		return $values;
	}

	/**
	 * manually signed OAuth step1 request
	 * @NoAdminRequired
	 */
	public function requestTokenOAuthStep3($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, $oauthVerifier) {
		$method = 'POST';
		$url = 'https://api.twitter.com/oauth/access_token';

		$ts = (new \Datetime())->getTimestamp();
		$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyz';
		$nonce = substr(str_shuffle($permitted_chars), 0, 32);
		$headerParams = [
			'oauth_consumer_key' => $consumerKey,
			'oauth_nonce' => base64_encode($nonce),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp' => $ts,
			'oauth_token' => $oauthToken,
			'oauth_verifier' => $oauthVerifier,
			'oauth_version' => '1.0',
			'oauth_callback' => 'web+nextcloudtwitter://auth-redirect',
		];

		$params = [];
		$combinedParams = array_merge($headerParams, $params);
		// build Signature Base String
		// get sorted keys
		$keys = array_keys($combinedParams);
		sort($keys);
		$paramStringArray = [];
		foreach ($keys as $k) {
			array_push($paramStringArray, urlencode($k) . '=' . urlencode($combinedParams[$k]));
		}
		$paramString = implode('&', $paramStringArray);
		$baseString = $method . '&' . urlencode($url) . '&' . urlencode($paramString);

		// generate signature
		$signingKey = urlencode($consumerSecret) . '&' . urlencode($oauthTokenSecret);
		$signature = hash_hmac('sha1', $baseString, $signingKey, true);
		$b64Signature = base64_encode($signature);
		$headerParams['oauth_signature'] = $b64Signature;

		// generate header string
		$keys = array_keys($headerParams);
		sort($keys);
		$authHeaderParts = [];
		foreach ($keys as $k) {
			array_push($authHeaderParts, urlencode($k) . '="' . urlencode($headerParams[$k]) . '"');
		}
		$authHeader = 'OAuth ' . implode(', ', $authHeaderParts);

		$response = $this->request($url, $params, $method, $authHeader, false);
		parse_str($response, $values);
		return $values;
	}

	private function request($url, $params = [], $method = 'GET', $authHeader = null, $json = true) {
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
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return $this->l10n->t('Request failed');
			} else {
				if ($json) {
					return json_decode($body, true);
				} else {
					return $body;
				}
			}
		} catch (\Exception $e) {
			$this->logger->warning('Twitter request error : '.$e, array('app' => $this->appName));
			return $e->getMessage();
		}
	}
}
