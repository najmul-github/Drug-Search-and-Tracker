<?php

namespace App\Services;

use App\Models\UserDrug;
use App\Services\RxNormService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserDrugService
{
    protected RxNormService $rx;

    public function __construct(RxNormService $rx)
    {
        $this->rx = $rx; // Inject RxNormService for drug validation and enrichment
    }

    /**
     * Get all drugs associated with a specific user
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserDrugs(int $userId)
    {
        // Fetch drugs by user ID, latest first
        return UserDrug::where('user_id', $userId)->latest()->get();
    }
    
    /**
     * Add a drug for a user
     * - Validates the RXCUI
     * - Fetches ingredients and dose forms from RxNorm
     * - Saves to database if it does not already exist
     *
     * @param $user
     * @param string $rxcui
     * @return array ['drug' => UserDrug|null, 'created' => bool]
     */
    public function addDrug($user, string $rxcui): array
    {
        try {
            // Validate RXCUI and get drug name
            $name = $this->rx->validateRxcui($rxcui);

            if (!$name) {
                Log::info('RXCUI validation failed', [
                    'user_id' => $user->id,
                    'rxcui' => $rxcui,
                ]);
                return ['drug' => null, 'created' => false];
            }

            // Fetch ingredients and dose forms for logging and storage
            $extras = $this->rx->fetchIngredientsAndDoseForms($rxcui);
            Log::debug('Fetched ingredients and dose forms', [
                'user_id' => $user->id,
                'rxcui' => $rxcui,
                'baseNames' => $extras['baseNames'] ?? [],
                'doseForms' => $extras['doseForms'] ?? [],
            ]);

            // Use a database transaction to safely create the drug if it doesn't exist
            $drug = DB::transaction(function () use ($user, $rxcui, $name, $extras) {
                // Find or create new UserDrug entry
                $drug = UserDrug::firstOrNew(
                    ['user_id' => $user->id, 'rxcui' => $rxcui]
                );

                if (!$drug->exists) {
                    // Set drug details and save
                    $drug->name = $name;
                    $drug->base_names = $extras['baseNames'] ?? [];
                    $drug->dose_forms = $extras['doseForms'] ?? [];
                    $drug->save();
                    $created = true;

                    Log::info('Added new drug for user', [
                        'user_id' => $user->id,
                        'rxcui' => $rxcui,
                        'drug_id' => $drug->id,
                    ]);
                } else {
                    // Drug already exists
                    $created = false;
                    Log::info('Drug already exists for user', [
                        'user_id' => $user->id,
                        'rxcui' => $rxcui,
                        'drug_id' => $drug->id,
                    ]);
                }

                return ['drug' => $drug, 'created' => $created];
            });

            return $drug;

        } catch (Throwable $e) {
            Log::error('UserDrugService addDrug failed', [
                'user_id' => $user->id,
                'rxcui' => $rxcui,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['drug' => null, 'created' => false];
        }
    }

    /**
     * Delete a drug for a user
     *
     * @param $user
     * @param string $rxcui
     * @return bool
     */
    public function deleteDrug($user, string $rxcui): bool
    {
        try {
            // Use a transaction to safely delete the drug
            return DB::transaction(function () use ($user, $rxcui) {
                $drug = $user->drugs()->where('rxcui', $rxcui)->first();

                if (!$drug) {
                    Log::info('Drug not found for deletion', [
                        'user_id' => $user->id,
                        'rxcui' => $rxcui,
                    ]);
                    return false;
                }

                // Delete the drug and log success
                $drug->delete();
                Log::info('Deleted drug for user', [
                    'user_id' => $user->id,
                    'rxcui' => $rxcui,
                    'drug_id' => $drug->id,
                ]);

                return true;
            });
        } catch (Throwable $e) {
            Log::warning('UserDrugService deleteDrug failed', [
                'user_id' => $user->id,
                'rxcui' => $rxcui,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
