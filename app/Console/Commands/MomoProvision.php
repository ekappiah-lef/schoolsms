<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sandbox only: creates the API user and API key that belong to the Collections subscription key
 * (momodeveloper.mtn.com → Profile → Primary key). In production MTN Ghana issues them instead.
 *
 * Run: php artisan momo:provision    then put MOMO_API_USER and MOMO_API_KEY in the .env file.
 */
class MomoProvision extends Command
{
    protected $signature = 'momo:provision {--callback-host=edusuite.lefsignature.com : Host MTN may call back}';

    protected $description = 'Create the MTN MoMo sandbox API user and key for the subscription key in MOMO_COLLECTION_SUBSCRIPTION_KEY';

    public function handle()
    {
        $key = config('momo.subscription_key');
        if (!$key) {
            $this->error('Set MOMO_COLLECTION_SUBSCRIPTION_KEY first.');
            return 1;
        }

        $base = 'https://sandbox.momodeveloper.mtn.com/v1_0/apiuser';
        $user = (string) Str::uuid();

        $res = Http::withHeaders(['X-Reference-Id' => $user, 'Ocp-Apim-Subscription-Key' => $key])->timeout(30)
            ->post($base, ['providerCallbackHost' => $this->option('callback-host')]);
        if ($res->status() !== 201) {
            $this->error('Creating the API user failed: HTTP '.$res->status().' '.$res->body());
            return 1;
        }

        $res = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $key])->timeout(30)->post($base.'/'.$user.'/apikey');
        if ($res->status() !== 201 || !$res->json('apiKey')) {
            $this->error('Creating the API key failed: HTTP '.$res->status().' '.$res->body());
            return 1;
        }

        $this->line('MOMO_API_USER='.$user);
        $this->line('MOMO_API_KEY='.$res->json('apiKey'));

        return 0;
    }
}
