<?php

namespace Everest\Repositories\Eloquent;

use Everest\Models\Egg;
use Everest\Contracts\Repository\EggRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Everest\Exceptions\Repository\RecordNotFoundException;

class EggRepository extends EloquentRepository implements EggRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return Egg::class;
    }

    /**
     * Return all the data needed to export a service.
     *
     * @throws RecordNotFoundException
     */
    public function getWithExportAttributes(int $id): Egg
    {
        try {
            return $this->getBuilder()->with('scriptFrom', 'configFrom', 'variables')->findOrFail($id, $this->getColumns());
        } catch (ModelNotFoundException) {
            throw new RecordNotFoundException();
        }
    }
}
