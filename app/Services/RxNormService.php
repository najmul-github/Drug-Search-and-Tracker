<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RxNormService
{
    protected string $baseUrl;
    protected int $timeoutMs;
    protected int $cacheSeconds;

    public function __construct()
    {
        // Load configuration or use defaults
        $cfg = config('services.rxnav');
        $this->baseUrl = rtrim($cfg['base_url'] ?? 'https://rxnav.nlm.nih.gov/REST', '/');
        $this->timeoutMs = (int)($cfg['timeout_ms'] ?? 8000);
        $this->cacheSeconds = (int)($cfg['cache_seconds'] ?? 3600);
    }

    /**
     * Search for drugs and return top N concepts by preferred TTY list.
     * @return array<int, array{rxcui:string,name:string}>
     */
    public function searchTopConcepts(string $drugName, int $limit = 5, array $preferredTty = ['SBD','BPCK','SCD']): array
    {
        $key = 'rxnav:search:'.mb_strtolower(trim($drugName));

        return Cache::remember($key, $this->cacheSeconds, function () use ($drugName, $limit, $preferredTty) {
            try {
                // Call the RxNorm search endpoint
                $resp = $this->client()->get($this->baseUrl.'/drugs.json', [
                    'name' => $drugName,
                ])->json();

                // Get the concept groups from the response
                $groups = $resp['drugGroup']['conceptGroup'] ?? [];
                if (empty($groups)) return [];

                $concepts = [];
                // Iterate preferred TTYs to find the first matching group
                foreach ($preferredTty as $tty) {
                    foreach ($groups as $group) {
                        if (($group['tty'] ?? null) === $tty) {
                            $concepts = $group['conceptProperties'] ?? [];
                            break 2; // stop after first match
                        }
                    }
                }

                // Limit results
                $concepts = array_slice($concepts, 0, $limit);

                // Map to simplified array with rxcui and name
                return array_map(function($c) {
                    return [
                        'rxcui' => (string)($c['rxcui'] ?? ''),
                        'name'  => (string)($c['name'] ?? ''),
                    ];
                }, $concepts);

            } catch (Throwable $e) {
                // Log any error and return empty array
                Log::error('RxNav search error', ['e' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Validate RXCUI exists using the ID endpoint.
     * Returns the drug name if valid, null otherwise.
     */
    public function validateRxcui(string $rxcui): ?string
    {
        $key = 'rxnav:valid:'.$rxcui;

        return Cache::remember($key, $this->cacheSeconds, function () use ($rxcui) {
            try {
                $resp = $this->client()->get($this->baseUrl.'/rxcui/'.$rxcui.'.json')->json();
                // Return the name if present
                return $resp['idGroup']['name'] ?? null;
            } catch (Throwable $e) {
                Log::warning('RxNav validate error', ['rxcui'=>$rxcui, 'e'=>$e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Fetch ingredients and dose forms for a given RXCUI.
     * Safely handles missing keys to avoid PHP 8 undefined key errors.
     */
    public function fetchIngredientsAndDoseForms(string $rxcui): array
    {
        $key = 'rxnav:historystatus:' . $rxcui;

        return Cache::remember($key, $this->cacheSeconds, function () use ($rxcui) {
            $baseNames = [];
            $doseForms = [];

            try {
                $response = $this->client()
                    ->get($this->baseUrl . '/rxcui/' . $rxcui . '/historystatus.json')
                    ->json();

                // Check if rxcuiStatusHistory exists
                if (!isset($response['rxcuiStatusHistory'])) {
                    Log::warning('RxNav historystatus missing rxcuiStatusHistory', ['rxcui' => $rxcui]);
                }

                $history = $response['rxcuiStatusHistory'] ?? [];

                // Check if definitionalFeatures exists
                if (!isset($history['definitionalFeatures'])) {
                    Log::warning('RxNav historystatus missing definitionalFeatures', ['rxcui' => $rxcui]);
                }

                $definitionalFeatures = $history['definitionalFeatures'] ?? [];

                // Extract baseNames from ingredientAndStrength
                $ingredients = $definitionalFeatures['ingredientAndStrength'] ?? [];
                if (empty($ingredients)) {
                    Log::info('RxNav historystatus ingredientAndStrength is empty', ['rxcui' => $rxcui]);
                }
                foreach ($ingredients as $ingredient) {
                    if (!empty($ingredient['baseName'])) {
                        $baseNames[] = $ingredient['baseName'];
                    }
                }

                // Extract doseForms from doseFormGroupConcept
                $doseGroups = $definitionalFeatures['doseFormGroupConcept'] ?? [];
                if (empty($doseGroups)) {
                    Log::info('RxNav historystatus doseFormGroupConcept is empty', ['rxcui' => $rxcui]);
                }
                foreach ($doseGroups as $doseGroup) {
                    if (!empty($doseGroup['doseFormGroupName'])) {
                        $doseForms[] = $doseGroup['doseFormGroupName'];
                    }
                }

                // Debug log the parsed data
                Log::debug('Parsed historystatus', [
                    'rxcui' => $rxcui,
                    'baseNames' => $baseNames,
                    'doseForms' => $doseForms,
                ]);

            } catch (Throwable $e) {
                // Log the error including stack trace for debugging
                Log::error('RxNav historystatus exception', [
                    'rxcui' => $rxcui,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // Return unique and filtered arrays
            return [
                'baseNames' => array_values(array_unique(array_filter($baseNames))),
                'doseForms' => array_values(array_unique(array_filter($doseForms))),
            ];
        });
    }


    /**
     * HTTP client wrapper with timeout and retry logic
     */
    protected function client()
    {
        return Http::timeout($this->timeoutMs / 1000)->retry(2, 200);
    }
}
