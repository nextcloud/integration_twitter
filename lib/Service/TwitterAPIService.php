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

require_once __DIR__ . '/../../vendor/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;
use Abraham\TwitterOAuth\TwitterOAuthException;

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
    }

    public function getAvatar($url) {
        return $this->client->get($url)->getBody();
    }

    public function getNotifications($since = null) {
        $consumerKey = $this->config->getAppValue('twitter', 'consumer_key', '');
        $consumerSecret = $this->config->getAppValue('twitter', 'consumer_secret', '');
        $oauthToken = $this->config->getUserValue($this->userId, 'twitter', 'oauth_token', '');
        $oauthTokenSecret = $this->config->getUserValue($this->userId, 'twitter', 'oauth_token_secret', '');

        $results = [];
        $missingUsers = [];

        // my home timeline
        //$result = $this->request($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'statuses/home_timeline', $params);

        ////////////////// MENTIONS
        $params = [
            'count' => 20,
            // 'since_id' =>
        ];
        $result = $this->request($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'statuses/mentions_timeline', $params);
        if (is_array($result)) {
            foreach ($result as $mention) {
                $ts = (new \Datetime($mention->created_at))->getTimestamp();
                $resMention = [
                    'type' => 'mention',
                    'id' => $mention->id,
                    'id_str' => $mention->id_str,
                    'timestamp' => $ts,
                    'text' => $mention->text,
                    'sender_id' => $mention->user->id,
                    'sender_name' => $mention->user->name,
                    'sender_screen_name' => $mention->user->screen_name,
                    'profile_image_url_https' => $mention->user->profile_image_url_https,
                ];
                array_push($results, $resMention);
            }
        }

        ////////////////// RETWEETS
        $params = [
            'count' => 20,
            // 'since_id' =>
        ];
        $result = $this->request($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'statuses/retweets_of_me', $params);
        if (is_array($result)) {
            foreach ($result as $retweet) {
                $ts = (new \Datetime($retweet->created_at))->getTimestamp();
                $resRetweet = [
                    'type' => 'retweet',
                    'id' => $retweet->id,
                    'id_str' => $retweet->id_str,
                    'timestamp' => $ts,
                    'text' => $retweet->text,
                    'sender_id' => $retweet->user->id,
                    'sender_name' => $retweet->user->name,
                    'sender_screen_name' => $retweet->user->screen_name,
                    'profile_image_url_https' => $retweet->user->profile_image_url_https,
                ];
                array_push($results, $resRetweet);
            }
        }

        //////////////// FRIENDSHIP REQUESTS
        //$result = $this->request($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'friendships/incoming', $params);
        //if (isset($result->ids) and is_array($result->ids)) {
        //    foreach ($result->ids as $user_id) {
        //        array_push($results, [
        //            'type' => 'follow_request',
        //            'sender_id' => $user_id,
        //        ]);
        //        if (!in_array($user_id, $missingUsers)) {
        //            array_push($missingUsers, $user_id);
        //        }
        //    }
        //}

        /////////////////// PRIVATE MESSAGES
        $params = [
            'count' => 20,
        ];
        $result = $this->request($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'direct_messages/events/list', $params);
        if (isset($result->events) and is_array($result->events)) {
            $msgs = $result->events;
            foreach ($msgs as $msg) {
                if (isset($msg->type) and $msg->type === 'message_create' and isset($msg->message_create)) {
                    $resMsg = [
                        'type' => 'message',
                        'id' => $msg->id,
                        'timestamp' => intval($msg->created_timestamp / 1000),
                        'sender_id' => $msg->message_create->sender_id,
                        'text' => $msg->message_create->message_data->text,
                    ];
                    array_push($results, $resMsg);
                    if (!in_array($msg->message_create->sender_id, $missingUsers)) {
                        array_push($missingUsers, $msg->message_create->sender_id);
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
            $result = $this->request($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, 'users/show', $params);
            if (isset($result->name) and isset($result->screen_name) and isset($result->profile_image_url_https)) {
                $userInfo[$user_id] = [
                    'sender_name' => $result->name,
                    'sender_screen_name' => $result->screen_name,
                    'profile_image_url_https' => $result->profile_image_url_https,
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

        return $results;
    }

    public function request($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret, $endPoint, $params = [], $method = 'GET') {
        try {
            $twitter = new TwitterOAuth(
                $consumerKey,
                $consumerSecret,
                $oauthToken,
                $oauthTokenSecret
            );

            if ($method === 'GET') {
                $result = $twitter->get($endPoint, $params);
            } elseif ($method === 'POST') {
                $result = $twitter->post($endPoint, $params);
            } elseif ($method === 'DELETE') {
                $result = $twitter->delete($endPoint, $params);
            } elseif ($method === 'PUT') {
                $result = $twitter->put($endPoint, $params);
            }
            return $result;
        } catch (\Exception $e) {
            $this->logger->warning('Twitter API error : '.$e, array('app' => $this->appName));
            return $e->getMessage();
        }
    }

}
