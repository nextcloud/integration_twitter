<?php
namespace OCA\Twitter\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\Twitter\AppInfo\Application;

class Admin implements ISettings {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;

	public function __construct(IConfig $config,
								IInitialState $initialStateService) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
	}

    /**
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse {
        $consumerKey = $this->config->getAppValue(Application::APP_ID, 'consumer_key');
        $consumerSecret = $this->config->getAppValue(Application::APP_ID, 'consumer_secret');
		$userToFollow = $this->config->getAppValue(Application::APP_ID, 'followed_user');

        $adminConfig = [
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
            'followed_user' => $userToFollow,
        ];
        $this->initialStateService->provideInitialState('admin-config', $adminConfig);
        return new TemplateResponse(Application::APP_ID, 'adminSettings');
    }

    public function getSection(): string {
        return 'connected-accounts';
    }

    public function getPriority(): int {
        return 10;
    }
}
