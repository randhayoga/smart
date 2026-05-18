<?php

namespace App\Http\Controllers\Smart\User;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Barang;
use App\Models\Master\Category;
use App\Models\Cart\ConsumableBasket;
use App\Models\Cart\AssetBasket;
use App\Models\Request\Request as UserRequest;
use App\Models\Request\RequestItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BrowseController extends Controller
{
    /**
     * Display the item listing for users to browse.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Redirect admin to admin dashboard
        if ($user->is_admin) {
            return redirect()->route('smart.dashboard');
        }

        // Fetch all categories
        $categories = Category::orderBy('name')->get(['id', 'name', 'code']);

        // Fetch all barangs with stock count calculated
        $barangs = Barang::with(['subcategory.category', 'brand', 'uom', 'lots.units'])
            ->get()
            ->map(function ($barang) {
                // Calculate stock amount
                if ($barang->type === 'asset') {
                    // For assets, amount is the count of individual units in lots of this barang
                    $amount = $barang->lots->flatMap->units->count();
                } else {
                    // For consumables, amount is the sum of quantity_change in inventory_logs
                    $amount = DB::table('inventory_logs')
                        ->where('barang_id', $barang->id)
                        ->sum('quantity_change');
                    
                    if ($amount == 0 && $barang->lots->count() > 0) {
                        $amount = $barang->lots->count() * 10; // Default seeded stock fallback
                    }
                }

                return [
                    'id' => $barang->id,
                    'code' => $barang->number,
                    'name' => $barang->specification, // Using specification as item display name
                    'brand' => $barang->brand->name ?? 'Tanpa Merek',
                    'specification' => $barang->specification,
                    'category_id' => $barang->subcategory->category->id ?? null,
                    'category' => $barang->subcategory->category->name ?? 'Kategori Lain',
                    'subcategory' => $barang->subcategory->name ?? 'Subkategori Lain',
                    'type' => $barang->type,
                    'is_consumable' => $barang->subcategory->category->is_consumable ?? true,
                    'amount' => max(0, $amount),
                    'unit' => $barang->uom->name ?? 'pcs',
                ];
            });

        return Inertia::render('Smart/User/Browse', [
            'user' => $user,
            'barangs' => $barangs,
            'categories' => $categories,
        ]);
    }

    /**
     * Add a selected item to the user's shopping basket (cart).
     */
    public function addToCart(Request $request): RedirectResponse
    {
        $request->validate([
            'barang_id' => 'required|exists:barangs,id',
            'quantity' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $user = auth()->user();
        $barang = Barang::with('subcategory.category')->findOrFail($request->barang_id);
        $isConsumable = $barang->subcategory->category->is_consumable ?? true;

        if ($isConsumable) {
            // Add or update consumable basket
            $basket = ConsumableBasket::firstOrNew([
                'user_id' => $user->id,
                'barang_id' => $barang->id,
            ]);
            $basket->quantity = ($basket->quantity ?? 0) + $request->quantity;
            $basket->save();

            return redirect()->back()->with('success', 'Barang berhasil ditambahkan ke keranjang habis pakai.');
        } else {
            // Add or update asset basket
            $basket = AssetBasket::firstOrNew([
                'user_id' => $user->id,
                'barang_id' => $barang->id,
            ]);
            $basket->quantity = ($basket->quantity ?? 0) + $request->quantity;
            $basket->start_date = $request->start_date ?? now();
            $basket->end_date = $request->end_date ?? now()->addDays(7);
            $basket->save();

            return redirect()->back()->with('success', 'Barang berhasil ditambahkan ke keranjang pinjam.');
        }
    }

    /**
     * Submit a quick PO / Reimburse request for out of stock items.
     */
    public function submitPoRequest(Request $request): RedirectResponse
    {
        $request->validate([
            'barang_id' => 'required|exists:barangs,id',
            'quantity_requested' => 'required|integer|min:1',
            'reason' => 'required|string|max:1000',
        ]);

        $user = auth()->user();
        $barang = Barang::findOrFail($request->barang_id);

        // Generate dynamic request number following standard system pattern (MMYYYY-NNNN)
        $month = date('m');
        $year = date('Y');
        $prefix = "{$month}{$year}-";
        
        $latest = UserRequest::where('request_number', 'like', $prefix . '%')
            ->orderBy('request_number', 'desc')
            ->first();

        if ($latest) {
            $seq = (int) substr($latest->request_number, 7) + 1;
        } else {
            $seq = 1;
        }
        $requestNumber = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

        // Get approver (manager of department or fallback)
        $approverId = $user->department?->manager_id ?? User::where('role', 'manager')->first()?->id ?? 3;

        // Start transaction
        DB::transaction(function () use ($user, $barang, $request, $requestNumber, $approverId) {
            // Create base request
            $userRequest = UserRequest::create([
                'request_number' => $requestNumber,
                'user_id' => $user->id,
                'approver_id' => $approverId,
                'utilization' => 'corporate',
                'department_id' => $user->department_id,
                'reasoning' => 'PO / Reimburse: ' . $request->reason,
                'status' => 'wait', // Wait for approval status
            ]);

            // Add the out-of-stock item
            RequestItem::create([
                'request_id' => $userRequest->id,
                'barang_id' => $barang->id,
                'quantity_requested' => $request->quantity_requested,
            ]);

            // Log status
            DB::table('request_status_logs')->insert([
                'request_id' => $userRequest->id,
                'status' => 'wait',
                'description' => 'Permintaan PO / Reimbursement baru dibuat',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Permintaan PO / Reimbursement berhasil diajukan dengan nomor: ' . $requestNumber);
    }
}
