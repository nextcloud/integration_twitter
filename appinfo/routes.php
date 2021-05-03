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

return [
    'routes' => [
        ['name' => 'config#doOauthStep1', 'url' => '/oauth-step1', 'verb' => 'GET'],
        ['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
        ['name' => 'config#getUsername', 'url' => '/username', 'verb' => 'GET'],
        ['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
        ['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
        ['name' => 'twitterAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
        ['name' => 'twitterAPI#getHomeTimeline', 'url' => '/home', 'verb' => 'GET'],
        ['name' => 'twitterAPI#getUserTimeline', 'url' => '/user', 'verb' => 'GET'],
        ['name' => 'twitterAPI#getAvatar', 'url' => '/avatar', 'verb' => 'GET'],
    ]
];
