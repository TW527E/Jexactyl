<?php

namespace Everest\Contracts\Repository;

use Everest\Models\Server;

interface ServerRepositoryInterface extends RepositoryInterface
{
    /**
     * Return a server by UUID.
     *
     * @throws \Everest\Exceptions\Repository\RecordNotFoundException
     */
    public function getByUuid(string $uuid): Server;

    /**
     * Check if a given UUID and UUID-Short string are unique to a server.
     */
    public function isUniqueUuidCombo(string $uuid, string $short): bool;
}
