<?php

namespace Everest\Contracts\Repository;

use Everest\Models\Egg;

interface EggRepositoryInterface extends RepositoryInterface
{
    /**
     * Return all the data needed to export a service.
     *
     * @throws \Everest\Exceptions\Repository\RecordNotFoundException
     */
    public function getWithExportAttributes(int $id): Egg;
}
