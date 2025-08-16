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
        $this->service = $service; // Inject UserDrugService for user drug operations
    }

    /**
     * Get all drugs for the authenticated user
     */
    public function getUserDrugs()
    {
        try {
            // Fetch all drugs for the logged-in user
            $drugs = $this->service->getUserDrugs(auth()->user()->id);

            // Return success response with a collection of UserDrugResource
            return $this->success(
                UserDrugResource::collection($drugs),
                'User drugs retrieved successfully'
            );
        } catch (Throwable $e) {
            // Log any exception while retrieving drugs
            Log::error('Failed to get user drugs', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return API failure response
            return $this->fail('Unable to retrieve drugs', 500);
        }
    }

    /**
     * Add a drug for the authenticated user using RxNormService
     */
    public function addDrug(AddUserDrugRequest $request)
    {
        try {
            // Add the drug via UserDrugService
            $result = $this->service->addDrug(auth()->user(), (string)$request->rxcui);

            $drug = $result['drug'];      // The UserDrug model
            $created = $result['created']; // Whether a new record was created

            // If RXCUI was invalid or drug could not be added
            if (!$drug) {
                Log::warning('Unable to add drug', [
                    'user_id' => auth()->id(),
                    'rxcui' => $request->rxcui,
                ]);
                return $this->fail('Unable to add drug', 500);
            }

            // Customize message and HTTP status based on creation
            $message = $created ? 'Drug added successfully' : 'Drug was already added';
            $status = $created ? 201 : 200;

            // Return API success response with the added drug
            return $this->success(
                new UserDrugResource($drug),
                $message,
                $status
            );

        } catch (Throwable $e) {
            // Log exception details
            Log::error('Failed to add user drug', [
                'user_id' => auth()->id(),
                'rxcui' => $request->rxcui,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->fail('Unable to add drug', 500);
        }
    }
    
    /**
     * Delete a drug for the authenticated user
     */
    public function deleteDrug(string $rxcui)
    {
        try {
            // Attempt to delete the drug via UserDrugService
            $deleted = $this->service->deleteDrug(auth()->user(), $rxcui);

            // If drug was not found
            if (!$deleted) {
                Log::info('Drug not found for deletion', [
                    'user_id' => auth()->id(),
                    'rxcui' => $rxcui,
                ]);
                return $this->fail('Drug not found', 404);
            }

            // Return success response
            return $this->success(null, 'Drug deleted successfully');

        } catch (Throwable $e) {
            // Log exception during deletion
            Log::warning('Failed to delete user drug', [
                'user_id' => auth()->id(),
                'rxcui' => $rxcui,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->fail('Unable to delete drug', 500);
        }
    }
}
