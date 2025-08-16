<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

class UserDrugControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function actingUser()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    /** @test */
    public function it_requires_auth_for_user_drug_endpoints()
    {
        $this->getJson('/api/user-drugs')->assertStatus(401);
        $this->postJson('/api/user-drugs', ['rxcui' => '1191'])->assertStatus(401);
        $this->deleteJson('/api/user-drugs/1191')->assertStatus(401);
    }

    /** @test */
    public function it_validates_rxcui_when_adding()
    {
        $this->actingUser();

        // RxCUI validation call returns no name -> invalid
        Http::fake([
            'rxnav.nlm.nih.gov/REST/rxcui/*/allProperties.json*' => Http::response([], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/*.json' => Http::response(['idGroup' => []], 200),
        ]);

        $res = $this->postJson('/api/user-drugs', ['rxcui' => '0000']);
        $res->assertStatus(422)->assertJson(['message' => 'Invalid rxcui']);
    }

    /** @test */
    public function it_adds_a_new_drug_for_the_user()
    {
        $this->actingUser();

        Http::fake([
            // validate rxcui -> has name
            'rxnav.nlm.nih.gov/REST/rxcui/1191.json' => Http::response([
                'idGroup' => ['name' => 'aspirin']
            ], 200),

            // fetch baseNames & doseForms (controller uses allProperties here)
            'rxnav.nlm.nih.gov/REST/rxcui/1191/allProperties.json*' => Http::response([
                'propConceptGroup' => [
                    'propConcept' => [
                        ['propName' => 'ingredient', 'propValue' => 'Aspirin'],
                        ['propName' => 'dose form', 'propValue' => 'Tablet'],
                        ['propName' => 'dose form', 'propValue' => 'Capsule'],
                    ],
                ],
            ], 200),
        ]);

        $res = $this->postJson('/api/user-drugs', ['rxcui' => '1191']);
        $res->assertStatus(201)
            ->assertJsonFragment([
                'rxcui' => '1191',
                'name' => 'aspirin',
            ]);

        // list show the drug
        $list = $this->getJson('/api/user-drugs');
        $list->assertOk()->assertJsonCount(1);
    }

    /** @test */
    public function it_prevents_duplicate_add()
    {
        $this->actingUser();

        Http::fake([
            'rxnav.nlm.nih.gov/REST/rxcui/1191.json' => Http::response([
                'idGroup' => ['name' => 'aspirin']
            ], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/1191/allProperties.json*' => Http::response([
                'propConceptGroup' => [
                    'propConcept' => [
                        ['propName' => 'ingredient', 'propValue' => 'Aspirin'],
                        ['propName' => 'dose form', 'propValue' => 'Tablet'],
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/api/user-drugs', ['rxcui' => '1191'])->assertStatus(201);
        $this->postJson('/api/user-drugs', ['rxcui' => '1191'])->assertStatus(422)
            ->assertJson(['message' => 'Drug already added']);
    }

    /** @test */
    public function it_deletes_a_drug_by_rxcui()
    {
        $this->actingUser();

        Http::fake([
            'rxnav.nlm.nih.gov/REST/rxcui/1191.json' => Http::response([
                'idGroup' => ['name' => 'aspirin']
            ], 200),
            'rxnav.nlm.nih.gov/REST/rxcui/1191/allProperties.json*' => Http::response([
                'propConceptGroup' => [
                    'propConcept' => [
                        ['propName' => 'ingredient', 'propValue' => 'Aspirin'],
                        ['propName' => 'dose form', 'propValue' => 'Tablet'],
                    ],
                ],
            ], 200),
        ]);

        $this->postJson('/api/user-drugs', ['rxcui' => '1191'])->assertStatus(201);

        $del = $this->deleteJson('/api/user-drugs/1191');
        $del->assertOk()->assertJson(['message' => 'Deleted successfully']);

        $this->getJson('/api/user-drugs')->assertOk()->assertJsonCount(0);
    }
}
