<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class DrugSearchTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_requires_drug_name()
    {
        $this->getJson('/api/search-drugs')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['drug_name']);
    }

    /** @test */
    public function it_returns_top_5_results_with_baseNames_and_doseForms_from_history_status()
    {
        Http::fake([
            'rxnav.nlm.nih.gov/REST/drugs.json*' => Http::response([
                'drugGroup' => [
                    'conceptGroup' => [
                        [
                            'tty' => 'SBD',
                            'conceptProperties' => [
                                ['rxcui' => '111', 'name' => 'Alpha 10 MG Tablet'],
                                ['rxcui' => '222', 'name' => 'Beta 20 MG Tablet'],
                            ],
                        ],
                        [
                            'tty' => 'SCD',
                            'conceptProperties' => [
                                ['rxcui' => '333', 'name' => 'Gamma 5 MG Capsule'],
                            ],
                        ],
                    ],
                ],
            ], 200),

            'rxnav.nlm.nih.gov/REST/rxcui/*/historystatus.json' => Http::response([
                'rxcuiStatusHistory' => [
                    'ingredientAndStrength' => [
                        ['baseName' => 'Aspirin'],
                        ['baseName' => 'Caffeine'],
                    ],
                    'doseFormGroupConcept' => [
                        ['doseFormGroupName' => 'Oral Tablet'],
                        ['doseFormGroupName' => 'Extended Release Tablet'],
                    ],
                ],
            ], 200),
        ]);

        $res = $this->getJson('/api/search-drugs?drug_name=aspirin');

        $res->assertOk()
            ->assertJsonStructure([
                '*' => ['rxcui','name','baseNames','doseForms']
            ]);

        $data = $res->json();
        $this->assertCount(2, $data); // faked only 2 SBD results
        $this->assertEquals(['Aspirin','Caffeine'], $data[0]['baseNames']);
        $this->assertEquals(['Oral Tablet','Extended Release Tablet'], $data[0]['doseForms']);
    }
}
