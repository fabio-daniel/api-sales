<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,10'],
        ]);

        // $quantities: total por producto (para validar el stock).
        // $lines: una línea por producto y descuento, sumando las cantidades repetidas.
        $quantities = [];
        $lines = [];
        foreach ($data['items'] as $item) {
            $id = $item['product_id'];
            $discount = (float) ($item['discount_percent'] ?? 0);

            $quantities[$id] = ($quantities[$id] ?? 0) + $item['quantity'];

            $key = $id . '|' . $discount;
            $lines[$key] ??= ['product_id' => $id, 'discount_percent' => $discount, 'quantity' => 0];
            $lines[$key]['quantity'] += $item['quantity'];
        }

        $sale = DB::transaction(function () use ($data, $quantities, $lines) {
            // Bloquea las filas para evitar ventas simultáneas sobre el mismo stock.
            $products = Product::whereIn('id', array_keys($quantities))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];
            foreach ($quantities as $productId => $quantity) {
                $product = $products[$productId];
                if ($product->stock < $quantity) {
                    $errors["stock.$productId"] = "Stock insuficiente para '{$product->name}': disponible {$product->stock}, solicitado {$quantity}.";
                }
            }

            if ($errors) {
                abort(response()->json([
                    'message' => 'Stock insuficiente.',
                    'errors' => $errors,
                ], 422));
            }

            $sale = Sale::create(['user_id' => $data['user_id'], 'total' => 0]);

            $total = 0;
            $totalDiscount = 0;
            foreach ($lines as $line) {
                $product = $products[$line['product_id']];

                // El precio sale de la base de datos, nunca del request.
                $subtotal = round($product->price * $line['quantity'], 2);
                $discountAmount = round($subtotal * ($line['discount_percent'] / 100), 2);
                $subtotalWithDiscount = round($subtotal - $discountAmount, 2);

                $sale->details()->create([
                    'product_id' => $product->id,
                    'quantity' => $line['quantity'],
                    'price' => $product->price,
                    'subtotal' => $subtotal,
                    'discount_percent' => $line['discount_percent'],
                    'discount_amount' => $discountAmount,
                    'subtotal_with_discount' => $subtotalWithDiscount,
                ]);

                $total += $subtotalWithDiscount;
                $totalDiscount += $discountAmount;
            }

            foreach ($quantities as $productId => $quantity) {
                $products[$productId]->decrement('stock', $quantity);
            }

            $sale->update([
                'total' => round($total, 2),
                'total_discount' => round($totalDiscount, 2),
            ]);

            return $sale;
        });

        return response()->json([
            'message' => 'Venta creada correctamente',
            'sale_id' => $sale->id,
            'total' => $sale->total,
        ], 201);
    }

    public function index(): JsonResponse
    {
        $sales = Sale::with(['user:id,name,email', 'details.product:id,name,image'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'total' => $sale->total,
                'total_discount' => $sale->total_discount,
                'created_at' => $sale->created_at->toDateString(),
                'created_at_datetime' => $sale->created_at->toIso8601String(),
                'user' => $sale->user ? [
                    'id' => $sale->user->id,
                    'name' => $sale->user->name,
                    'email' => $sale->user->email,
                ] : null,
                'units' => $sale->details->sum('quantity'),
                'details' => $sale->details->map(fn ($detail) => [
                    'product' => $detail->product?->name,
                    'image' => $detail->product?->image,
                    'quantity' => $detail->quantity,
                    'price' => $detail->price,
                    'subtotal' => $detail->subtotal,
                    'discount_percent' => $detail->discount_percent,
                    'discount_amount' => $detail->discount_amount,
                    'subtotal_with_discount' => $detail->subtotal_with_discount,
                ]),
            ]);

        return response()->json($sales, 200);
    }

    public function destroy(string $id): JsonResponse
    {
        // Un id que no es un número (por ejemplo "abc") también es "no encontrada", no un error del servidor.
        $sale = ctype_digit($id) ? Sale::find((int) $id) : null;

        if (! $sale) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        $sale->delete();

        return response()->json(['message' => 'Venta eliminada correctamente'], 200);
    }
}
