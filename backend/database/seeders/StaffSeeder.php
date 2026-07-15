<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

// All seeded staff share the password "password" (UserFactory's default
// Hash::make('password')) — memorable for a frontend developer testing login.
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->superAdmin()->create([
            'name'  => 'Nadia Superadmin',
            'email' => 'super@carlton.demo',
        ]);

        $presets = [
            ['email' => 'reception@carlton.demo',    'name' => 'Rima Reception',    'role' => 'reception'],
            ['email' => 'kitchen@carlton.demo',      'name' => 'Karim Kitchen',     'role' => 'kitchen'],
            ['email' => 'housekeeping@carlton.demo', 'name' => 'Huda Housekeeping', 'role' => 'housekeeping'],
            ['email' => 'concierge@carlton.demo',    'name' => 'Cyrine Concierge',  'role' => 'concierge'],
            ['email' => 'events@carlton.demo',       'name' => 'Elias Events',      'role' => 'events'],
        ];

        foreach ($presets as $preset) {
            $user = User::factory()->staff()->create([
                'name'  => $preset['name'],
                'email' => $preset['email'],
            ]);
            $user->assignRole($preset['role']);
        }

        // A couple of extra plain staff (no role) for pagination + "staff with
        // no permissions" testing (e.g. GET /operations/queue -> 403).
        User::factory()->staff()->create(['name' => 'Yara Newstaff', 'email' => 'newstaff@carlton.demo']);
        User::factory()->staff()->create(['name' => 'Tarek Trainee', 'email' => 'trainee@carlton.demo']);
    }
}
