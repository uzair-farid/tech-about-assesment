<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fare;
use Illuminate\Http\Resources\Json\JsonResource;

class FareController extends Controller
{
    public function index(): JsonResource
    {
        return JsonResource::collection(
            Fare::query()->orderBy('origin')->orderBy('destination')->get()
        );
    }
}
