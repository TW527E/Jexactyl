<?php

namespace Everest\Repositories\Eloquent;

use Everest\Models\Node;
use Everest\Contracts\Repository\NodeRepositoryInterface;

class NodeRepository extends EloquentRepository implements NodeRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return Node::class;
    }
}
