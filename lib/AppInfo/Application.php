<?php
/**
 * Nextcloud - Twitter
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Twitter\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Twitter\Dashboard\TwitterNotificationsWidget;
use OCA\Twitter\Dashboard\TwitterHomeWidget;
use OCA\Twitter\Dashboard\TwitterUserFollowWidget;

/**
 * Class Application
 *
 * @package OCA\Twitter\AppInfo
 */
class Application extends App implements IBootstrap {

    public const APP_ID = 'integration_twitter';
	public const DEFAULT_TWITTER_CONSUMER_KEY = 'Q0t3zN0kgbB4isnBfmwL223S3';
	public const DEFAULT_TWITTER_CONSUMER_SECRET = '9sxvwHDegbojv5OffdSspnu0Z2OmMEaR7viCFovT14Jj95LCQZ';

    /**
     * Constructor
     *
     * @param array $urlParams
     */
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerDashboardWidget(TwitterNotificationsWidget::class);
        $context->registerDashboardWidget(TwitterHomeWidget::class);
        $context->registerDashboardWidget(TwitterUserFollowWidget::class);
    }

    public function boot(IBootContext $context): void {
    }
}

