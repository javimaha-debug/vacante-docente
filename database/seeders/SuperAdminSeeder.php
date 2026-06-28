<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    /**
     * Grant the super-admin role to the platform owner. The user is matched by
     * email; if they have not registered yet this is a no-op (the Google OAuth
     * callback also promotes this email on first login).
     */
    public function run(): void
    {
        $user = User::where('email', 'j.madrid@loggex.es')->first();

        if ($user) {
            $user->forceFill(['role' => 'superadmin'])->save();
        }
    }
}
