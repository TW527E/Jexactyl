<?php

namespace Everest\Repositories\Eloquent;

use Everest\Models\EggVariable;
use Everest\Contracts\Repository\EggVariableRepositoryInterface;

class EggVariableRepository extends EloquentRepository implements EggVariableRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return EggVariable::class;
    }
}
