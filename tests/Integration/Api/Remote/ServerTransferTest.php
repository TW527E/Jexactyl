<?php

namespace Everest\Tests\Integration\Api\Remote;

use Everest\Models\Node;
use Everest\Models\Backup;
use Everest\Models\Server;
use Everest\Models\Allocation;
use Everest\Models\ServerTransfer;
use Everest\Tests\Integration\IntegrationTestCase;

/**
 * Covers what Wings (1.12+) calls on the Panel while a server moves between nodes: the target
 * node fetching the configuration, and either node reporting a failure.
 */
class ServerTransferTest extends IntegrationTestCase
{
    private Server $server;

    private Node $target;

    private Allocation $allocation;

    private ServerTransfer $transfer;

    public function setUp(): void
    {
        parent::setUp();

        $this->server = $this->createServerModel();
        $this->target = Node::factory()->create();
        $this->allocation = Allocation::factory()->create(['node_id' => $this->target->id, 'server_id' => $this->server->id]);

        $this->transfer = ServerTransfer::factory()->create([
            'server_id' => $this->server->id,
            'old_node' => $this->server->node_id,
            'new_node' => $this->target->id,
            'old_allocation' => $this->server->allocation_id,
            'new_allocation' => $this->allocation->id,
        ]);
    }

    public function testTargetNodeCanFetchTheConfigurationDuringATransfer(): void
    {
        $this->actAsNode($this->target)
            ->getJson("/api/remote/servers/{$this->server->uuid}")
            ->assertOk()
            ->assertJsonPath('settings.uuid', $this->server->uuid);
    }

    public function testUnrelatedNodeCannotFetchTheConfiguration(): void
    {
        $this->actAsNode(Node::factory()->create())
            ->getJson("/api/remote/servers/{$this->server->uuid}")
            ->assertNotFound();
    }

    /**
     * The source node reports the failure when pushing the archive to the target fails.
     */
    public function testSourceNodeCanReportATransferFailure(): void
    {
        $this->actAsNode($this->server->node)
            ->postJson("/api/remote/servers/{$this->server->uuid}/transfer/failure")
            ->assertNoContent();

        $this->assertFalse($this->transfer->refresh()->successful);
        $this->assertNull($this->allocation->refresh()->server_id);
    }

    public function testOnlyTheTargetNodeCanReportATransferSuccess(): void
    {
        $this->actAsNode($this->server->node)
            ->postJson("/api/remote/servers/{$this->server->uuid}/transfer/success")
            ->assertNotFound();
    }

    public function testNodeCannotReportOnAnotherNodesBackup(): void
    {
        $backup = Backup::factory()->create(['server_id' => $this->server->id, 'is_successful' => false, 'completed_at' => null]);

        $this->actAsNode($this->target)
            ->postJson("/api/remote/backups/{$backup->uuid}", ['successful' => true, 'checksum' => 'x', 'checksum_type' => 'sha1', 'size' => 1])
            ->assertNotFound();

        $this->actAsNode($this->target)
            ->postJson("/api/remote/backups/{$backup->uuid}/restore", ['successful' => true])
            ->assertNotFound();

        $this->assertNull($backup->refresh()->completed_at);
    }

    private function actAsNode(Node $node): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $node->daemon_token_id . '.' . decrypt($node->daemon_token));
    }
}
