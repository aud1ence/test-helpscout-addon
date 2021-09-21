<?php

namespace App\Http\Controllers;

use Exception;
use GuzzleHttp\Exception\ClientException;
use HelpScout\Api\Http\Authenticator;
use HelpScout\Api\ApiClientFactory;
use HelpScout\Api\Webhooks\Webhook;

class WebhookController extends Controller
{
    public function __construct()
    {
        $this->appId = env('HS_APP_ID');
        $this->appSecret = env('HS_APP_SECRET');
        $this->url = env('HS_URL');
        $this->webhookSecret = env('WEBHOOK_CLIENT_SECRET');

        try {
            $this->client = ApiClientFactory::createClient([], function (Authenticator $authenticator) {
                return $authenticator->accessToken();
            });
            $this->client->useClientCredentials($this->appId, $this->appSecret);
            $this->listWebHook();
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() == '401') {
                $this->client->getAuthenticator()->fetchAccessAndRefreshToken();
            }
        }
    }

    public function listWebHook()
    {
        $webhooks = $this->client->webhooks()
            ->list()
            ->toArray();

        if (empty($webhooks)) {
            $this->createWebHook('convo.created');
            $this->createWebHook('convo.agent.reply.created');
        } else {
            $events = [];
            foreach ($webhooks as $hook) {
                array_push($events, $hook->getEvents()[0]);
                if ($hook->getState() == 'disabled') {
                    $this->client->webhooks()->delete($hook->getId());
                }
            }
            if (!in_array('convo.created', $events)) {
                $this->createWebHook('convo.created');
            }
            if (!in_array('convo.agent.reply.created', $events)) {
                $this->createWebHook('convo.agent.reply.created');
            }
        }
    }

    public function createWebHook($event)
    {
        $subUri = str_replace('.', '-', $event);
        $request = new Webhook();
        $request->hydrate([
            'url' => "$this->url/$subUri",
            'events' => [$event],
            'secret' => $this->webhookSecret
        ]);

        try {
            $this->client->webhooks()->create($request);
        } catch (Exception $exception) {
            echo $exception->getMessage();
            echo $exception->getCode();
        }
    }
}
