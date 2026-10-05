<?php

namespace Everest\Repositories\Eloquent;

use Everest\Models\ApiKey;
use Everest\Contracts\Repository\ApiKeyRepositoryInterface;

class ApiKeyRepository extends EloquentRepository implements ApiKeyRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return ApiKey::class;
    }
}
