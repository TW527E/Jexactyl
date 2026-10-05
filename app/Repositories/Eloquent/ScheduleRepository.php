<?php

namespace Everest\Repositories\Eloquent;

use Everest\Models\Schedule;
use Everest\Contracts\Repository\ScheduleRepositoryInterface;

class ScheduleRepository extends EloquentRepository implements ScheduleRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return Schedule::class;
    }
}
