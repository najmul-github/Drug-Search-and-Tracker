<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserDrugResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'rxcui' => $this->rxcui,
            'name' => $this->name,
            'baseNames' => $this->base_names ?? [],
            'doseForms' => $this->dose_forms ?? [],
            'addedAt' => $this->created_at?->toIso8601String()
        ];
    }
}