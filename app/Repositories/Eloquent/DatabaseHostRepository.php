<?php

namespace Everest\Repositories\Eloquent;

use Everest\Models\DatabaseHost;
use Everest\Contracts\Repository\DatabaseHostRepositoryInterface;

class DatabaseHostRepository extends EloquentRepository implements DatabaseHostRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return DatabaseHost::class;
    }
}
