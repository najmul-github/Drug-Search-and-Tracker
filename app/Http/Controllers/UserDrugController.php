<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserDrug\AddUserDrugRequest;
use App\Http\Resources\UserDrugResource;
use App\Services\UserDrugService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserDrugController extends Controller
{
    use ApiResponse;

    protected UserDrugService $service;

    public function __construct(UserDrugService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all drugs for authenticated user
     */
    public function getUserDrugs()
    {
        try {
            $drugs = $this->service->getUserDrugs(auth()->user()->id);

            return $this->success(UserDrugResource::collection($drugs), 'User drugs retrieved successfully');
        } catch (Throwable $e) {
            Log::error('Failed to get user drugs', ['error' => $e->getMessage()]);
            return $this->fail('Unable to retrieve drugs', 500);
        }
    }

    /**
     * Add a drug using RxNormService
     */
    public function addDrug(AddUserDrugRequest $request)
    {
        try {
            $result = $this->service->addDrug(auth()->user(), (string)$request->rxcui);
            
            $drug = $result['drug'];
            $created = $result['created'];

            if (!$drug) return $this->fail('Unable to add drug', 500);

            $message = $created ? 'Drug added successfully' : 'Drug was already added';
            $status = $created ? 201 : 200;
            
            return $this->success(new UserDrugResource($drug), $message, $status);

        } catch (Throwable $e) {
            Log::error('Failed to add user drug', ['rxcui' => $request->rxcui, 'error' => $e->getMessage()]);
            return $this->fail('Unable to add drug', 500);
        }
    }
    
    /**
     * Delete a drug using RxNormService
     */
    public function deleteDrug(string $rxcui)
    {
        try {
            $deleted = $this->service->deleteDrug(auth()->user(), $rxcui);

            if (!$deleted) return $this->fail('Drug not found', 404);

            return $this->success(null, 'Drug deleted successfully');

        } catch (Throwable $e) {
            Log::warning('Failed to delete user drug', ['rxcui' => $rxcui, 'error' => $e->getMessage()]);
            return $this->fail('Unable to delete drug', 500);
        }
    }
}