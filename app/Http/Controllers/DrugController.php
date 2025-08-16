<?php

namespace App\Http\Controllers;

use App\Http\Requests\Drug\SearchDrugRequest;
use App\Http\Resources\DrugSearchResource;
use App\Services\RxNormService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class DrugController extends Controller
{
    use ApiResponse;

    protected $drugService;

    public function __construct(RxNormService $drugService)
    {
        $this->drugService = $drugService;
    }

    /**
     * Search drugs by name with ingredients and dose forms.
     */
    public function search(SearchDrugRequest $request)
    {
        try {
            // Search top 5 concepts for the given drug name
            $concepts = $this->drugService->searchTopConcepts($request->drug_name, 5);

            // Log the raw concepts returned by RxNorm
            Log::debug('RxNorm search results', [
                'drug_name' => $request->drug_name,
                'concepts_count' => count($concepts),
                'concepts' => $concepts,
            ]);

            // Augment each concept with ingredients and dose forms
            $enriched = array_map(function ($c) {
                Log::debug('Processing concept', ['rxcui' => $c['rxcui'], 'name' => $c['name']]);

                // Fetch ingredients and dose forms for each RXCUI
                $extras = $this->drugService->fetchIngredientsAndDoseForms($c['rxcui']);

                // Log fetched extras for debugging
                Log::debug('Fetched ingredients and dose forms', [
                    'rxcui' => $c['rxcui'],
                    'baseNames' => $extras['baseNames'] ?? [],
                    'doseForms' => $extras['doseForms'] ?? [],
                ]);

                return [
                    'rxcui' => $c['rxcui'],
                    'name' => $c['name'],
                    'baseNames' => $extras['baseNames'] ?? [],
                    'doseForms' => $extras['doseForms'] ?? [],
                ];
            }, $concepts);

            // Return API response with enriched results
            return $this->success([
                'count' => count($enriched),
                'items' => DrugSearchResource::collection(collect($enriched)),
            ], 'Drug search completed successfully');

        } catch (Throwable $e) {
            // Log the exception with full stack trace
            Log::error('Drug search failed', [
                'drug_name' => $request->drug_name,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return a generic API error response
            return $this->fail('Unable to search drugs right now', 502);
        }
    }
}
