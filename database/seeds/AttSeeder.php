<?php

use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AttSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    private function insertInto($id, $date){
        DB::table('attendances')->insert([
            'user_id' => $id,
            'verify_mode' => 1,
            'io_mode' => 1,
            'io_time' => $date,
        ]);
    }

    public function run()
    {
        foreach(range(1, 31) as $index) {
            $date = Carbon::create(2019, 10, $index, 10, 0, 0);
            foreach(range(0,1) as $dontNeedThis){
                $this->insertInto(1,$date);
                $this->insertInto(2,$date);
                $date = Carbon::create(2019, 10, $index, 18, 0, 0);
            }
        }
    }
}
