<?php

namespace Everest\Contracts\Repository;

interface DatabaseRepositoryInterface extends RepositoryInterface
{
    public const DEFAULT_CONNECTION_NAME = 'dynamic';

    /**
     * Return the connection to execute statements against.
     */
    public function getConnection(): string;

    /**
     * Create a new database on a given connection.
     */
    public function createDatabase(string $database): bool;

    /**
     * Create a new database user on a given connection.
     */
    public function createUser(string $username, string $remote, string $password, ?int $max_connections): bool;

    /**
     * Give a specific user access to a given database.
     */
    public function assignUserToDatabase(string $database, string $username, string $remote): bool;

    /**
     * Flush the privileges for a given connection.
     */
    public function flush(): bool;

    /**
     * Drop a given database on a specific connection.
     */
    public function dropDatabase(string $database): bool;

    /**
     * Drop a given user on a specific connection.
     */
    public function dropUser(string $username, string $remote): bool;
}
