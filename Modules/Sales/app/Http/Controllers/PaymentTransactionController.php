<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Sales\Models\PaymentTransaction;

class PaymentTransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:sales.payments.view')->only(['downloadVoucher']);
        $this->middleware('permission:sales.payments.update')->only(['uploadVoucher']);
    }

    public function uploadVoucher(Request $request, PaymentTransaction $transaction)
    {
        $request->validate([
            'voucher' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $file = $request->file('voucher');
        if (!$file) {
            return response()->json(['message' => 'Archivo voucher no encontrado'], 422);
        }

        DB::beginTransaction();
        try {
            if (!empty($transaction->voucher_path)) {
                Storage::disk('public')->delete($transaction->voucher_path);
            }

            $path = Storage::disk('public')->putFile('payments/transactions', $file);
            $transaction->voucher_path = $path;
            $transaction->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voucher subido correctamente',
                'data' => [
                    'transaction_id' => $transaction->transaction_id,
                    'has_voucher' => true,
                    'voucher_url' => url("/api/v1/sales/payment-transactions/{$transaction->transaction_id}/voucher"),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al subir voucher',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadVoucher(Request $request, PaymentTransaction $transaction)
    {
        if (empty($transaction->voucher_path)) {
            return response()->json(['message' => 'Esta transacción no tiene voucher'], 404);
        }

        if (!Storage::disk('public')->exists($transaction->voucher_path)) {
            return response()->json(['message' => 'Voucher no encontrado en almacenamiento'], 404);
        }

        return Storage::disk('public')->download($transaction->voucher_path);
    }
}

