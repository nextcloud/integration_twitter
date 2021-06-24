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

use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Twitter\Service\TwitterAPIService;
use OCA\Twitter\AppInfo\Application;

class TwitterAPIController extends Controller {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var TwitterAPIService
	 */
	private $twitterAPIService;
	/**
	 * @var string|null
	 */
	private $userId;
	/**
	 * @var string
	 */
	private $consumerKey;
	/**
	 * @var string
	 */
	private $consumerSecret;
	/**
	 * @var string
	 */
	private $oauthToken;
	/**
	 * @var string
	 */
	private $oauthTokenSecret;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								TwitterAPIService $twitterAPIService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->twitterAPIService = $twitterAPIService;
		$this->userId = $userId;
		$this->consumerKey = $this->config->getAppValue(Application::APP_ID, 'consumer_key', Application::DEFAULT_TWITTER_CONSUMER_KEY);
		$this->consumerSecret = $this->config->getAppValue(Application::APP_ID, 'consumer_secret', Application::DEFAULT_TWITTER_CONSUMER_SECRET);
		$this->consumerKey = $this->consumerKey ?: Application::DEFAULT_TWITTER_CONSUMER_KEY;
		$this->consumerSecret = $this->consumerSecret ?: Application::DEFAULT_TWITTER_CONSUMER_SECRET;
		$this->oauthToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_token');
		$this->oauthTokenSecret = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_token_secret');
	}

	/**
	 * get notification list
	 * @NoAdminRequired
	 *
	 * @param ?int $since limit timestamp
	 * @return DataResponse notifications or error
	 */
	public function getNotifications(?int $since = null): DataResponse {
		if ($this->oauthToken === '') {
			return new DataResponse([], 400);
		}
		$result = $this->twitterAPIService->getNotifications($this->consumerKey, $this->consumerSecret, $this->oauthToken, $this->oauthTokenSecret, $since);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

	/**
	 * get home timeline
	 * @NoAdminRequired
	 *
	 * @param ?int $since min ID
	 * @return DataResponse the timeline items or an error
	 */
	public function getHomeTimeline(?int $since = null): DataResponse {
		if ($this->oauthToken === '') {
			return new DataResponse([], 400);
		}
		$result = $this->twitterAPIService->getHomeTimeline(
			$this->consumerKey, $this->consumerSecret, $this->oauthToken, $this->oauthTokenSecret, $since
		);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

	/**
	 * get timeline of a given user
	 * @NoAdminRequired
	 *
	 * @param ?int $since min ID
	 * @return DataResponse the timeline items or an error
	 */
	public function getUserTimeline(?int $since = null): DataResponse {
		if ($this->oauthToken === '') {
			return new DataResponse(['error' => 'Not connected'], 400);
		}
		$userToFollow = $this->config->getAppValue(Application::APP_ID, 'followed_user');
		if ($userToFollow === '') {
			$userToFollow = $this->config->getUserValue($this->userId, Application::APP_ID, 'followed_user');
		}
		if ($userToFollow === '') {
			return new DataResponse(['error' => 'No user to follow'], 418);
		}
		$result = $this->twitterAPIService->getUserTimeline(
			$this->consumerKey, $this->consumerSecret, $this->oauthToken, $this->oauthTokenSecret, $userToFollow, $since
		);
		if (!isset($result['error'])) {
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
	 *
	 * @param string $userId
	 * @return DataDisplayResponse the avatar image data
	 */
	public function getAvatar(string $userId): DataDisplayResponse {
		$avatar = $this->twitterAPIService->getAvatar(
			$this->consumerKey, $this->consumerSecret, $this->oauthToken, $this->oauthTokenSecret, $userId
		);
		if (is_null($avatar)) {
			return new DataDisplayResponse('', 400);
		} else {
			$response = new DataDisplayResponse($avatar);
			$response->cacheFor(60*60*24);
			return $response;
		}
	}
}
