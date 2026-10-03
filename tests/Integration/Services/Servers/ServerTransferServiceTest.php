<?php

namespace Everest\Tests\Integration\Services\Servers;

use Mockery as m;
use Everest\Models\Node;
use GuzzleHttp\Psr7\Request;
use Lcobucci\JWT\Token\Plain;
use Everest\Models\Allocation;
use Everest\Models\ServerTransfer;
use GuzzleHttp\Exception\ConnectException;
use Everest\Tests\Integration\IntegrationTestCase;
use Everest\Services\Servers\ServerTransferService;
use Everest\Repositories\Wings\DaemonTransferRepository;
use Everest\Exceptions\Http\Connection\DaemonConnectionException;

class ServerTransferServiceTest extends IntegrationTestCase
{
    private m\MockInterface $repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = m::mock(DaemonTransferRepository::class);
        $this->repository->allows('setServer')->andReturnSelf();
        $this->app->instance(DaemonTransferRepository::class, $this->repository);
    }

    /**
     * Wings 1.12.2+ answers 403 to a transfer token without the "transfer" scope.
     */
    public function testTransferTokenCarriesTheTransferScope(): void
    {
        [$server, $target, $allocation] = $this->scenario();

        $this->repository->expects('notify')->withArgs(function (Node $node, Plain $token) use ($target) {
            return $node->is($target) && $token->claims()->get('scope') === 'transfer';
        });

        $this->app->make(ServerTransferService::class)
            ->handle($server, ['node_id' => $target->id, 'allocation_id' => $allocation->id]);

        $this->assertNotNull($server->refresh()->transfer);
    }

    /**
     * If the source node never hears about the transfer it never reports back, so nothing may be left behind.
     */
    public function testUnreachableSourceNodeLeavesNoTransferBehind(): void
    {
        [$server, $target, $allocation] = $this->scenario();

        $this->repository->expects('notify')->andThrow(
            new DaemonConnectionException(new ConnectException('down', new Request('POST', '/')))
        );

        try {
            $this->app->make(ServerTransferService::class)
                ->handle($server, ['node_id' => $target->id, 'allocation_id' => $allocation->id]);

            $this->fail('Expected the transfer to fail.');
        } catch (DaemonConnectionException) {
        }

        $this->assertFalse(ServerTransfer::query()->where('server_id', $server->id)->exists());
        $this->assertNull($allocation->refresh()->server_id);
    }

    private function scenario(): array
    {
        $server = $this->createServerModel();
        $target = Node::factory()->create();
        $allocation = Allocation::factory()->create(['node_id' => $target->id]);

        return [$server, $target, $allocation];
    }
}
