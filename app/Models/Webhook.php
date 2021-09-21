<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\WebhookClient\Models\WebhookCall;

class Webhook extends WebhookCall
{
    use HasFactory;
    protected $table = "webhook_calls";
}
