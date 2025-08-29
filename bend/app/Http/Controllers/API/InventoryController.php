<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    // GET /api/inventory/items
    public function items(Request $r) {
        $items = InventoryItem::query()
            ->where('is_active', true)
            ->withSum('batches as current_stock', 'qty_on_hand')
            ->orderBy('name')
            ->get();

        return response()->json($items);
    }

    // POST /api/inventory/items
    public function createItem(Request $r) {
        $data = $r->validate([
            'name'          => 'required|string|max:255',
            'sku'           => 'nullable|string|max:100',
            'category'      => 'nullable|string|max:100',
            'unit'          => 'nullable|string|max:20',
            'unit_hint'     => 'nullable|string|max:255',
            'reorder_level' => 'nullable|numeric|min:0',
        ]);
        $data['unit'] = $data['unit'] ?? 'piece';
        $data['reorder_level'] = $data['reorder_level'] ?? 0;

        $item = InventoryItem::create($data);
        return response()->json($item, 201);
    }

    // PATCH /api/inventory/items/{id}
    public function updateItem(Request $r, $id) {
        $item = InventoryItem::findOrFail($id);
        $item->update($r->only(['name','sku','category','unit','unit_hint','reorder_level','is_active']));
        return response()->json($item);
    }

    // POST /api/inventory/receive  (IN)
    public function receive(Request $r) {
        $data = $r->validate([
            'item_id'     => 'required|exists:inventory_items,id',
            'lot_no'      => 'nullable|string|max:100',
            'expiry_date' => 'nullable|date',
            'supplier'    => 'nullable|string|max:255',
            'unit_cost'   => 'nullable|numeric|min:0',
            'qty'         => 'required|numeric|min:0.01',
            'received_at' => 'nullable|date',
        ]);

        // Optional: stricter rule for drugs
        $item = InventoryItem::findOrFail($data['item_id']);
        $needsLot = in_array(($item->category ?? ''), ['Drug','Disinfectant','Biologic','Implant']);
        if ($needsLot && empty($data['lot_no'])) {
            return response()->json(['message' => 'Lot number is required for this item category.'], 422);
        }

        $batch = InventoryBatch::create([
            'item_id'     => $data['item_id'],
            'lot_no'      => $data['lot_no'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'supplier'    => $data['supplier'] ?? null,
            'unit_cost'   => $data['unit_cost'] ?? null,
            'qty_on_hand' => $data['qty'],
            'received_at' => $data['received_at'] ?? now()->toDateString(),
        ]);

        InventoryMovement::create([
            'batch_id' => $batch->id,
            'type'     => 'IN',
            'qty'      => $data['qty'],
            'reason'   => 'Receive',
            'user_id'  => $r->user()->id,
        ]);

        return response()->json(['message' => 'Stock received', 'batch_id' => $batch->id], 201);
    }

    // POST /api/inventory/consume  (OUT)  — FEFO if expiry exists, else FIFO
    public function consume(Request $r) {
        $data = $r->validate([
            'item_id'  => 'required|exists:inventory_items,id',
            'qty'      => 'required|numeric|min:0.01',
            'reason'   => 'nullable|string|max:255',
            'ref_type' => 'nullable|string|max:100',
            'ref_id'   => 'nullable|integer',
        ]);

        $remaining = (float) $data['qty'];

        DB::beginTransaction();
        try {
            $batches = InventoryBatch::where('item_id', $data['item_id'])
                ->where('qty_on_hand', '>', 0)
                // FEFO (expiry first), then by id (FIFO fallback)
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date ASC, id ASC')
                ->lockForUpdate()->get();

            foreach ($batches as $b) {
                if ($remaining <= 0) break;

                $take = min((float) $b->qty_on_hand, $remaining);
                if ($take <= 0) continue;

                $b->qty_on_hand = (float) $b->qty_on_hand - $take;
                $b->save();

                InventoryMovement::create([
                    'batch_id' => $b->id,
                    'type'     => 'OUT',
                    'qty'      => $take,
                    'reason'   => $data['reason'] ?? 'Consume',
                    'ref_type' => $data['ref_type'] ?? null,
                    'ref_id'   => $data['ref_id'] ?? null,
                    'user_id'  => $r->user()->id,
                ]);

                $remaining -= $take;
            }

            if ($remaining > 0.0001) {
                DB::rollBack();
                return response()->json(['message' => 'Insufficient stock'], 422);
            }

            DB::commit();
            return response()->json(['message' => 'Consumed']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // POST /api/inventory/adjust  (+/- on a specific batch)
    public function adjust(Request $r) {
        $data = $r->validate([
            'batch_id' => 'required|exists:inventory_batches,id',
            'delta'    => 'required|numeric',  // + add / - subtract
            'reason'   => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $b = InventoryBatch::lockForUpdate()->findOrFail($data['batch_id']);
            $newQty = (float) $b->qty_on_hand + (float) $data['delta'];
            if ($newQty < -0.0001) {
                DB::rollBack();
                return response()->json(['message' => 'Cannot go below zero'], 422);
            }
            $b->qty_on_hand = max(0, $newQty);
            $b->save();

            InventoryMovement::create([
                'batch_id' => $b->id,
                'type'     => 'ADJUST',
                'qty'      => (float) $data['delta'],
                'reason'   => $data['reason'] ?? 'Adjustment',
                'user_id'  => $r->user()->id,
            ]);

            DB::commit();
            return response()->json(['message' => 'Adjusted']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // GET /api/inventory/low-stock
    public function lowStock(Request $r) {
        $rows = InventoryItem::query()
            ->where('is_active', true)
            ->withSum('batches as current_stock', 'qty_on_hand')
            ->having('current_stock', '<=', DB::raw('reorder_level'))
            ->orderBy('current_stock')
            ->get(['id','name','unit','reorder_level']);

        return response()->json($rows);
    }

    // GET /api/inventory/near-expiry?withinDays=60
    public function nearExpiry(Request $r) {
        $days = max(1, (int) $r->query('withinDays', 60));
        $today = now()->toDateString();
        $until = now()->addDays($days)->toDateString();

        $rows = InventoryBatch::query()
            ->with('item:id,name')
            ->whereNotNull('expiry_date')
            ->where('qty_on_hand', '>', 0)
            ->whereBetween('expiry_date', [$today, $until])
            ->orderBy('expiry_date')
            ->get(['id','item_id','lot_no','expiry_date','qty_on_hand']);

        return response()->json($rows);
    }
}
