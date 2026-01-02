<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Sale;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $saleData;

    /**
     * Create a new event instance.
     */
    public function __construct(Sale $sale)
    {
        // Load items relation if not loaded
        $sale->loadMissing(['items']);

        $this->saleData = [
            'id' => $sale->id,
            'code' => $sale->sale_number ?? '#' . $sale->id,
            'total' => (float) $sale->total,
            'status' => $sale->status,
            'itemsCount' => $sale->items->count(),
            'customer' => null, // Customer model doesn't exist yet
            'createdAt' => $sale->created_at->toIso8601String(),
            'timeAgo' => $sale->created_at->diffForHumans(),
            'tenantId' => $sale->tenant_id,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast to tenant-specific private channel
        return [
            new PrivateChannel('tenant.' . $this->saleData['tenantId'] . '.sales'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'sale.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return ['sale' => $this->saleData];
    }
}
