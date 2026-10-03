<?php

namespace Everest\Repositories\Eloquent;

use Everest\Models\Nest;
use Everest\Contracts\Repository\NestRepositoryInterface;

class NestRepository extends EloquentRepository implements NestRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return Nest::class;
    }
}
