<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryBatch;
use App\Models\Notification;
use Carbon\Carbon;

class NotificationService
{
    // debounce window: do not duplicate same alert within 24h
    public const DEBOUNCE_HOURS = 24;

    /** Admin broadcast: low stock for an item */
    public static function notifyLowStock(InventoryItem $item, float $totalOnHand): ?Notification
    {
        if (!self::canFire('low_stock', ['item_id' => $item->id])) {
            return null;
        }

        return Notification::create([
            'type'            => 'low_stock',
            'title'           => "Low stock: {$item->name}",
            'body'            => "On-hand is {$totalOnHand} {$item->unit} (threshold: {$item->low_stock_threshold}).",
            'severity'        => 'warning',                  // matches your enum: info|warning|danger
            'scope'           => 'broadcast',                // broadcast by role (no targets rows needed)
            'audience_roles'  => json_encode(['admin']),     // notify admins
            'effective_from'  => now(),
            'effective_until' => null,
            'data'            => json_encode([
                'item_id'   => $item->id,
                'item_name' => $item->name,
                'unit'      => $item->unit,
                'threshold' => (int) $item->low_stock_threshold,
                'on_hand'   => (float) $totalOnHand,
            ]),
            'created_by'      => null, // system
        ]);
    }

    /** Admin broadcast: near expiry for a batch */
    public static function notifyNearExpiry(InventoryBatch $batch, int $daysLeft): ?Notification
    {
        if (is_null($batch->expiry_date)) {
            return null;
        }
        if (!self::canFire('near_expiry', ['batch_id' => $batch->id])) {
            return null;
        }

        $label = $batch->batch_number ?? $batch->lot_number ?? "#{$batch->id}";
        return Notification::create([
            'type'            => 'near_expiry',
            'title'           => "Near expiry: {$batch->item->name}",
            'body'            => "Batch {$label} expires in {$daysLeft} day(s).",
            'severity'        => 'info',
            'scope'           => 'broadcast',
            'audience_roles'  => json_encode(['admin']),
            'effective_from'  => now(),
            'effective_until' => null,
            'data'            => json_encode([
                'item_id'     => $batch->item_id,
                'batch_id'    => $batch->id,
                'label'       => $label,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'days_left'   => $daysLeft,
            ]),
            'created_by'      => null,
        ]);
    }

    /** Has a similar alert fired within the debounce window? (uses type + key fields in JSON data) */
    protected static function canFire(string $type, array $keyData): bool
    {
        $since = Carbon::now()->subHours(self::DEBOUNCE_HOURS);

        $q = Notification::query()
            ->where('type', $type)
            ->where('created_at', '>=', $since);

        // apply JSON key filters, e.g. data->item_id == 5 OR data->batch_id == 10
        foreach ($keyData as $k => $v) {
            $q->whereJsonContains('data->'.$k, $v);
        }

        return !$q->exists();
    }
}
