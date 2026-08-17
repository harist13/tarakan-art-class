<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\InvoiceWhatsApp;
use App\Support\MidtransSnap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Halaman pembayaran untuk orang tua (tanpa login) + webhook Midtrans.
 *
 * Tautannya dikirim admin lewat tombol WhatsApp di Daftar Transaksi Pembayaran.
 * Karena diakses publik, satu-satunya kunci adalah `pay_token` yang acak — di
 * halaman ini tidak ada data murid lain selain yang tertagih di invoice itu.
 */
class PaymentLinkController extends Controller
{
    public function show(string $token, MidtransSnap $snap)
    {
        $payment = Payment::with('student')->where('pay_token', $token)->firstOrFail();

        // Sudah lunas (dibayar online maupun dicatat manual oleh admin):
        // halamannya berubah jadi tanda terima, tidak lagi memanggil Midtrans.
        if ($payment->payment_status === 'paid') {
            return view('public.pay', [
                'payment' => $payment,
                'state' => 'paid',
                // Tombol kirim bukti ke admin studio.
                'adminWaUrl' => InvoiceWhatsApp::receiptLink($payment),
            ]);
        }

        if (! $snap->isConfigured()) {
            return view('public.pay', ['payment' => $payment, 'state' => 'unavailable']);
        }

        try {
            $transaction = $snap->transactionFor($payment);
        } catch (RuntimeException $e) {
            Log::error('Snap gagal dibuat untuk '.$payment->invoice_number.': '.$e->getMessage());

            return view('public.pay', ['payment' => $payment, 'state' => 'error']);
        }

        return view('public.pay', [
            'payment' => $payment,
            'state' => 'payable',
            'snapToken' => $transaction['token'],
            'redirectUrl' => $transaction['redirect_url'],
            'clientKey' => $snap->clientKey(),
            'snapJsUrl' => $snap->snapJsUrl(),
        ]);
    }

    /**
     * Dipanggil halaman /bayar begitu popup Snap melaporkan pembayaran berhasil.
     *
     * Kata "berhasil" dari peramban TIDAK dipercaya: yang dilakukan di sini
     * adalah menanyakan ulang statusnya ke Midtrans dari sisi server (Core API,
     * pakai server key), lalu menerapkan jawaban Midtrans — sama persis dengan
     * yang dilakukan webhook.
     *
     * Gunanya: status invoice tetap ikut terbarui walau Payment Notification URL
     * belum terpasang atau server tidak dapat dijangkau Midtrans (mis. berjalan
     * di localhost). Ini PELENGKAP webhook, bukan pengganti — kalau orang tua
     * menutup tab sebelum membayar VA, hanya webhook yang bisa mengabarkan.
     */
    public function verify(string $token, MidtransSnap $snap): JsonResponse
    {
        $payment = Payment::where('pay_token', $token)->firstOrFail();

        if ($payment->payment_status === 'paid') {
            return response()->json(['paid' => true]);
        }

        if (! $snap->isConfigured() || ! $payment->snap_order_id) {
            return response()->json(['paid' => false]);
        }

        $status = $snap->statusFor($payment);

        if (empty($status['transaction_status'])) {
            return response()->json(['paid' => false]);
        }

        $settled = DB::transaction(fn () => $snap->applyStatus($payment, $status));

        if ($settled) {
            Log::info("Invoice {$payment->invoice_number} lunas via Midtrans ({$status['payment_type']}) — diverifikasi dari halaman bayar.");
        }

        return response()->json(['paid' => $settled]);
    }

    /**
     * Webhook "Payment Notification URL" Midtrans.
     *
     * Ini SATU-SATUNYA sumber kebenaran status pembayaran online: callback di
     * peramban bisa saja tidak pernah terjadi (orang tua menutup tab setelah
     * bayar), sedangkan webhook tetap dikirim ulang oleh Midtrans.
     */
    public function notification(Request $request, MidtransSnap $snap): JsonResponse
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? null;

        // Setiap notifikasi yang masuk dicatat lebih dulu. Tanpa ini, notifikasi
        // yang ditolak diam-diam tidak meninggalkan jejak sama sekali dan tidak
        // mungkin dibedakan dari notifikasi yang memang tidak pernah tiba.
        Log::info('Notifikasi Midtrans diterima.', [
            'order_id' => $orderId,
            'transaction_status' => $payload['transaction_status'] ?? null,
            'payment_type' => $payload['payment_type'] ?? null,
        ]);

        if (! $snap->isConfigured() || ! $snap->signatureIsValid($payload)) {
            Log::warning('Notifikasi Midtrans ditolak: signature tidak cocok.', ['order_id' => $orderId]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payment = Payment::where('snap_order_id', $orderId)->first();

        if (! $payment) {
            // Dijawab 200, BUKAN 404. Midtrans menganggap jawaban selain 2xx
            // sebagai kegagalan lalu mengirim ulang berkali-kali — padahal
            // order yang tidak kita kenal tidak akan pernah bisa dicocokkan.
            // Tombol "Tes URL notifikasi" di dashboard juga memakai order_id
            // dummy; menjawabnya 404 membuat tesnya selalu dilaporkan gagal.
            Log::info("Notifikasi Midtrans untuk order tak dikenal: {$orderId} (diabaikan).");

            return response()->json(['message' => 'Order not found, ignored']);
        }

        // Midtrans mengirim notifikasi yang sama berkali-kali sampai dijawab 200.
        // Invoice yang sudah lunas cukup diakui, jangan dicatat dua kali.
        if ($payment->payment_status === 'paid') {
            return response()->json(['message' => 'Already settled']);
        }

        $settled = DB::transaction(fn () => $snap->applyStatus($payment, $payload));

        if ($settled) {
            Log::info("Invoice {$payment->invoice_number} lunas via Midtrans ({$payload['payment_type']}).");

            return response()->json(['message' => 'Settled']);
        }

        return response()->json(['message' => 'Acknowledged']);
    }
}
