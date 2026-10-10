<?php

namespace App\Http\Resources;

use App\Services\DesignPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(DesignPresenter::class)->forUser($this->resource, $request->user());
    }
}
