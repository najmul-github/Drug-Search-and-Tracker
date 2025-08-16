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
        $cfg = config('services.rxnav');
        $this->baseUrl = rtrim($cfg['base_url'] ?? 'https://rxnav.nlm.nih.gov/REST', '/');
        $this->timeoutMs = (int)($cfg['timeout_ms'] ?? 8000);
        $this->cacheSeconds = (int)($cfg['cache_seconds'] ?? 3600);
    }

    /**
     * Search for drugs (getDrugs) and return top N concepts by preferred TTY list.
     * @return array<int, array{rxcui:string,name:string}>
     */
    public function searchTopConcepts(string $drugName, int $limit = 5, array $preferredTty = ['SBD','BPCK','SCD']): array
    {
        $key = 'rxnav:search:'.mb_strtolower(trim($drugName));

        return Cache::remember($key, $this->cacheSeconds, function () use ($drugName, $limit, $preferredTty) {
            try {
                $resp = $this->client()->get($this->baseUrl.'/drugs.json', [
                    'name' => $drugName,
                ])->json();

                $groups = $resp['drugGroup']['conceptGroup'] ?? [];
                if (empty($groups)) return [];

                $concepts = [];
                foreach ($preferredTty as $tty) {
                    foreach ($groups as $group) {
                        if (($group['tty'] ?? null) === $tty) {
                            $concepts = $group['conceptProperties'] ?? [];
                            break 2;
                        }
                    }
                }
                $concepts = array_slice($concepts, 0, $limit);

                return array_map(function($c) {
                    return [
                        'rxcui' => (string)($c['rxcui'] ?? ''),
                        'name'  => (string)($c['name'] ?? ''),
                    ];
                }, $concepts);
            } catch (Throwable $e) {
                Log::error('RxNav search error', ['e' => $e->getMessage()]);
                return [];
            }
        });
    }

    /**
     * Validate RXCUI exists using id endpoint.
     */
    public function validateRxcui(string $rxcui): ?string
    {
        $key = 'rxnav:valid:'.$rxcui;
        return Cache::remember($key, $this->cacheSeconds, function () use ($rxcui) {
            try {
                $resp = $this->client()->get($this->baseUrl.'/rxcui/'.$rxcui.'.json')->json();
                return $resp['idGroup']['name'] ?? null;
            } catch (Throwable $e) {
                Log::warning('RxNav validate error', ['rxcui'=>$rxcui, 'e'=>$e->getMessage()]);
                return null;
            }
        });
    }
    
    public function fetchIngredientsAndDoseForms(string $rxcui): array
    {
        $key = 'rxnav:historystatus:'.$rxcui;

        return Cache::remember($key, $this->cacheSeconds, function () use ($rxcui) {
            $baseNames = [];
            $doseForms = [];

            try {
                $response = $this->client()
                    ->get($this->baseUrl.'/rxcui/'.$rxcui.'/historystatus.json')
                    ->json();

                $history = $response['rxcuiHistory'] ?? [];

                // Extract baseNames from ingredientAndStrength
                foreach (($history['ingredientAndStrength'] ?? []) as $ingredient) {
                    if (!empty($ingredient['baseName'])) {
                        $baseNames[] = $ingredient['baseName'];
                    }
                }

                // Extract doseForms from doseFormGroupConcept
                foreach (($history['doseFormGroupConcept'] ?? []) as $doseGroup) {
                    if (!empty($doseGroup['doseFormGroupName'])) {
                        $doseForms[] = $doseGroup['doseFormGroupName'];
                    }
                }

            } catch (Throwable $e) {
                Log::error('RxNav historystatus error', [
                    'rxcui' => $rxcui,
                    'e' => $e->getMessage()
                ]);
            }

            return [
                'baseNames' => array_values(array_unique(array_filter($baseNames))),
                'doseForms' => array_values(array_unique(array_filter($doseForms))),
            ];
        });
    }

    protected function client()
    {
        return Http::timeout($this->timeoutMs / 1000)->retry(2, 200);
    }
    
}