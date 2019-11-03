<?php

use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CalenderTableSeeder extends Seeder
{
    public function run()
    {
        // factory(App\Calendar::class, 10)->create()->each(function ($calendar) {
        //     $calendar->posts()->save(factory(App\Post::class)->make());
        // });

        foreach(range(1, 31) as $index) {
            $date = Carbon::create(2019, 10, $index, 0, 0, 0);
            DB::table('calendars')->insert([
                'theDate' => $date,
                'open' => true,
                'events' => '',
            ]);
        }
    }
}
