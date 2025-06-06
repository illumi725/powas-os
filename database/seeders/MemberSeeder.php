<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserInfo;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\Hash;

class MemberSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        for ($i = 1; $i <= 5; $i++) {
            $user_id = random_int(10000, 99999);
            $lastname = strtolower($faker->lastName());
            $firstname = strtolower($faker->firstName());
            User::create([
                'user_id' => $user_id,
                'username' => $lastname . ", " . $firstname,
                'email' => $faker->safeEmail(),
                'password' => Hash::make('powas-os'),
                'account_status' => 'ACTIVE',
            ])->assignRole('member');

            UserInfo::create([
                'user_id' => $user_id,
                'lastname' => strtoupper($lastname),
                'firstname' => strtoupper($firstname),
                'middlename' => strtoupper($lastname),
                'birthday' => $faker->date(),
                'address1' => strtoupper($faker->streetName()),
                'region' => strtoupper($faker->country()),
                'province' => strtoupper($faker->state()),
                'municipality' => strtoupper($faker->city()),
                'barangay' => strtoupper($faker->streetName()),
            ]);
        }
    }
}
