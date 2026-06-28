<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteAdmin extends Command
{
    protected $signature = 'admin:promote {email : The email of the user to promote}';

    protected $description = 'Promote an existing user to super-admin (role = superadmin).';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No existe ningún usuario con el email {$email}.");

            return self::FAILURE;
        }

        if ($user->role === 'superadmin') {
            $this->info("{$email} ya es super-admin. Nada que hacer.");

            return self::SUCCESS;
        }

        // `role` is intentionally not mass-assignable (privilege escalation),
        // so it is set explicitly here. EnsureSuperAdmin / isSuperAdmin() check
        // role === 'superadmin'.
        $user->forceFill(['role' => 'superadmin'])->save();

        $this->info("✅ {$email} ahora es super-admin.");

        return self::SUCCESS;
    }
}
