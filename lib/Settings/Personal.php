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
        $token = $this->config->getUserValue($this->userId, 'twitter', 'oauth_token', '');
        $tokenSecret = $this->config->getUserValue($this->userId, 'twitter', 'oauth_token_secret', '');

        $consumerKey = $this->config->getAppValue('twitter', 'consumer_key', '') !== '';
        $consumerSecret = $this->config->getAppValue('twitter', 'consumer_secret', '') !== '';

        $userConfig = [
            'oauth_token' => $token,
            'oauth_token_secret' => $tokenSecret,
            'consumer_key' => $consumerKey,
            'consumer_secret' => $consumerSecret,
        ];
        $this->initialStateService->provideInitialState($this->appName, 'user-config', $userConfig);
        $response = new TemplateResponse('twitter', 'personalSettings');
        return $response;
    }

    public function getSection() {
        return 'linked-accounts';
    }

    public function getPriority() {
        return 10;
    }
}
