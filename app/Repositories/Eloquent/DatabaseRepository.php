<?php

namespace Everest\Repositories\Eloquent;

use Everest\Models\Database;
use Illuminate\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Everest\Contracts\Repository\DatabaseRepositoryInterface;

class DatabaseRepository extends EloquentRepository implements DatabaseRepositoryInterface
{
    protected string $connection = self::DEFAULT_CONNECTION_NAME;

    /**
     * DatabaseRepository constructor.
     */
    public function __construct(Application $application, private DatabaseManager $database)
    {
        parent::__construct($application);
    }

    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return Database::class;
    }

    /**
     * Return the connection to execute statements against.
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * Create a new database on a given connection.
     */
    public function createDatabase(string $database): bool
    {
        return $this->run(sprintf('CREATE DATABASE IF NOT EXISTS `%s`', $database));
    }

    /**
     * Create a new database user on a given connection.
     */
    public function createUser(string $username, string $remote, string $password, ?int $max_connections): bool
    {
        $args = [$username, $remote, $password];
        $command = 'CREATE USER `%s`@`%s` IDENTIFIED BY \'%s\'';

        if (!empty($max_connections)) {
            $args[] = $max_connections;
            $command .= ' WITH MAX_USER_CONNECTIONS %s';
        }

        return $this->run(sprintf($command, ...$args));
    }

    /**
     * Give a specific user access to a given database.
     */
    public function assignUserToDatabase(string $database, string $username, string $remote): bool
    {
        return $this->run(sprintf(
            'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, REFERENCES, INDEX, LOCK TABLES, CREATE ROUTINE, ALTER ROUTINE, EXECUTE, CREATE TEMPORARY TABLES, CREATE VIEW, SHOW VIEW, EVENT, TRIGGER ON `%s`.* TO `%s`@`%s`',
            $database,
            $username,
            $remote
        ));
    }

    /**
     * Flush the privileges for a given connection.
     */
    public function flush(): bool
    {
        return $this->run('FLUSH PRIVILEGES');
    }

    /**
     * Drop a given database on a specific connection.
     */
    public function dropDatabase(string $database): bool
    {
        return $this->run(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
    }

    /**
     * Drop a given user on a specific connection.
     */
    public function dropUser(string $username, string $remote): bool
    {
        return $this->run(sprintf('DROP USER IF EXISTS `%s`@`%s`', $username, $remote));
    }

    /**
     * Run the provided statement against the database on a given connection.
     */
    private function run(string $statement): bool
    {
        return $this->database->connection($this->getConnection())->statement($statement);
    }
}
