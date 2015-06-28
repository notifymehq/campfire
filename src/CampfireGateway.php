<?php

/*
 * This file is part of NotifyMe.
 *
 * (c) Alt Three LTD <support@alt-three.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NotifyMeHQ\Campfire;

use GuzzleHttp\Client;
use NotifyMeHQ\Contracts\GatewayInterface;
use NotifyMeHQ\NotifyMe\Arr;
use NotifyMeHQ\NotifyMe\HttpGatewayTrait;
use NotifyMeHQ\NotifyMe\Response;

class CampfireGateway implements GatewayInterface
{
    use HttpGatewayTrait;

    /**
     * Gateway api endpoint.
     *
     * @var string
     */
    protected $endpoint = 'https://{domain}.campfirenow.com';

    /**
     * Campfire allowed message types.
     *
     * @var string[]
     */
    protected $allowedTypeMessages = [
        'TextMessage',
        'PasteMessage',
        'TweetMessage',
        'SoundMessage',
    ];

    /**
     * Campfire allowed sound types.
     *
     * @var string[]
     */
    protected $allowedSounds = [
        '56k',
        'bueller',
        'crickets',
        'dangerzone',
        'deeper',
        'drama',
        'greatjob',
        'horn',
        'horror',
        'inconceivable',
        'live',
        'loggins',
        'noooo',
        'nyan',
        'ohmy',
        'ohyeah',
        'pushit',
        'rimshot',
        'sax',
        'secret',
        'tada',
        'tmyk',
        'trombone',
        'vuvuzela',
        'yeah',
        'yodel',
    ];

    /**
     * Create a new campfire gateway instance.
     *
     * @param \GuzzleHttp\Client $client
     * @param string[]           $config
     *
     * @return void
     */
    public function __construct(Client $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Send a notification.
     *
     * @param string $to
     * @param string $message
     *
     * @return \NotifyMeHQ\Contracts\ResponseInterface
     */
    public function notify($to, $message)
    {
        $type = Arr::get($this->config, 'type', 'TextMessage');

        if (!in_array($type, $this->allowedTypeMessages)) {
            $type = 'TextMessage';
        }

        $params = [
            'to'   => $to,
            'type' => $type,
        ];

        if ($type === 'SoundMessage') {
            $params['body'] = in_array($message, $this->allowedSounds) ? $message : 'horn';
        } else {
            $params['body'] = $message;
        }

        return $this->commit($params);
    }

    /**
     * Commit a HTTP request.
     *
     * @param string[] $params
     *
     * @return mixed
     */
    protected function commit(array $params)
    {
        $success = false;

        $rawResponse = $this->client->post($this->buildUrlFromString("room/{$params['to']}/speak.json"), [
            'exceptions'      => false,
            'timeout'         => '80',
            'connect_timeout' => '30',
            'headers'         => [
                'Authorization' => 'Basic '.base64_encode($this->config['token'].':x'),
                'Content-Type'  => 'application/json',
            ],
            'json' => ['message' => $params],
        ]);

        $response = [];

        switch ($rawResponse->getStatusCode()) {
            case 201:
                $success = true;
                break;
            case 400:
                $response['error'] = 'Incorrect request values.';
                break;
            case 404:
                $response['error'] = 'Invalid room.';
                break;
            default:
                $response['error'] = $this->responseError($rawResponse);
        }

        return $this->mapResponse($success, $response);
    }

    /**
     * Map HTTP response to response object.
     *
     * @param bool  $success
     * @param array $response
     *
     * @return \NotifyMeHQ\Contracts\ResponseInterface
     */
    protected function mapResponse($success, $response)
    {
        return (new Response())->setRaw($response)->map([
            'success' => $success,
            'message' => $success ? 'Message sent' : $response['error'],
        ]);
    }

    /**
     * Get the default json response.
     *
     * @param \GuzzleHttp\Message\ResponseInterface $rawResponse
     *
     * @return array
     */
    protected function jsonError($rawResponse)
    {
        $msg = 'API Response not valid.';
        $msg .= " (Raw response API {$rawResponse->getBody()})";

        return [
            'error' => $msg,
        ];
    }

    /**
     * Get the request url.
     *
     * @return string
     */
    protected function getRequestUrl()
    {
        return str_replace('{domain}', $this->config['from'], $this->endpoint);
    }
}
