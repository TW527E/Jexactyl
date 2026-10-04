<?php

namespace Everest\Tests\Integration\Api\Application\Databases;

use Everest\Models\Database;
use Illuminate\Http\Response;
use Everest\Models\DatabaseHost;
use Illuminate\Support\Facades\Crypt;
use Everest\Tests\Integration\Api\Application\ApplicationApiIntegrationTestCase;

class DatabaseHostControllerTest extends ApplicationApiIntegrationTestCase
{
    /**
     * A host pointing at the test database itself, so the connection check on update passes.
     */
    private function host(): DatabaseHost
    {
        $connection = config('database.connections.' . config('database.default'));

        return DatabaseHost::factory()->create([
            'host' => $connection['host'],
            'port' => $connection['port'],
            'username' => $connection['username'],
            'password' => Crypt::encrypt($connection['password'] ?? ''),
        ]);
    }

    public function testHostCanBeViewed()
    {
        $host = $this->host();

        $this->getJson('/api/application/databases/' . $host->id)
            ->assertOk()
            ->assertJsonPath('attributes.id', $host->id)
            ->assertJsonPath('attributes.name', $host->name);
    }

    public function testReachableHostReportsOnline()
    {
        $this->getJson('/api/application/databases/' . $this->host()->id . '/status')
            ->assertOk()
            ->assertJsonPath('online', true);
    }

    public function testUnreachableHostReportsOfflineWithTheReason()
    {
        // Check a working host first: a cached "dynamic" connection must not leak into the next check.
        $this->getJson('/api/application/databases/' . $this->host()->id . '/status')->assertJsonPath('online', true);

        $host = $this->host();
        $host->update(['port' => 1]);

        $this->getJson('/api/application/databases/' . $host->id . '/status')
            ->assertOk()
            ->assertJsonPath('online', false)
            ->assertJsonStructure(['error']);
    }

    public function testHostCanBeUpdated()
    {
        $host = $this->host();

        $this->patchJson('/api/application/databases/' . $host->id, [
            'name' => 'Renamed',
            'host' => $host->host,
            'port' => $host->port,
            'username' => $host->username,
        ])->assertOk()->assertJsonPath('attributes.name', 'Renamed');

        $this->assertSame('Renamed', $host->refresh()->name);
    }

    public function testHostCanBeDeleted()
    {
        $host = $this->host();

        $this->deleteJson('/api/application/databases/' . $host->id)->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertModelMissing($host);
    }

    public function testHostWithDatabasesCannotBeDeleted()
    {
        $server = $this->createServerModel();
        $host = $this->host();
        Database::factory()->create(['server_id' => $server->id, 'database_host_id' => $host->id]);

        // Nothing can be asserted against the database after this: the exception handler rolls
        // back every open transaction, including the one this test case runs inside.
        $this->deleteJson('/api/application/databases/' . $host->id)
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonPath('errors.0.detail', 'Cannot delete a database host that still has databases attached to it.');
    }
}
