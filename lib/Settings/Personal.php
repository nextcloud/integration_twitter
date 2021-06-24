<?php
namespace OCA\Twitter\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\Twitter\AppInfo\Application;

class Personal implements ISettings {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(IConfig $config,
								IInitialState $initialStateService,
								?string $userId) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

    /**
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse {
        $token = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_token');
        $tokenSecret = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_token_secret');

        $consumerKey = $this->config->getAppValue(Application::APP_ID, 'consumer_key', Application::DEFAULT_TWITTER_CONSUMER_KEY);
        $consumerSecret = $this->config->getAppValue(Application::APP_ID, 'consumer_secret', Application::DEFAULT_TWITTER_CONSUMER_SECRET);
        $consumerKey = $consumerKey ?: Application::DEFAULT_TWITTER_CONSUMER_KEY;
        $consumerSecret = $consumerSecret ?: Application::DEFAULT_TWITTER_CONSUMER_SECRET;

        $hasConsumerKey = ($consumerKey !== '');
        $hasConsumerSecret = ($consumerSecret !== '');

        $name = $this->config->getUserValue($this->userId, Application::APP_ID, 'name');
        $screenName = $this->config->getUserValue($this->userId, Application::APP_ID, 'screen_name');

        $userToFollow = $this->config->getUserValue($this->userId, Application::APP_ID, 'followed_user');
		$userToFollowAdmin = $this->config->getAppValue(Application::APP_ID, 'followed_user');

        $userConfig = [
            'oauth_token' => $token,
            'oauth_token_secret' => $tokenSecret,
            'consumer_key' => $hasConsumerKey,
            'consumer_secret' => $hasConsumerSecret,
            'name' => $name,
            'screen_name' => $screenName,
            'followed_user' => $userToFollow,
            'followed_user_admin' => $userToFollowAdmin,
        ];
        $this->initialStateService->provideInitialState('user-config', $userConfig);
        return new TemplateResponse(Application::APP_ID, 'personalSettings');
    }

    public function getSection(): string {
        return 'connected-accounts';
    }

    public function getPriority(): int {
        return 10;
    }
}
