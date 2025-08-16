<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserDrugSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        DB::table('user_drugs')->insert([
            [
                'user_id'    => 1,
                'rxcui'      => '1191',
                'name'       => 'Aspirin 325 MG Oral Tablet',
                'base_names' => json_encode(['Aspirin']),
                'dose_forms' => json_encode(['Oral Tablet']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id'    => 1,
                'rxcui'      => '857005',
                'name'       => 'Ibuprofen 200 MG Oral Tablet',
                'base_names' => json_encode(['Ibuprofen']),
                'dose_forms' => json_encode(['Oral Tablet']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id'    => 2,
                'rxcui'      => '5640',
                'name'       => 'Acetaminophen 500 MG Oral Tablet',
                'base_names' => json_encode(['Acetaminophen']),
                'dose_forms' => json_encode(['Oral Tablet']),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
