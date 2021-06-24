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

use Datetime;
use Exception;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;

class TwitterAPIService {
	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var \OCP\Http\Client\IClient
	 */
	private $client;

	/**
	 * Service to make requests to Twitter v3 (JSON) API
	 */
	public function __construct (string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IClientService $clientService) {
		$this->appName = $appName;
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->client = $clientService->newClient();
	}

	/**
	 * Actually download the avatar (not authenticated)
	 *
	 * @param string $consumerKey
	 * @param string $consumerSecret
	 * @param string $oauthToken
	 * @param string $oauthTokenSecret
	 * @param string $twitterUserId
	 * @return ?string the avatar image content
	 */
	public function getAvatar(string $consumerKey, string $consumerSecret, string $oauthToken, string $oauthTokenSecret, string $twitterUserId): ?string {
		$params = [
			'user_id' => $twitterUserId,
		];
		$userInfo = $this->classicRequest($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'users/show.json', $params);
		if (!isset($userInfo['error']) && isset($userInfo['profile_image_url_https'])) {
			return $this->client->get($userInfo['profile_image_url_https'])->getBody();
		}
		return null;
	}

	/**
	 * Get tweets of the home timeline
	 *
	 * @param string $consumerKey
	 * @param string $consumerSecret
	 * @param string $oauthToken
	 * @param string $oauthTokenSecret
	 * @param ?int $since min ID
	 * @return array the tweets
	 */
	public function getHomeTimeline(string $consumerKey, string $consumerSecret, string $oauthToken, string $oauthTokenSecret, ?int $since = null): array {
		// my home timeline
		$params = [
			'count' => 20,
		];
		if (!is_null($since)) {
			$params['since_id'] = $since;
		}
		return $this->classicRequest($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'statuses/home_timeline.json', $params);
	}

	/**
	 * Get tweets of the home timeline
	 *
	 * @param string $consumerKey
	 * @param string $consumerSecret
	 * @param string $oauthToken
	 * @param string $oauthTokenSecret
	 * @param string $twitterUserId
	 * @param ?int $since min ID
	 * @return array the tweets
	 */
	public function getUserTimeline(string $consumerKey, string $consumerSecret, string $oauthToken, string $oauthTokenSecret,
									string $twitterUserId, ?int $since = null): array {
		// my home timeline
		$params = [
			'count' => 20,
			'screen_name' => $twitterUserId,
		];
		if (!is_null($since)) {
			$params['since_id'] = $since;
		}
		return $this->classicRequest($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'statuses/user_timeline.json', $params);
	}

	/**
	 * Get multiple kind of notifications
	 *
	 * @param string $consumerKey
	 * @param string $consumerSecret
	 * @param string $oauthToken
	 * @param string $oauthTokenSecret
	 * @param ?int $since limit timestamp
	 * @return array the notifications, follow requests, mentions...
	 */
	public function getNotifications(string $consumerKey, string $consumerSecret, string $oauthToken, string $oauthTokenSecret, ?int $since = null): array {
		$results = [];
		$missingUsers = [];

		// !!! this will probably work when API v2 will be released
		// i found it in the web interface (already using unreleased APIv2)
		//$result = $this->classicRequest('notifications/all.json', [], 'GET', '2');

		//////////////// GET MY CREDENTIALS
		$result = $this->classicRequest(
			$consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret,
			'account/verify_credentials.json', [], 'GET'
		);
		if (isset($result['error'])) {
			return $result;
		}
//		$myId = $result['id'];
		$myIdStr = $result['id_str'];

		////////////////// MENTIONS
		$params = [
			'count' => 20,
			// 'since_id' =>
		];
		$result = $this->classicRequest($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'statuses/mentions_timeline.json', $params);
		if (!isset($result['error'])) {
			foreach ($result as $mention) {
				$ts = (new Datetime($mention['created_at']))->getTimestamp();
				$resMention = [
					'type' => 'mention',
					'id' => $mention['id'],
					'id_str' => $mention['id_str'],
					'timestamp' => $ts,
					'text' => $mention['text'],
					'sender_id' => $mention['user']['id'],
					'sender_id_str' => $mention['user']['id_str'] ?? '',
					'sender_name' => $mention['user']['name'],
					'sender_screen_name' => $mention['user']['screen_name'],
					'profile_image_url_https' => $mention['user']['profile_image_url_https'],
				];
				$results[] = $resMention;
			}
		} else {
			return $result;
		}

		////////////////// RETWEETS
		$params = [
			'count' => 20,
			// 'since_id' =>
		];
		$result = $this->classicRequest($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'statuses/retweets_of_me.json', $params);
		if (!isset($result['error'])) {
			foreach ($result as $retweet) {
				$ts = (new Datetime($retweet['created_at']))->getTimestamp();
				$resRetweet = [
					'type' => 'retweet',
					'id' => $retweet['id'],
					'id_str' => $retweet['id_str'],
					'timestamp' => $ts,
					'text' => $retweet['text'],
					'sender_id' => $retweet['user']['id'],
					'sender_id_str' => $retweet['user']['id_str'] ?? '',
					'sender_name' => $retweet['user']['name'],
					'sender_screen_name' => $retweet['user']['screen_name'],
					'profile_image_url_https' => $retweet['user']['profile_image_url_https'],
				];
				$results[] = $resRetweet;
			}
		} else {
			return $result;
		}

		////////////// FOLLOW REQUESTS
		$result = $this->classicRequest($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'friendships/incoming.json', $params);
		if (isset($result['error'])) {
			return $result;
		}
		$nbFollowRequests = 0;
		if (isset($result['ids']) && is_array($result['ids'])) {
			$nbFollowRequests = count($result['ids']);
		}
		$results[] = [
			'type' => 'follow_request',
			'number' => $nbFollowRequests,
			'timestamp' => (new Datetime())->getTimestamp()
		];

		/////////////////// PRIVATE MESSAGES
		$params = [
			'count' => 20,
		];
		$result = $this->classicRequest($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'direct_messages/events/list.json', $params);
		if (isset($result['error'])) {
			return $result;
		}
		if (isset($result['events']) && is_array($result['events'])) {
			$msgs = $result['events'];
			foreach ($msgs as $msg) {
				if (isset($msg['type']) && $msg['type'] === 'message_create' && isset($msg['message_create'])) {
					// ignore if no sender ID and ignore what I sent
					if (isset($msg['message_create']['sender_id']) && $msg['message_create']['sender_id'] !== $myIdStr) {
						$resMsg = [
							'type' => 'message',
							'id' => $msg['id'],
							'timestamp' => intval($msg['created_timestamp'] / 1000),
							'sender_id' => $msg['message_create']['sender_id'],
							'text' => $msg['message_create']['message_data']['text'],
						];
						$results[] = $resMsg;
						if (!in_array($msg['message_create']['sender_id'], $missingUsers)) {
							$missingUsers[] = $msg['message_create']['sender_id'];
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
			$result = $this->classicRequest($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'users/show.json', $params);
			if (isset($result['error'])) {
				return $result;
			}
			$userInfo[$user_id] = [
				'sender_id_str' => $result['id_str'] ?? '',
				'sender_name' => $result['name'] ?? 'unknown',
				'sender_screen_name' => $result['screen_name'] ?? 'unknown',
				'profile_image_url_https' => $result['profile_image_url_https'] ?? '',
			];
		}
		// fill missing info
		foreach ($results as $i => $res) {
			if (in_array($res['type'], ['message'])) {
				if (isset($res['sender_id'])) {
					$sender_id = $res['sender_id'];
					$results[$i]['sender_id_str'] = $userInfo[$sender_id]['sender_id_str'];
					$results[$i]['sender_name'] = $userInfo[$sender_id]['sender_name'];
					$results[$i]['sender_screen_name'] = $userInfo[$sender_id]['sender_screen_name'];
					$results[$i]['profile_image_url_https'] = $userInfo[$sender_id]['profile_image_url_https'];
				} else {
					$results[$i]['sender_id_str'] = '';
					$results[$i]['sender_name'] = 'unknown';
					$results[$i]['sender_screen_name'] = 'unknown';
					$results[$i]['profile_image_url_https'] = '';
				}
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
		usort($results, function($a, $b) {
			$ta = $a['timestamp'];
			$tb = $b['timestamp'];
			return ($ta > $tb) ? -1 : 1;
		});

		return $results;
	}

	/**
	 * manually signed API request
	 *
	 * @param string $consumerKey
	 * @param string $consumerSecret
	 * @param string $oauthToken
	 * @param string $oauthTokenSecret
	 * @param string $endPoint suffix of the requested URL
	 * @param array $params request parameters
	 * @param string $method HTTP request method
	 * @param string $apiVersion
	 * @return array json decoded request result or error
	 */
	public function classicRequest(string $consumerKey, string $consumerSecret, string $oauthToken, string $oauthTokenSecret,
									string $endPoint, array $params = [], string $method = 'GET', string $apiVersion = '1.1'): array {
		$url = 'https://api.twitter.com/' . $apiVersion . '/' . $endPoint;

		$ts = (new Datetime())->getTimestamp();
		$headerParams = [
			'oauth_consumer_key' => $consumerKey,
			'oauth_nonce' => $this->makeNonce(),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp' => $ts,
			'oauth_token' => $oauthToken,
			'oauth_version' => '1.0',
		];

		$combinedParams = array_merge($headerParams, $params);
		// build Signature Base String
		// get sorted keys
		$keys = array_keys($combinedParams);
		sort($keys);
		$paramStringArray = [];
		foreach ($keys as $k) {
			$paramStringArray[] = urlencode($k) . '=' . urlencode($combinedParams[$k]);
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
			$authHeaderParts[] = urlencode($k) . '="' . urlencode($headerParams[$k]) . '"';
		}
		$authHeader = 'OAuth ' . implode(', ', $authHeaderParts);

		$result = $this->request($url, $params, $method, $authHeader);
		if (isset($result['error'])) {
			return $result;
		}
		$decoded = json_decode($result['body'], true);
		if (is_array($decoded)) {
			return $decoded;
		} else {
			return ['error' => $this->l10n->t('Impossible to decode response')];
		}
	}

	/**
	 * manually signed OAuth step1 request
	 *
	 * @param string $consumerKey
	 * @param string $consumerSecret
	 * @return array result body or request error
	 */
	public function requestTokenOAuthStep1(string $consumerKey, string $consumerSecret): array {
		$method = 'POST';
		$url = 'https://api.twitter.com/oauth/request_token';

		$ts = (new Datetime())->getTimestamp();
		$headerParams = [
			'oauth_consumer_key' => $consumerKey,
			'oauth_nonce' => $this->makeNonce(),
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
			$paramStringArray[] = urlencode($k) . '=' . urlencode($combinedParams[$k]);
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
			$authHeaderParts[] = urlencode($k) . '="' . urlencode($headerParams[$k]) . '"';
		}
		$authHeader = 'OAuth ' . implode(', ', $authHeaderParts);

		$result = $this->request($url, $params, $method, $authHeader);
		if (isset($result['error'])) {
			return $result;
		}
		parse_str($result['body'], $values);
		return count($values) > 0 ? $values : ['error' => $this->l10n->t('Invalid return value in OAuth step 1')];
	}

	/**
	 * manually signed OAuth step3 request
	 *
	 * @param string $consumerKey
	 * @param string $consumerSecret
	 * @param string $oauthToken
	 * @param string $oauthTokenSecret
	 * @param string $oauthVerifier
	 * @return array result body or error
	 */
	public function requestTokenOAuthStep3(string $consumerKey, string $consumerSecret, string $oauthToken,
											string $oauthTokenSecret, string $oauthVerifier): array {
		$method = 'POST';
		$url = 'https://api.twitter.com/oauth/access_token';

		$ts = (new Datetime())->getTimestamp();
		$headerParams = [
			'oauth_consumer_key' => $consumerKey,
			'oauth_nonce' => $this->makeNonce(),
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
			$paramStringArray[] = urlencode($k) . '=' . urlencode($combinedParams[$k]);
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
			$authHeaderParts[] = urlencode($k) . '="' . urlencode($headerParams[$k]) . '"';
		}
		$authHeader = 'OAuth ' . implode(', ', $authHeaderParts);

		$result = $this->request($url, $params, $method, $authHeader);
		if (isset($result['error'])) {
			return $result;
		}
		parse_str($result['body'], $values);
		return count($values) > 0 ? $values : ['error' => $this->l10n->t('Invalid return value in OAuth step 1')];
	}

	/**
	 * make an HTTP request
	 *
	 * @param string $url
	 * @param array $params
	 * @param string $method
	 * @param ?string $authHeader
	 * @return array response body or error
	 */
	private function request(string $url, array $params = [], string $method = 'GET', ?string $authHeader = null): array {
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
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Request failed')];
			} else {
				return ['body' => $body];
			}
		} catch (Exception $e) {
			$this->logger->warning('Twitter request error : '.$e->getMessage(), array('app' => $this->appName));
			return ['error' => $e->getMessage()];
		}
	}

	private function makeNonce(): string {
		$len = 32;
		$buf = '';
		$permittedChars = '0123456789abcdefghijklmnopqrstuvwxyz';
		$maxBound = strlen($permittedChars) - 1;

		while ($len-- > 0) {
			$choice = random_int(0, $maxBound);
			$buf .= $permittedChars[$choice];
		}

		return $buf;
	}
}
