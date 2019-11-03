<?php

use Illuminate\Database\Seeder;

class logsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        log::truncate();
        $faker = \Faker\Factory::create();
        log::create([
            'name' => 'a',
            'email' => 'a@a.a',
            'password' =>'aaaaaa',

        ]);
        for ($i = 0; $i < 50; $i++) {
            log::create([
                'name' => $faker->name,
                'email' => $faker->email,
                'password' => $faker->password,

            ]);
        }  
        //
    }
}
