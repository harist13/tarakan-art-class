<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\PaymentBundle;
use App\Models\Student;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pembungkus tipis API Midtrans Snap (tanpa SDK — cukup HTTP client Laravel).
 *
 * Alurnya: admin mengirim tautan /bayar/{pay_token} lewat WhatsApp, orang tua
 * membukanya, DI SAAT ITULAH transaksi Snap dibuat. Token Snap tidak dibuat
 * lebih awal karena masa berlakunya terbatas — tautan yang dibuka tiga hari
 * setelah dikirim tetap harus bisa dipakai.
 */
class MidtransSnap
{
    /** Belum dikonfigurasi = fitur mati total, aplikasi kembali ke konfirmasi manual. */
    public function isConfigured(): bool
    {
        return ! empty(config('midtrans.server_key'));
    }

    public function clientKey(): ?string
    {
        return config('midtrans.client_key');
    }

    public function isProduction(): bool
    {
        return (bool) config('midtrans.is_production');
    }

    public function snapJsUrl(): string
    {
        return $this->endpoint('snap_js');
    }

    /**
     * Apakah alamat notifikasi kita dapat dijangkau server Midtrans?
     *
     * Notifikasi dikirim dari internet, jadi APP_URL harus menunjuk ke alamat
     * publik. Alamat pengembangan lokal gagal diam-diam: Midtrans tidak pernah
     * berhasil menyambung, sementara di layar admin pembayaran terlihat sukses
     * tapi invoicenya tak kunjung lunas — gejala yang sulit ditebak asalnya.
     */
    public function webhookReachable(): bool
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        // Akhiran yang tidak pernah ada di DNS publik: .test & .localhost
        // dicadangkan RFC 6761 (dipakai Laragon/Valet), .local milik mDNS.
        foreach (['.test', '.localhost', '.local', '.invalid', '.example', '.internal', '.home', '.lan'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        // Alamat IP privat/khusus hanya berlaku di dalam jaringan sendiri.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return true;
    }

    /**
     * Transaksi Snap yang siap dibayar untuk sebuah invoice.
     *
     * Token lama dipakai ulang selama belum kedaluwarsa DAN nominalnya belum
     * berubah — order_id yang sama tidak boleh dikirim dua kali ke Midtrans.
     * Begitu invoice direvisi, transaksi baru dibuat dengan order_id berikutnya.
     *
     * @return array{token: string, redirect_url: string}
     */
    public function transactionFor(Payment $payment): array
    {
        if ($this->reusableToken($payment)) {
            return [
                'token' => $payment->snap_token,
                'redirect_url' => $payment->snap_redirect_url,
            ];
        }

        $orderId = $this->nextOrderId($payment);
        $result = $this->createTransaction($payment, $orderId);

        $payment->forceFill([
            'snap_order_id' => $orderId,
            'snap_token' => $result['token'],
            'snap_redirect_url' => $result['redirect_url'],
            'snap_expires_at' => now()->addHours((int) config('midtrans.expiry_hours', 24)),
            'gateway_status' => 'pending',
        ])->save();

        // Jejak untuk menelusuri "popup bilang sukses tapi invoice tetap Unpaid":
        // order_id di sini harus sama dengan yang muncul di Dashboard Midtrans.
        Log::info("Snap dibuat untuk {$payment->invoice_number}: order_id={$orderId}, nominal=".
            (int) round((float) $payment->payment_amount));

        return $result;
    }

    /**
     * Token masih bisa dipakai ulang? Diberi jeda 5 menit sebelum kedaluwarsa
     * supaya orang tua tidak kebagian token yang mati di tengah pembayaran.
     * Revisi nominal invoice membuang token lama lewat hook di model Payment.
     */
    private function reusableToken(Payment $payment): bool
    {
        return $payment->snap_token !== null
            && $payment->snap_expires_at !== null
            && $payment->snap_expires_at->isAfter(now()->addMinutes(5));
    }

    /**
     * order_id untuk Midtrans: INV017-9-k3f9-1, INV017-9-k3f9-2, …
     *
     * Susunannya {nomor invoice}-{id baris}-{token acak}-{percobaan}. Midtrans
     * menuntut order_id unik SELAMANYA, sementara dua bagian pertama bisa
     * terulang dan keduanya pernah mematikan tautan bayar:
     *
     *  - Nomor invoice dipakai ulang begitu invoice terakhir dihapus, karena
     *    Payment::generateInvoiceNumber() menghitung dari baris terakhir. Id
     *    baris yang auto-increment membedakan invoice pengganti dari yang lama.
     *  - Id baris pun terulang setiap database di-reset: migrate:fresh
     *    mengembalikan auto-increment ke 1, sedangkan Midtrans tetap mengingat
     *    order_id dari database yang sudah dibuang. Tautan bayar lalu ditolak
     *    "order_id sudah digunakan" dan orang tua hanya melihat "tautan sedang
     *    tidak dapat dibuat" — kegagalan yang mustahil ditebak dari layar admin.
     *    Token acak per transaksi menutup celah itu.
     *
     * Token diambil ulang dari order_id sebelumnya supaya seluruh percobaan
     * untuk satu invoice tetap terbaca berkelompok di Dashboard Midtrans.
     */
    private function nextOrderId(Payment $payment): string
    {
        $token = strtolower(Str::random(4));
        $attempt = 1;

        if ($payment->snap_order_id && preg_match('/-([a-z0-9]{4})-(\d+)$/', $payment->snap_order_id, $m)) {
            $token = $m[1];
            $attempt = (int) $m[2] + 1;
        } elseif ($payment->snap_order_id && preg_match('/-(\d+)$/', $payment->snap_order_id, $m)) {
            // Format lama {invoice}-{id}-{percobaan}: hitungannya dilanjutkan,
            // tokennya baru — order_id-nya tetap berbeda dari yang sudah dipakai.
            $attempt = (int) $m[1] + 1;
        } elseif ($payment->snap_order_id) {
            $attempt = 2;
        }

        return $payment->invoice_number.'-'.$payment->getKey().'-'.$token.'-'.$attempt;
    }

    /**
     * @return array{token: string, redirect_url: string}
     */
    private function createTransaction(Payment $payment, string $orderId): array
    {
        return $this->requestSnap($orderId, [$payment], $payment->student, route('pay.show', $payment->payToken()));
    }

    /**
     * Minta transaksi Snap untuk sejumlah invoice — satu untuk tautan biasa,
     * beberapa untuk tagihan gabungan (satu item_details per invoice).
     *
     * @param  list<Payment>  $payments
     * @return array{token: string, redirect_url: string}
     */
    private function requestSnap(string $orderId, array $payments, ?Student $guardian, string $finishUrl): array
    {
        // Midtrans menolak gross_amount berdesimal; nominal dibulatkan ke rupiah
        // penuh dan item_details harus berjumlah sama persis dengan gross_amount.
        $items = array_map(fn (Payment $p) => [
            'id' => $p->invoice_number,
            'price' => (int) round((float) $p->payment_amount),
            'quantity' => 1,
            // Nama item dibatasi 50 karakter oleh Midtrans.
            'name' => mb_substr('Kelas seni - '.($p->student?->name ?? 'Murid'), 0, 50),
        ], $payments);

        $response = $this->request()->post($this->endpoint('snap'), [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => array_sum(array_column($items, 'price')),
            ],
            'item_details' => $items,
            'customer_details' => array_filter([
                'first_name' => mb_substr($guardian?->parent_name ?: ($guardian?->name ?? 'Wali Murid'), 0, 50),
                'phone' => $guardian?->whatsappNumber(),
            ]),
            'expiry' => [
                'unit' => 'hour',
                'duration' => (int) config('midtrans.expiry_hours', 24),
            ],
            'callbacks' => [
                'finish' => $finishUrl,
            ],
        ] + $this->channelWhitelist());

        if ($response->failed()) {
            // error_messages berisi alasan yang bisa ditindaklanjuti admin
            // (mis. "gross_amount is not equal to the sum of item_details").
            $reason = implode(' ', (array) $response->json('error_messages', [$response->status()]));

            throw new RuntimeException('Midtrans menolak permintaan pembayaran: '.$reason);
        }

        return [
            'token' => $response->json('token'),
            'redirect_url' => $response->json('redirect_url'),
        ];
    }

    // ─── Tagihan gabungan ─────────────────────────────────────────

    /**
     * Transaksi Snap untuk tagihan gabungan, atas invoice yang masih belum lunas.
     *
     * Token lama dipakai ulang hanya bila belum kedaluwarsa DAN totalnya masih
     * sama dengan saat token dibuat: invoice di dalamnya bisa dibayar terpisah
     * atau direvisi kapan saja, dan orang tua tidak boleh ditagih dengan total
     * yang sudah tidak berlaku.
     *
     * @return array{token: string, redirect_url: string}
     */
    public function bundleTransactionFor(PaymentBundle $bundle): array
    {
        $total = $bundle->totalDue();

        if ($bundle->snap_token !== null
            && $bundle->snap_amount === $total
            && $bundle->snap_expires_at?->isAfter(now()->addMinutes(5))) {
            return ['token' => $bundle->snap_token, 'redirect_url' => $bundle->snap_redirect_url];
        }

        $orderId = $this->nextBundleOrderId($bundle);
        $result = $this->requestSnap(
            $orderId,
            $bundle->unpaidPayments()->all(),
            $bundle->guardian(),
            route('pay.bundle.show', $bundle->pay_token),
        );

        $bundle->forceFill([
            'snap_order_id' => $orderId,
            'snap_token' => $result['token'],
            'snap_redirect_url' => $result['redirect_url'],
            'snap_expires_at' => now()->addHours((int) config('midtrans.expiry_hours', 24)),
            'snap_amount' => $total,
            'gateway_status' => 'pending',
        ])->save();

        Log::info("Snap dibuat untuk tagihan gabungan {$bundle->code}: order_id={$orderId}, nominal={$total}");

        return $result;
    }

    /** GAB-003-7-k3f9-1, -2, … — pola sama dengan nextOrderId(), lihat alasannya di sana. */
    private function nextBundleOrderId(PaymentBundle $bundle): string
    {
        $token = strtolower(Str::random(4));
        $attempt = 1;

        if ($bundle->snap_order_id && preg_match('/-([a-z0-9]{4})-(\d+)$/', $bundle->snap_order_id, $m)) {
            $token = $m[1];
            $attempt = (int) $m[2] + 1;
        }

        return $bundle->code.'-'.$bundle->getKey().'-'.$token.'-'.$attempt;
    }

    public function bundleStatusFor(PaymentBundle $bundle): array
    {
        return $this->status($bundle->gateway_transaction_id ?: $bundle->snap_order_id);
    }

    /**
     * Terapkan status Midtrans ke tagihan gabungan. Begitu lunas, tiap invoice
     * yang masih belum dibayar ditandai lunas lewat model — PaymentObserver
     * mencatat pemasukannya satu per satu, persis seperti pembayaran biasa.
     *
     * Mengembalikan invoice yang BARU saja lunas (kosong bila belum lunas).
     *
     * @return list<Payment>
     */
    public function applyBundleStatus(PaymentBundle $bundle, array $payload): array
    {
        $bundle->loadMissing('payments.student');

        if ($bundle->paid_at !== null) {
            return [];
        }

        if ($this->isSettled($payload)) {
            $lunas = [];

            foreach ($bundle->unpaidPayments() as $payment) {
                $payment->forceFill([
                    'payment_status' => 'paid',
                    'payment_method' => $this->methodFor($payload['payment_type'] ?? null),
                    'gateway_status' => $payload['transaction_status'],
                    'gateway_payment_type' => $payload['payment_type'] ?? null,
                    'gateway_transaction_id' => $payload['transaction_id'] ?? null,
                    'paid_at' => now(),
                ])->save();
                $lunas[] = $payment;
            }

            // Dana yang masuk mengikuti total saat transaksi dibuat. Bila sejak
            // itu ada invoice yang dibayar terpisah, orang tua membayar lebih —
            // tidak bisa dicegah di sini, tapi harus terlihat oleh admin.
            $paidNow = array_sum(array_map(fn (Payment $p) => (int) round((float) $p->payment_amount), $lunas));
            if ($bundle->snap_amount !== null && $paidNow !== $bundle->snap_amount) {
                Log::warning("Tagihan gabungan {$bundle->code} dibayar Rp {$bundle->snap_amount}, ".
                    "tapi invoice yang dilunasi hanya Rp {$paidNow} — ada invoice yang sudah dibayar terpisah.");
            }

            $bundle->forceFill([
                'gateway_status' => $payload['transaction_status'],
                'gateway_payment_type' => $payload['payment_type'] ?? null,
                'gateway_transaction_id' => $payload['transaction_id'] ?? $bundle->gateway_transaction_id,
                'paid_at' => now(),
            ])->save();

            return $lunas;
        }

        $failed = $this->isFailed($payload);

        $bundle->forceFill([
            'gateway_status' => $payload['transaction_status'] ?? 'unknown',
            'gateway_payment_type' => $payload['payment_type'] ?? $bundle->gateway_payment_type,
            'gateway_transaction_id' => $payload['transaction_id'] ?? $bundle->gateway_transaction_id,
            'snap_token' => $failed ? null : $bundle->snap_token,
            'snap_expires_at' => $failed ? null : $bundle->snap_expires_at,
        ])->save();

        return [];
    }

    /**
     * Batasi channel yang muncul di popup Snap.
     *
     * Midtrans hanya mengenal daftar putih; tidak ada cara mematikan satu
     * channel selain menyebut semua yang boleh tampil. Alasan QRIS & dompet
     * digital tidak ikut didaftarkan ada di config/midtrans.php.
     *
     * Daftar kosong = kunci ini tidak dikirim sama sekali, sehingga Snap
     * kembali menampilkan seluruh channel yang aktif di dashboard.
     *
     * @return array{enabled_payments?: list<string>}
     */
    private function channelWhitelist(): array
    {
        $channels = array_values(array_filter((array) config('midtrans.enabled_payments', [])));

        return $channels === [] ? [] : ['enabled_payments' => $channels];
    }

    /** Status terkini dari Core API — dipakai bila webhook tidak sampai. */
    public function status(string $reference): array
    {
        $result = $this->request()->get($this->endpoint('api')."/{$reference}/status")->json() ?? [];

        Log::info("Status Midtrans untuk {$reference}: ".
            ($result['transaction_status'] ?? ($result['status_message'] ?? '(kosong)')).
            ' / '.($result['payment_type'] ?? '-'));

        return $result;
    }

    /**
     * Kunci terbaik untuk menanyakan status sebuah invoice.
     *
     * transaction_id didahulukan bila sudah pernah diketahui: pencarian lewat
     * order_id tidak selalu menemukan transaksi e-wallet, sedangkan
     * transaction_id selalu menunjuk ke satu transaksi yang pasti.
     */
    public function statusFor(Payment $payment): array
    {
        return $this->status($payment->gateway_transaction_id ?: $payment->snap_order_id);
    }

    /**
     * Terapkan status dari Midtrans ke invoice. Dipakai webhook maupun tombol
     * "cek status" manual, supaya keduanya tidak pernah berbeda perlakuan.
     *
     * Mengembalikan true bila invoice ini BARU saja menjadi lunas.
     */
    public function applyStatus(Payment $payment, array $payload): bool
    {
        if ($payment->payment_status === 'paid') {
            return false;
        }

        if ($this->isSettled($payload)) {
            // PaymentObserver yang mencatat pemasukannya ke Laporan Keuangan
            // dan mencabut penangguhan murid begitu status jadi "paid".
            $payment->forceFill([
                'payment_status' => 'paid',
                'payment_method' => $this->methodFor($payload['payment_type'] ?? null),
                'gateway_status' => $payload['transaction_status'],
                'gateway_payment_type' => $payload['payment_type'] ?? null,
                'gateway_transaction_id' => $payload['transaction_id'] ?? $payment->gateway_transaction_id,
                'paid_at' => now(),
            ])->save();

            return true;
        }

        // Gagal / kedaluwarsa: token dibuang agar tautan yang sama membuat
        // transaksi baru saat dibuka lagi, bukan menampilkan token mati.
        $failed = $this->isFailed($payload);

        $payment->forceFill([
            'gateway_status' => $payload['transaction_status'] ?? 'unknown',
            'gateway_payment_type' => $payload['payment_type'] ?? $payment->gateway_payment_type,
            'gateway_transaction_id' => $payload['transaction_id'] ?? $payment->gateway_transaction_id,
            'snap_token' => $failed ? null : $payment->snap_token,
            'snap_expires_at' => $failed ? null : $payment->snap_expires_at,
        ])->save();

        return false;
    }

    /**
     * Notifikasi Midtrans hanya boleh dipercaya bila signature-nya cocok.
     * Rumus resmi: sha512(order_id + status_code + gross_amount + server_key).
     */
    public function signatureIsValid(array $payload): bool
    {
        $expected = hash('sha512',
            ($payload['order_id'] ?? '')
            .($payload['status_code'] ?? '')
            .($payload['gross_amount'] ?? '')
            .config('midtrans.server_key')
        );

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }

    /**
     * Notifikasi yang berarti "uang sudah masuk".
     *
     * `capture` (kartu kredit) hanya dihitung lunas bila fraud_status-nya
     * accept — challenge masih menunggu keputusan manual di dashboard.
     */
    public function isSettled(array $payload): bool
    {
        $status = $payload['transaction_status'] ?? null;

        return $status === 'settlement'
            || ($status === 'capture' && ($payload['fraud_status'] ?? 'accept') === 'accept');
    }

    /** Notifikasi yang berarti transaksinya batal — tautan boleh dibuat ulang. */
    public function isFailed(array $payload): bool
    {
        return in_array($payload['transaction_status'] ?? null, ['deny', 'cancel', 'expire', 'failure'], true);
    }

    /**
     * Channel Midtrans dipetakan ke kosakata payments.payment_method (cash,
     * transfer, virtual_account) supaya Laporan Keuangan tidak pecah jadi
     * puluhan kategori tiap kali Midtrans menambah channel baru. Nama channel
     * aslinya tetap utuh di kolom gateway_payment_type, jadi tidak ada
     * informasi yang hilang.
     *
     * Channel yang belum dikenal masuk "transfer" — kategori paling netral
     * untuk uang yang masuk lewat gateway. Begitu pula QRIS & dompet digital:
     * tidak lagi punya kategori sendiri, dan popup Snap memang tidak
     * menawarkannya selama daftar channel di config/midtrans.php terisi.
     */
    public function methodFor(?string $paymentType): string
    {
        return match ($paymentType) {
            // Virtual Account seluruh bank + Mandiri Bill Payment (echannel).
            'bank_transfer', 'echannel', 'permata', 'bca_va', 'bni_va',
            'bri_va', 'cimb_va', 'mandiri_va', 'other_va' => 'virtual_account',

            // Indomaret / Alfamart — orang tua menyetor tunai di gerai.
            'cstore' => 'cash',

            default => 'transfer',
        };
    }

    private function request(): PendingRequest
    {
        // Midtrans memakai Basic Auth: server key sebagai username, password kosong.
        return Http::withBasicAuth((string) config('midtrans.server_key'), '')
            ->acceptJson()
            ->timeout(20);
    }

    private function endpoint(string $key): string
    {
        $env = $this->isProduction() ? 'production' : 'sandbox';

        return config("midtrans.endpoints.{$env}.{$key}");
    }
}
