<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\ServiceDiscount;
use Carbon\Carbon;

class ServiceDiscountController extends Controller
{
    public function index(Service $service)
    {
        // 🟡 Mark expired launched promos as 'done'
        $cleanupCount = ServiceDiscount::where('status', 'launched')
            ->whereDate('end_date', '<', Carbon::today())
            ->update(['status' => 'done']);

        // 🟢 Return active promos and cleanup count
        return response()->json([
            'cleanup_count' => $cleanupCount,
            'promos' => $service->discounts()
                ->whereIn('status', ['planned', 'launched'])
                ->orderBy('start_date')
                ->get(),
        ]);
    }

    public function store(Request $request, Service $service)
    {
        $validated = $request->validate([
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'discounted_price' => 'required|numeric|min:0|max:' . $service->price,
        ]);

        $overlap = $service->discounts()
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhere(function ($q2) use ($validated) {
                        $q2->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                    });
            })
            ->where('status', '!=', 'canceled')
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'A discount already exists for this date range.',
            ], 422);
        }

        $discount = $service->discounts()->create($validated);
        return response()->json($discount, 201);
    }

    public function update(Request $request, $id)
    {
        $discount = ServiceDiscount::findOrFail($id);
        if ($discount->status !== 'planned') {
            return response()->json(['message' => 'Only planned promos can be edited.'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'discounted_price' => 'required|numeric|min:0|max:' . $discount->service->price,
        ]);

        $discount->update($validated);
        return response()->json($discount);
    }

    // public function destroy($id)
    // {
    //     $discount = ServiceDiscount::findOrFail($id);

    //     if ($discount->status !== 'planned') {
    //         return response()->json(['message' => 'Only planned promos can be deleted.'], 403);
    //     }

    //     $discount->delete();
    //     return response()->json(['message' => 'Promo deleted.']);
    // }

    public function archive(Request $request)
    {
        $query = ServiceDiscount::with('service')
            ->where(function ($q) {
                $q->where('status', 'done')
                    ->orWhere('status', 'canceled');
            });

        if ($request->has('year')) {
            $query->whereYear('start_date', $request->year);
        }

        return response()->json(
            $query->orderBy('start_date')->get()
        );
    }


    // 🟢 Launch promo
    public function launch($id)
    {
        $discount = ServiceDiscount::findOrFail($id);
        if ($discount->status !== 'planned') {
            return response()->json(['message' => 'Promo must be in planned state to launch.'], 422);
        }

        $discount->status = 'launched';
        $discount->activated_at = now();
        $discount->save();

        return response()->json(['message' => 'Promo launched.']);
    }

    // 🟡 Cancel promo
    public function cancel($id)
    {
        $discount = ServiceDiscount::findOrFail($id);

        if ($discount->status !== 'launched') {
            return response()->json(['message' => 'Only launched promos can be canceled.'], 422);
        }

        if (!$discount->activated_at || now()->diffInHours($discount->activated_at) > 24) {
            return response()->json(['message' => 'Cancel period has expired.'], 403);
        }

        $discount->status = 'canceled';
        $discount->save();

        return response()->json(['message' => 'Promo canceled.']);
    }

    public function allActivePromos()
    {
        $promos = ServiceDiscount::with('service')
            ->whereIn('status', ['planned', 'launched'])
            ->orderBy('start_date')
            ->get();

        return response()->json($promos);
    }

}
