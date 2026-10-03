<?php

namespace Everest\Repositories\Eloquent;

use Everest\Models\Subuser;
use Everest\Contracts\Repository\SubuserRepositoryInterface;

class SubuserRepository extends EloquentRepository implements SubuserRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return Subuser::class;
    }
}
