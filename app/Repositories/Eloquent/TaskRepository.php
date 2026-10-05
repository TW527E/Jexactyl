<?php

namespace Everest\Repositories\Eloquent;

use Everest\Models\Task;
use Everest\Contracts\Repository\TaskRepositoryInterface;

class TaskRepository extends EloquentRepository implements TaskRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return Task::class;
    }
}
