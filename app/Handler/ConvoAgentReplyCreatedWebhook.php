<?php

namespace App\Handler;

use App\Models\Ticket;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ConvoAgentReplyCreatedWebhook extends SpatieProcessWebhookJob
{
    public function handle()
    {
        $res = json_decode($this->webhookCall, true)['payload'];
        $id_conversation = $res['id'];
        $message = $res['threads'][0]['body'];
        $ticket = Ticket::where('id_helpscout_convo', '=', $id_conversation)->first();
        if ($ticket) {
            $id_addon = $ticket->id_addon_thread;
            $this->updateAddonThread($message, $id_addon);
        }
        return response('success', '200');
    }

    public function updateAddonThread($message, $id_addon)
    {
        $api_key = env('ADDON_API_KEY');

        try {
            $url = "https://api.addons.prestashop.com/request/seller/threads/$id_addon/messages/add?api_key=$api_key";
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'form_params' => [
                    'id_thread' => $id_addon,
                    'message' => $message,
                ]
            ]);
        } catch (\Exception $exception) {
            echo 'Caught exception: ', $exception->getMessage();
        }
    }
}
