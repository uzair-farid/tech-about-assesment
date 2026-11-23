<?php

namespace App\Repositories;

use App\Models\CustomerToken;

class CustomerTokenRepository
{
    public function create(array $attributes): CustomerToken
    {
        return CustomerToken::query()->create($attributes);
    }
}
