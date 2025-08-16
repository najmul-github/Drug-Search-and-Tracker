<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DrugSearchResource extends JsonResource
{
    /** @var array{rxcui:string,name:string,baseNames:array,doseForms:array} */
    public $resource;

    public function toArray($request): array
    {
        return [
            'rxcui' => $this->resource['rxcui'],
            'name' => $this->resource['name'],
            'baseNames' => $this->resource['baseNames'] ?? [],
            'doseForms' => $this->resource['doseForms'] ?? [],
        ];
    }
}