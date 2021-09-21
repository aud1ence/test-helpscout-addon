<?php

namespace App\Handler;

use App\Models\Ticket;
use HelpScout\Api\Conversations\Conversation;
use HelpScout\Api\Conversations\Threads\CustomerThread;
use HelpScout\Api\Customers\Customer;
use HelpScout\Api\Entity\Collection;
use HelpScout\Api\Exception\RateLimitExceededException;
use HelpScout\Api\Exception\ValidationErrorException;
use Illuminate\Support\Facades\Http;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;
use HelpScout\Api\ApiClientFactory;
use Carbon\Carbon;

class ConvoCreatedWebhook extends ProcessWebhookJob
{
    public function handle()
    {
        $client = ApiClientFactory::createClient();
        $client->useClientCredentials(env('HS_APP_ID'), env('HS_APP_SECRET'));

        $data = json_decode($this->webhookCall, true)['payload'];
        $subject = $data['subject'];
        $email = $data['customer']['email'];

        $wordEn = env('ADDON_MESSAGE_SUBJECT');

        $id_addon_thread = $this->getIdThread($subject);

        if (str_contains($subject, $wordEn) && $email === env('ADDON_MESSAGE_EMAIL')) {
            $id_convo = $data['id'];
            $client->conversations()->updateStatus($id_convo, 'closed');

            $api_key = env('ADDON_API_KEY');
            $message = $this->getMessageFromAddon($id_addon_thread, $api_key);
            $topic = $this->getTopicName($id_addon_thread, $api_key);

            $fake_email = env('HELPSCOUT_FAKE_EMAIL_USER') . "$id_addon_thread" . env('HELPSCOUT_FAKE_EMAIL_DOMAIN');

            $ticket = Ticket::where('id_addon_thread', '=', $id_addon_thread)->first();

            $customer = new Customer();
            $customer->addEmail($fake_email);
            $thread = new CustomerThread();
            $thread->setCustomer($customer);
            $thread->setText($message);

            if (!$ticket) {
                $conversation = new Conversation();
                $conversation->setSubject($topic);
                $conversation->setStatus('active');
                $conversation->setType('email');
                $conversation->setMailboxId(env('HELPSCOUT_MAILBOX_DEFAULT'));
                $conversation->setCustomer($customer);
                $conversation->setThreads(new Collection([
                    $thread,
                ]));
                try {
                    $conversationId = $client->conversations()->create($conversation);
                } catch (ValidationErrorException $e) {
                    var_dump($e->getError()->getErrors());
                }
                $this->saveToDatabase($id_addon_thread, $conversationId);
            } else {
                $client->threads()->create($ticket->id_helpscout_convo, $thread);
            }
        }
        return response('success', '200');
    }

    public function getMessageFromAddon($id_addon_thread, $api_key)
    {
        $url = "https://api.addons.prestashop.com/request/seller/threads/$id_addon_thread/messages?api_key=$api_key";
        $response = Http::get($url);
        $response_data = json_decode($response->body(), true);
        return $response_data['messages']['data'][0]['message'];
    }

    public function getTopicName($id_addon_thread, $api_key)
    {
        $topic = '';
        $getTopic = "https://api.addons.prestashop.com/request/seller/threads?api_key=$api_key";
        $res = Http::get($getTopic);
        $response_topic = json_decode($res->body(), true)['threads']['data'];
        foreach ($response_topic as $value) {
            if ($id_addon_thread == $value['id_community_thread']) {
                $topic = $value['topic'];
            }
        }
        return $topic;
    }

    public function saveToDatabase($id_addon, $id_convo)
    {
        $newTicket = new Ticket();
        $newTicket->id_addon_thread = $id_addon;
        $newTicket->id_helpscout_convo = $id_convo;
        $newTicket->create_time_at = Carbon::now();
        $newTicket->save();
    }

    public function getIdThread($subject)
    {
        $id_addon_thread = '';
        preg_match_all('!\d+!', $subject, $matches);
        if (!empty($matches[0])) {
            $id_addon_thread = (int)$matches[0][0];
        }
        return $id_addon_thread;
    }
}
