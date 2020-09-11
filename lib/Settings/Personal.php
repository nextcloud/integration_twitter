<?php
namespace OCA\Twitter\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IURLGenerator;
use OCP\IInitialStateService;

use OCA\Twitter\AppInfo\Application;

require_once __DIR__ . '/../constants.php';

class Personal implements ISettings {

    private $request;
    private $config;
    private $dataDirPath;
    private $urlGenerator;
    private $l;

    public function __construct(
                        string $appName,
                        IL10N $l,
                        IRequest $request,
                        IConfig $config,
                        IURLGenerator $urlGenerator,
                        IInitialStateService $initialStateService,
                        $userId) {
        $this->appName = $appName;
        $this->urlGenerator = $urlGenerator;
        $this->request = $request;
        $this->l = $l;
        $this->config = $config;
        $this->initialStateService = $initialStateService;
        $this->userId = $userId;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm() {
        $token = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_token', '');
        $tokenSecret = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_token_secret', '');

        $consumerKey = $this->config->getAppValue(Application::APP_ID, 'consumer_key', DEFAULT_TWITTER_CONSUMER_KEY);
        $consumerSecret = $this->config->getAppValue(Application::APP_ID, 'consumer_secret', DEFAULT_TWITTER_CONSUMER_SECRET);
        $consumerKey = $consumerKey ? $consumerKey : DEFAULT_TWITTER_CONSUMER_KEY;
        $consumerSecret = $consumerSecret ? $consumerSecret : DEFAULT_TWITTER_CONSUMER_SECRET;

        $hasConsumerKey = ($consumerKey !== '');
        $hasConsumerSecret = ($consumerSecret !== '');

        $name = $this->config->getUserValue($this->userId, Application::APP_ID, 'name', '');
        $screenName = $this->config->getUserValue($this->userId, Application::APP_ID, 'screen_name', '');

        $userConfig = [
            'oauth_token' => $token,
            'oauth_token_secret' => $tokenSecret,
            'consumer_key' => $hasConsumerKey,
            'consumer_secret' => $hasConsumerSecret,
            'name' => $name,
            'screen_name' => $screenName,
        ];
        $this->initialStateService->provideInitialState($this->appName, 'user-config', $userConfig);
        $response = new TemplateResponse(Application::APP_ID, 'personalSettings');
        return $response;
    }

    public function getSection() {
        return 'connected-accounts';
    }

    public function getPriority() {
        return 10;
    }
}
