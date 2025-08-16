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
        $this->rx = $rx;
    }

    /**
     * Get all drug based on user
     */
    public function getUserDrugs(int $userId)
    {
        return UserDrug::where('user_id', $userId)->latest()->get();
    }
    
    /**
     * Add a drug for a user
     */
    public function addDrug($user, string $rxcui): array
    {
        try {
            $name = $this->rx->validateRxcui($rxcui);
            if (!$name) return ['drug' => null, 'created' => false];

            $extras = $this->rx->fetchIngredientsAndDoseForms($rxcui);

            $drug = DB::transaction(function () use ($user, $rxcui, $name, $extras) {
                $drug = UserDrug::firstOrNew(
                    ['user_id' => $user->id, 'rxcui' => $rxcui]
                );

                if (!$drug->exists) {
                    $drug->name = $name;
                    $drug->base_names = $extras['baseNames'] ?? [];
                    $drug->dose_forms = $extras['doseForms'] ?? [];
                    $drug->save();
                    $created = true;
                } else {
                    $created = false;
                }

                return ['drug' => $drug, 'created' => $created];
            });

            return $drug;

        } catch (Throwable $e) {
            Log::error('UserDrugService addDrug failed', [
                'user_id' => $user->id,
                'rxcui' => $rxcui,
                'error' => $e->getMessage(),
            ]);
            return ['drug' => null, 'created' => false];
        }
    }


    /**
     * Delete a drug for a user
     */
    public function deleteDrug($user, string $rxcui): bool
    {
        try {
            return DB::transaction(function () use ($user, $rxcui) {
                $drug = $user->drugs()->where('rxcui', $rxcui)->first();
                if (!$drug) return false;
                $drug->delete();
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
