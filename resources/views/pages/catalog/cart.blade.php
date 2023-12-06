<?php

use function Livewire\Volt\{state, rules, on};
use App\Models\Cart;
use App\Models\Order;
use App\Models\Item;

state(['carts' => fn() => Cart::where('user_id', auth()->id())->get()]);

on([
    'cart-updated' => function () {
        $this->cart = $this->carts;
        $this->subTotal = $this->carts->sum(function ($item) {
            return $item->product->price * $item->qty;
        });
    },
]);

$calculateTotal = function () {
    $total = 0;
    foreach ($this->carts as $cart) {
        $total += $cart->product->price * $cart->qty;
    }
    return $total;
};

$increaseQty = function ($cartId) {
    $cart = Cart::find($cartId);
    if ($cart->qty < $cart->product->quantity) {
        $cart->update(['qty' => $cart->qty + 1]);
        $this->dispatch('cart-updated');
    }
};

$decreaseQty = function ($cartId) {
    $cart = Cart::find($cartId);
    if ($cart->qty > 1) {
        $cart->update(['qty' => $cart->qty - 1]);
        $this->dispatch('cart-updated');
    }
};

$confirmCheckout = function () {
    // Ambil item keranjang pengguna
    $cartItems = Cart::where('user_id', auth()->id())->get();

    // Buat record pesanan
    $order = Order::create([
        'user_id' => auth()->id(),
        'status' => 'unpaid', // Atur status pesanan sesuai kebutuhan
        'invoice' => 'INV-' . time(), // Atur nomor invoice, bisa disesuaikan sesuai kebutuhan
        'total' => 0, // Nantinya akan dihitung berdasarkan order_items
        'resi' => null, // Nomor resi pengiriman, bisa diisi nanti setelah pengiriman
        'ongkir' => null, // Biaya pengiriman, bisa dihitung atau diatur sesuai kebijakan
        'payment' => null, // Status pembayaran, bisa diatur nanti setelah pembayaran
    ]);

    // Inisialisasi total harga pesanan
    $totalPrice = 0;

    // Salin item dari keranjang ke tabel order_items
    foreach ($cartItems as $cartItem) {
        $orderItem = new Item([
            'product_id' => $cartItem->product_id,
            'qty' => $cartItem->qty,
        ]);

        // Tambahkan item ke pesanan
        $order->items()->save($orderItem);

        // Hitung total harga pesanan
        $totalPrice += $cartItem->product->price * $cartItem->qty;

        // Kurangkan kuantitas produk dari stok
        $cartItem->product->decrement('quantity', $cartItem->qty);
    }

    // Update total harga pesanan
    $order->update([
        'total' => $totalPrice + $order->ongkir, // Total harga pesanan ditambah ongkir
    ]);

    // Hapus item keranjang setelah checkout
    Cart::where('user_id', auth()->id())->delete();

    // ... lainnya sesuai kebutuhan ...
    $this->dispatch('cart-updated');

    $this->redirect('/orders/' . $order->id, navigate: true);
    // Ganti dengan route yang sesuai, dan kirimkan ID pesanan ke halaman konfirmasi
};

?>

<x-costumer-layout>
    @volt
        <div>
            <div class="pt-6 card">
                <div class="text-sm breadcrumbs">
                    <ul class="px-4 sm:px-6 lg:px-8">
                        <li><a wire:navigate href="/catalog/list">Katalog Produk</a></li>
                        <li><a wire:navigate href="/catalog/cart">Keranjang</a></li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="rounded overflow-x-auto">
                        <table class="table text-center border border-2">
                            <!-- head -->
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>#</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($carts as $no => $cart)
                                    <tr>
                                        <td>{{ ++$no }}.</td>
                                        <td>{{ Str::limit($cart->product->title, 50, '...') }}</td>
                                        <td>
                                            <div class="join gap-4 flex justify-center items-center">
                                                <button wire:loading.attr='disabled'
                                                    wire:click="increaseQty('{{ $cart->id }}')"
                                                    class="btn btn-xs join-item font-bold">+</button>
                                                <span class="join-item">{{ $cart->qty }}</span>
                                                <button wire:loading.attr='disabled'
                                                    wire:click="decreaseQty('{{ $cart->id }}')"
                                                    class="btn btn-xs join-item font-bold">-</button>
                                            </div>

                                        </td>
                                        <td class="w-1/6">Rp. {{ $cart->qty * $cart->product->price }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td>Total: </td>
                                    <td>Rp. {{ $this->calculateTotal() }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="join gap-3">
                        <button class="join-item btn btn-outline btn-sm">Button</button>

                        <form wire:submit="confirmCheckout">
                            <button wire:loading.attr='disable' type="submit" class="join-item btn btn-outline btn-sm">
                                <span wire:loading.delay wire:loading wire:target="confirmCheckout"
                                    class="loading loading-spinner"></span>
                                Checkout</button>
                        </form>
                    </div>
                    <x-action-message class="me-3" on="cart-updated">
                        {{ __('success!') }}
                    </x-action-message>
                </div>
            </div>
        </div>
    @endvolt
</x-costumer-layout>
