<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DrugService
{
    protected $apiUrl;

    public function __construct()
    {
        $this->apiUrl = config('services.rxnorm.base_url');
    }

    public function searchDrugs(string $drugName): array
    {
        try {
            $cacheKey = 'rxnorm_search_' . strtolower(trim($drugName));

            return Cache::remember($cacheKey, 3600, function () use ($drugName) {
                $response = Http::get("{$this->apiUrl}/drugs.json", [
                    'name' => $drugName
                ])->json();

                if (empty($response['drugGroup']['conceptGroup'])) {
                    return [];
                }

                $preferredTty = ['SBD', 'BPCK', 'SCD'];
                $concepts = [];

                foreach ($preferredTty as $tty) {
                    foreach ($response['drugGroup']['conceptGroup'] as $group) {
                        if (($group['tty'] ?? null) === $tty) {
                            $concepts = $group['conceptProperties'] ?? [];
                            break 2;
                        }
                    }
                }

                return array_slice($concepts, 0, 5);
            });
        } catch (\Throwable $e) {
            Log::error('Drug search failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
