<?php

namespace Everest\Http\Controllers\Api\Application\Databases;

use Everest\Facades\Activity;
use Illuminate\Http\Response;
use Everest\Models\DatabaseHost;
use Spatie\QueryBuilder\QueryBuilder;
use Everest\Exceptions\DisplayException;
use Everest\Services\Databases\Hosts\HostUpdateService;
use Everest\Services\Databases\Hosts\HostCreationService;
use Everest\Exceptions\Http\QueryValueOutOfRangeHttpException;
use Everest\Transformers\Api\Application\DatabaseHostTransformer;
use Everest\Http\Controllers\Api\Application\ApplicationApiController;
use Everest\Http\Requests\Api\Application\Databases\GetDatabaseRequest;
use Everest\Http\Requests\Api\Application\Databases\GetDatabasesRequest;
use Everest\Http\Requests\Api\Application\Databases\StoreDatabaseRequest;
use Everest\Http\Requests\Api\Application\Databases\DeleteDatabaseRequest;
use Everest\Http\Requests\Api\Application\Databases\UpdateDatabaseRequest;

class DatabaseController extends ApplicationApiController
{
    /**
     * DatabaseController constructor.
     */
    public function __construct(private HostCreationService $creationService, private HostUpdateService $updateService)
    {
        parent::__construct();
    }

    /**
     * Returns an array of all database hosts.
     */
    public function index(GetDatabasesRequest $request): array
    {
        $perPage = (int) $request->query('per_page', '20');
        if ($perPage < 1 || $perPage > 100) {
            throw new QueryValueOutOfRangeHttpException('per_page', 1, 100);
        }

        $databases = QueryBuilder::for(DatabaseHost::query())
            ->allowedFilters(...['name', 'host'])
            ->allowedSorts(...['id', 'name', 'host'])
            ->paginate($perPage);

        return $this->transform($databases, DatabaseHostTransformer::class);
    }

    /**
     * Returns a single database host.
     */
    public function view(GetDatabaseRequest $request, DatabaseHost $databaseHost): array
    {
        return $this->transform($databaseHost, DatabaseHostTransformer::class);
    }

    /**
     * Creates a new database host.
     *
     * @throws \Throwable
     */
    public function store(StoreDatabaseRequest $request): array
    {
        $database = $this->creationService->handle($request->validated());

        Activity::event('admin:database-hosts:create')
            ->subject($database)
            ->property('database-host', $database)
            ->description('A new database host was created')
            ->log();

        return $this->transform($database, DatabaseHostTransformer::class);
    }

    /**
     * Updates a database host.
     *
     * @throws \Throwable
     */
    public function update(UpdateDatabaseRequest $request, DatabaseHost $databaseHost): array
    {
        $database = $this->updateService->handle($databaseHost->id, $request->validated());

        Activity::event('admin:database-hosts:update')
            ->subject($database)
            ->property('database-host', $database)
            ->property('new_data', $request->all())
            ->description('A database host was updated')
            ->log();

        return $this->transform($database, DatabaseHostTransformer::class);
    }

    /**
     * Deletes a database host.
     *
     * @throws \Exception
     */
    public function delete(DeleteDatabaseRequest $request, DatabaseHost $databaseHost): Response
    {
        // The databases table holds a restricting foreign key to its host.
        if ($databaseHost->databases()->exists()) {
            throw new DisplayException('Cannot delete a database host that still has databases attached to it.');
        }

        $databaseHost->delete();

        Activity::event('admin:database-hosts:delete')
            ->subject($databaseHost)
            ->property('database-host', $databaseHost)
            ->description('A database host was deleted')
            ->log();

        return $this->returnNoContent();
    }
}
