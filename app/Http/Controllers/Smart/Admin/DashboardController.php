<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Request\Request as SmartRequest;
use App\Models\Request\RequestStatusLog;
use App\Models\Inventory\Unit;
use App\Models\Master\Category;

class DashboardController extends Controller
{
    /**
     * Menampilkan halaman dashboard admin.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        
        // Redirect non-admin to user dashboard
        if (!$user->is_admin) {
            return redirect()->route('smart.user.dashboard');
        }

        // 1. Stats
        $stats = [
            'dalamPinjaman' => SmartRequest::where('status', 'borrow')->count(),
            'belumDiproses' => SmartRequest::whereIn('status', ['wait', 'approve'])->count(),
            'sudahDiproses' => SmartRequest::whereIn('status', ['confirm', 'handover', 'success', 'reject', 'cancel', 'pending'])->count(),
            'dalamPengembalian' => SmartRequest::where('status', 'return')->count(),
            'sudahPantauan' => Unit::whereIn('status', ['maintenance', 'broken'])
                ->orWhereIn('condition', ['Kurang baik', 'Buruk'])
                ->count(),
        ];

        // 2. Riwayat Terbaru: Penyerahan
        $penyerahan = SmartRequest::with(['user', 'department', 'project', 'items.barang.subcategory.category', 'handover'])
            ->whereIn('status', ['confirm', 'handover', 'borrow', 'return', 'success'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($req) {
                $firstItem = $req->items->first();
                $categoryInfo = '-';
                if ($firstItem && $firstItem->barang) {
                    $catName = $firstItem->barang->subcategory->category->name ?? '';
                    $subcatName = $firstItem->barang->subcategory->name ?? '';
                    $categoryInfo = $catName . ' (' . $subcatName . ')';
                }
                
                $timeStr = '-';
                if ($req->handover && $req->handover->scheduled_date) {
                    $timeStr = $req->handover->scheduled_date->format('d-m-Y H:i');
                } else {
                    $timeStr = $req->updated_at ? $req->updated_at->format('d-m-Y H:i') : '-';
                }

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'requester' => $req->user->name ?? '-',
                    'department' => $req->utilization === 'corporate' 
                        ? ($req->department->name ?? '-') 
                        : ($req->project->name ?? '-'),
                    'category_info' => $categoryInfo,
                    'time' => $timeStr,
                ];
            });

        // 2. Riwayat Terbaru: Pengembalian
        $pengembalian = SmartRequest::with(['user', 'department', 'project', 'items.barang.subcategory.category', 'return'])
            ->where(function ($query) {
                $query->where('status', 'return')
                      ->orWhere(function ($q) {
                          $q->where('status', 'success')
                            ->whereHas('return');
                      });
            })
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($req) {
                $firstItem = $req->items->first();
                $categoryInfo = '-';
                if ($firstItem && $firstItem->barang) {
                    $catName = $firstItem->barang->subcategory->category->name ?? '';
                    $subcatName = $firstItem->barang->subcategory->name ?? '';
                    $categoryInfo = $catName . ' (' . $subcatName . ')';
                }
                
                $timeStr = '-';
                if ($req->return && $req->return->scheduled_date) {
                    $timeStr = $req->return->scheduled_date->format('d-m-Y H:i');
                } else {
                    $timeStr = $req->updated_at ? $req->updated_at->format('d-m-Y H:i') : '-';
                }

                return [
                    'id' => $req->id,
                    'number' => $req->request_number,
                    'requester' => $req->user->name ?? '-',
                    'department' => $req->utilization === 'corporate' 
                        ? ($req->department->name ?? '-') 
                        : ($req->project->name ?? '-'),
                    'category_info' => $categoryInfo,
                    'time' => $timeStr,
                ];
            });

        // 3. Aktivitas Terbaru
        $aktivitasTerbaru = RequestStatusLog::with(['request', 'changer'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->changer->name ?? 'System',
                    'request_number' => $log->request->request_number ?? '-',
                    'action' => $log->note ?? 'Mengubah status permintaan',
                    'time' => $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('d-m-Y H:i') : '-',
                ];
            });

        // 4. Total Stok per Category
        $categories = Category::all();
        $totalStok = $categories->map(function ($cat) {
            $count = Unit::whereHas('lot.barang.subcategory.category', function ($q) use ($cat) {
                $q->where('id', $cat->id);
            })->count();
            
            // Fallback for consumables with no unit records
            if ($cat->is_consumable && $count === 0) {
                $count = \App\Models\Inventory\Lot::whereHas('barang.subcategory.category', function ($q) use ($cat) {
                    $q->where('id', $cat->id);
                })->sum('current_quantity');
            }

            // Remap code ELK -> Elektronik in display if necessary, or just keep original
            $displayName = $cat->name;
            if ($cat->code === 'ELK') {
                $displayName = 'Elektronik';
            } elseif ($cat->code === 'FUR') {
                $displayName = 'Furniture';
            }

            return [
                'name' => $displayName,
                'code' => $cat->code,
                'count' => $count,
            ];
        });

        // 5. Kualitas Stok per Category
        $kualitasStok = [];
        foreach ($categories as $cat) {
            $baik = Unit::whereHas('lot.barang.subcategory.category', function ($q) use ($cat) {
                $q->where('id', $cat->id);
            })->where('condition', 'Baik')->count();
            
            $kurangBaik = Unit::whereHas('lot.barang.subcategory.category', function ($q) use ($cat) {
                $q->where('id', $cat->id);
            })->where('condition', 'Kurang baik')->count();
            
            $buruk = Unit::whereHas('lot.barang.subcategory.category', function ($q) use ($cat) {
                $q->where('id', $cat->id);
            })->where('condition', 'Buruk')->count();

            // Remap code/display name
            $displayName = $cat->name;
            if ($cat->code === 'ELK') {
                $displayName = 'Elektronik';
            } elseif ($cat->code === 'FUR') {
                $displayName = 'Furniture';
            }

            $kualitasStok[$cat->code] = [
                'name' => $displayName,
                'code' => $cat->code,
                'baik' => $baik,
                'kurang_baik' => $kurangBaik,
                'buruk' => $buruk,
            ];
        }

        return Inertia::render('Smart/Admin/Dashboard', [
            'user' => $user,
            'stats' => $stats,
            'riwayatTerbaru' => [
                'penyerahan' => $penyerahan,
                'pengembalian' => $pengembalian,
            ],
            'aktivitasTerbaru' => $aktivitasTerbaru,
            'totalStok' => $totalStok,
            'kualitasStok' => $kualitasStok,
        ]);
    }
}

