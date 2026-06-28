<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate a VAPID key pair for Web Push and print the .env lines.';

    public function handle(): int
    {
        if (! class_exists(VAPID::class)) {
            $this->error('minishlink/web-push no está instalado.');

            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();

        $this->info('Añade estas líneas a tu .env (no las compartas):');
        $this->newLine();
        $this->line('VAPID_SUBJECT=mailto:tu-correo@dominio.com');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
