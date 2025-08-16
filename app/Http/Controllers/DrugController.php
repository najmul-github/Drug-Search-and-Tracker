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

    public function search(SearchDrugRequest $request)
    {
        try {
            $concepts = $this->drugService->searchTopConcepts($request->drug_name, 5);

            // Augment each with ingredients + dose forms
            $enriched = array_map(function ($c) {
                $extras = $this->drugService->fetchIngredientsAndDoseForms($c['rxcui']);
                return $extras;
                return [
                    'rxcui' => $c['rxcui'],
                    'name' => $c['name'],
                    'baseNames' => $extras['baseNames'] ?? [],
                    'doseForms' => $extras['doseForms'] ?? [],
                ];
            }, $concepts);

            return $this->success([
                'count' => count($enriched),
                'items' => DrugSearchResource::collection(collect($enriched)),
            ], 'Drug search completed successfully');
        } catch (Throwable $e) {
            Log::error('Drug search failed', ['e'=>$e->getMessage()]);
            return $this->fail('Unable to search drugs right now', 502);
        }
    }
}
