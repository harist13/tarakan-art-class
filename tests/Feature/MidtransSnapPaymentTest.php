<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\InvoiceWhatsApp;
use App\Support\MidtransSnap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA: pembayaran online Midtrans Snap (F6).
 *
 * Seluruh panggilan ke Midtrans dipalsukan — yang diuji adalah alur kita:
 * pembuatan transaksi saat tautan dibuka, keamanan webhook, dan sinkronisasi
 * status ke Laporan Keuangan lewat PaymentObserver.
 */
class MidtransSnapPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-TEST';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'midtrans.server_key' => self::SERVER_KEY,
            'midtrans.client_key' => 'SB-Mid-client-TEST',
            'midtrans.is_production' => false,
        ]);
    }

    private function fakeSnap(string $token = 'snap-token-1'): void
    {
        Http::fake([
            '*/snap/v1/transactions' => Http::response([
                'token' => $token,
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v3/redirection/'.$token,
            ]),
        ]);
    }

    private function makeUser(): User
    {
        Role::firstOrCreate(['name' => 'super_admin']);
        $user = User::create([
            'full_name' => 'Admin QA',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function makePayment(array $overrides = []): Payment
    {
        $student = Student::create([
            'name' => 'Budi Santoso',
            'date_of_birth' => '2018-05-10',
            'parent_name' => 'Ibu Ani',
            'phone_number' => '081234567890',
            'class_type' => 'drawing',
            'status' => 'active',
            'join_date' => now()->toDateString(),
        ]);

        return Payment::create(array_merge([
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'payment_amount' => 250000,
            'payment_method' => 'transfer',
            'payment_status' => 'unpaid',
        ], $overrides));
    }

    /** Notifikasi seperti yang dikirim Midtrans, lengkap dengan signature sah. */
    private function notification(Payment $payment, string $status, array $overrides = []): array
    {
        $gross = number_format((float) $payment->payment_amount, 2, '.', '');
        $statusCode = $status === 'settlement' ? '200' : '202';

        return array_merge([
            'order_id' => $payment->snap_order_id,
            'status_code' => $statusCode,
            'gross_amount' => $gross,
            'transaction_status' => $status,
            'payment_type' => 'qris',
            'signature_key' => hash('sha512', $payment->snap_order_id.$statusCode.$gross.self::SERVER_KEY),
        ], $overrides);
    }

    public function test_pesan_whatsapp_menyertakan_tautan_bayar(): void
    {
        $payment = $this->makePayment();

        $target = urldecode($this->actingAs($this->makeUser())
            ->get(route('payments.whatsapp', $payment))
            ->headers->get('Location'));

        $this->assertStringContainsString($payment->payUrl(), $target);
    }

    public function test_invoice_lunas_dikirim_tanpa_tautan_bayar(): void
    {
        $payment = $this->makePayment(['payment_status' => 'paid']);

        $target = urldecode($this->actingAs($this->makeUser())
            ->get(route('payments.whatsapp', $payment))
            ->headers->get('Location'));

        $this->assertStringNotContainsString('/bayar/', $target);
    }

    public function test_halaman_bayar_membuat_transaksi_snap(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();

        $this->get(route('pay.show', $payment->pay_token))
            ->assertOk()
            ->assertSee('Bayar Sekarang')
            ->assertSee('Rp 250.000')
            ->assertSee('snap-token-1', false);

        $payment->refresh();
        $orderId = $payment->snap_order_id;
        $this->assertMatchesRegularExpression(
            '/^'.$payment->invoice_number.'-'.$payment->id.'-[a-z0-9]{4}-1$/',
            $orderId
        );
        $this->assertSame('snap-token-1', $payment->snap_token);
        $this->assertSame('pending', $payment->gateway_status);

        Http::assertSent(fn ($request) => $request['transaction_details']['gross_amount'] === 250000
            && $request['transaction_details']['order_id'] === $orderId);
    }

    public function test_popup_snap_hanya_menawarkan_virtual_account(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();

        $this->get(route('pay.show', $payment->pay_token))->assertOk();

        Http::assertSent(function ($request) {
            $channels = $request['enabled_payments'] ?? [];

            $this->assertContains('bca_va', $channels);
            $this->assertEmpty(array_intersect(
                ['qris', 'other_qris', 'gopay', 'shopeepay', 'dana', 'credit_card',
                    'indomaret', 'alfamart'],
                $channels
            ));

            return true;
        });
    }

    public function test_daftar_channel_kosong_mengembalikan_seluruh_pilihan_midtrans(): void
    {
        config(['midtrans.enabled_payments' => []]);
        $this->fakeSnap();
        $payment = $this->makePayment();

        $this->get(route('pay.show', $payment->pay_token))->assertOk();

        // Tanpa daftar putih, kuncinya tidak boleh ikut terkirim — Snap
        // menganggap array kosong sebagai "tidak ada channel yang boleh dipakai".
        Http::assertSent(fn ($request) => ! array_key_exists('enabled_payments', $request->data()));
    }

    public function test_token_snap_dipakai_ulang_selama_belum_kedaluwarsa(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();

        $this->get(route('pay.show', $payment->pay_token))->assertOk();
        $this->get(route('pay.show', $payment->pay_token))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_revisi_nominal_membuang_token_lama_dan_membuat_order_id_baru(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token))->assertOk();

        $token = explode('-', $payment->fresh()->snap_order_id)[2];

        $payment->update(['payment_amount' => 400000]);
        $this->assertNull($payment->fresh()->snap_token);

        $this->fakeSnap('snap-token-2');
        $this->get(route('pay.show', $payment->pay_token))->assertOk();

        // Token acaknya dipertahankan, hanya angka percobaannya yang naik.
        $this->assertMatchesRegularExpression(
            '/^'.$payment->invoice_number.'-'.$payment->id.'-'.$token.'-2$/',
            $payment->fresh()->snap_order_id
        );
    }

    /**
     * migrate:fresh mengembalikan auto-increment ke 1, jadi invoice dengan nomor
     * DAN id yang sama persis bisa lahir lagi di database baru — sementara
     * Midtrans masih mengingat order_id dari database lama dan menolaknya.
     */
    public function test_order_id_tidak_terulang_walau_nomor_dan_id_invoice_sama(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token))->assertOk();
        $orderLama = $payment->fresh()->snap_order_id;

        // Meniru database yang di-reset: baris yang sama muncul lagi tanpa
        // jejak transaksi Snap sebelumnya.
        $payment->forceFill([
            'snap_order_id' => null,
            'snap_token' => null,
            'snap_expires_at' => null,
        ])->save();

        $this->fakeSnap('snap-token-2');
        $this->get(route('pay.show', $payment->pay_token))->assertOk();

        $this->assertNotSame($orderLama, $payment->fresh()->snap_order_id);
    }

    /**
     * Nomor invoice bisa terpakai ulang setelah invoice lama dihapus. Kalau
     * order_id ikut terulang, Midtrans menolaknya dengan "order_id sudah
     * digunakan" dan orang tua melihat halaman error alih-alih tombol bayar.
     */
    public function test_invoice_pengganti_dengan_nomor_sama_memakai_order_id_berbeda(): void
    {
        $this->fakeSnap();
        $lama = $this->makePayment();
        $this->get(route('pay.show', $lama->pay_token))->assertOk();
        $orderLama = $lama->fresh()->snap_order_id;

        // Invoice dihapus, lalu dibuat lagi dengan nomor yang sama persis.
        $nomor = $lama->invoice_number;
        $lama->delete();
        $baru = $this->makePayment(['invoice_number' => $nomor]);

        $this->fakeSnap('snap-token-2');
        $this->get(route('pay.show', $baru->pay_token))->assertOk();

        $this->assertSame($nomor, $baru->invoice_number);
        $this->assertNotSame($orderLama, $baru->fresh()->snap_order_id);
    }

    public function test_invoice_lunas_menampilkan_tanda_terima_tanpa_memanggil_midtrans(): void
    {
        Http::fake();
        $payment = $this->makePayment(['payment_status' => 'paid']);

        $this->get(route('pay.show', $payment->pay_token))
            ->assertOk()
            ->assertSee('Pembayaran Diterima')
            ->assertDontSee('Bayar Sekarang');

        Http::assertNothingSent();
    }

    /**
     * Tanda terima menyediakan jalan pintas bagi orang tua untuk mengabari admin,
     * lengkap dengan rincian invoice — tanpa perlu mengetik ulang apa pun.
     */
    public function test_tanda_terima_menyediakan_tombol_kirim_bukti_ke_admin(): void
    {
        Http::fake();
        config(['site.contact.whatsapp' => '6281234567890']);

        $payment = $this->makePayment([
            'payment_status' => 'paid',
            'payment_method' => 'qris',
            'paid_at' => now(),
        ]);

        $halaman = $this->get(route('pay.show', $payment->pay_token))->assertOk();

        $halaman->assertSee('Kirim bukti ke Admin');
        $halaman->assertSee('https://wa.me/6281234567890', false);

        $pesan = urldecode(InvoiceWhatsApp::receiptLink($payment));
        $this->assertStringContainsString($payment->invoice_number, $pesan);
        $this->assertStringContainsString('Budi Santoso', $pesan);
        $this->assertStringContainsString('Rp 250.000', $pesan);
        $this->assertStringContainsString('QRIS', $pesan);
        $this->assertStringContainsString($payment->payUrl(), $pesan);
    }

    public function test_tombol_kirim_bukti_disembunyikan_bila_nomor_studio_kosong(): void
    {
        Http::fake();
        config(['site.contact.whatsapp' => '']);

        $payment = $this->makePayment(['payment_status' => 'paid']);

        $this->get(route('pay.show', $payment->pay_token))
            ->assertOk()
            ->assertDontSee('Kirim bukti ke Admin');
    }

    public function test_tautan_bayar_yang_tidak_dikenal_menghasilkan_404(): void
    {
        $this->get(route('pay.show', 'token-palsu'))->assertNotFound();
    }

    public function test_notifikasi_settlement_melunasi_invoice_dan_mencatat_pemasukan(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));
        $payment->refresh();

        $this->postJson(route('midtrans.notification'), $this->notification($payment, 'settlement'))
            ->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->payment_status);
        $this->assertSame('qris', $payment->payment_method);
        $this->assertNotNull($payment->paid_at);

        // Sinkron ke Laporan Keuangan lewat PaymentObserver.
        $this->assertDatabaseHas('transactions', [
            'payment_id' => $payment->id,
            'type' => 'income',
            'amount' => 250000,
        ]);
    }

    /**
     * Jalur yang menyelamatkan kasus "Snap bilang sukses tapi status masih
     * Unpaid" ketika webhook tidak dapat menjangkau server.
     */
    public function test_verifikasi_dari_halaman_bayar_melunasi_invoice_tanpa_webhook(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));

        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'settlement',
                'payment_type' => 'gopay',
            ]),
        ]);

        $this->postJson(route('pay.verify', $payment->pay_token))
            ->assertOk()
            ->assertJson(['paid' => true]);

        $payment->refresh();
        $this->assertSame('paid', $payment->payment_status);
        $this->assertSame('qris', $payment->payment_method);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_verifikasi_tidak_melunasi_bila_midtrans_bilang_belum_dibayar(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));

        // Peramban boleh saja mengaku sukses; yang menentukan tetap Midtrans.
        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'pending',
                'payment_type' => 'bank_transfer',
            ]),
        ]);

        $this->postJson(route('pay.verify', $payment->pay_token))
            ->assertOk()
            ->assertJson(['paid' => false]);

        $this->assertSame('unpaid', $payment->fresh()->payment_status);
        $this->assertDatabaseCount('transactions', 0);
    }

    /**
     * Tombol "Tes URL notifikasi" di dashboard Midtrans mengirim order_id dummy.
     * Menjawabnya 404 membuat tesnya dilaporkan gagal, dan notifikasi sungguhan
     * untuk order tak dikenal akan dikirim ulang terus-menerus.
     */
    public function test_notifikasi_untuk_order_tak_dikenal_dijawab_200(): void
    {
        $gross = '10000.00';
        $orderId = 'payment_notif_test_G123456_'.uniqid();

        $this->postJson(route('midtrans.notification'), [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $gross,
            'transaction_status' => 'settlement',
            'payment_type' => 'bank_transfer',
            'signature_key' => hash('sha512', $orderId.'200'.$gross.self::SERVER_KEY),
        ])->assertOk();
    }

    public function test_notifikasi_dengan_signature_palsu_ditolak(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));
        $payment->refresh();

        $this->postJson(route('midtrans.notification'), $this->notification($payment, 'settlement', [
            'signature_key' => 'jelas-palsu',
        ]))->assertForbidden();

        $this->assertSame('unpaid', $payment->fresh()->payment_status);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_notifikasi_expire_membuang_token_agar_tautan_bisa_dipakai_lagi(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));
        $payment->refresh();

        $this->postJson(route('midtrans.notification'), $this->notification($payment, 'expire'))->assertOk();

        $payment->refresh();
        $this->assertSame('unpaid', $payment->payment_status);
        $this->assertSame('expire', $payment->gateway_status);
        $this->assertNull($payment->snap_token);
    }

    public function test_notifikasi_ganda_tidak_mencatat_pemasukan_dua_kali(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));
        $payment->refresh();

        $notification = $this->notification($payment, 'settlement');
        $this->postJson(route('midtrans.notification'), $notification)->assertOk();
        $this->postJson(route('midtrans.notification'), $notification)->assertOk();

        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_cek_status_manual_melunasi_invoice_saat_webhook_tidak_sampai(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));
        $payment->refresh();

        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'settlement',
                'payment_type' => 'bank_transfer',
            ]),
        ]);

        $this->actingAs($this->makeUser())
            ->patch(route('payments.sync-gateway', $payment))
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame('paid', $payment->payment_status);
        $this->assertSame('virtual_account', $payment->payment_method);
        $this->assertDatabaseCount('transactions', 1);
    }

    /**
     * Channel apa pun harus masuk ke salah satu dari empat kategori laporan,
     * dan nama channel aslinya tetap tersimpan untuk audit.
     */
    public function test_channel_midtrans_dipetakan_ke_kategori_laporan(): void
    {
        $snap = app(MidtransSnap::class);

        $this->assertSame('qris', $snap->methodFor('qris'));
        $this->assertSame('qris', $snap->methodFor('other_qris'));
        // QRIS & seluruh dompet digital dilaporkan sebagai satu kategori.
        $this->assertSame('qris', $snap->methodFor('dana'));
        $this->assertSame('qris', $snap->methodFor('gopay'));
        $this->assertSame('qris', $snap->methodFor('shopeepay'));
        $this->assertSame('virtual_account', $snap->methodFor('bank_transfer'));
        $this->assertSame('virtual_account', $snap->methodFor('echannel'));
        $this->assertSame('cash', $snap->methodFor('cstore'));
        $this->assertSame('transfer', $snap->methodFor('credit_card'));
        // Channel yang belum pernah ada pun tidak boleh membuat penyimpanan gagal.
        $this->assertSame('transfer', $snap->methodFor('channel_baru_2027'));
    }

    public function test_pembayaran_dana_tercatat_sebagai_qris_dengan_jejak_channel_asli(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));
        $payment->refresh();

        $this->postJson(route('midtrans.notification'), $this->notification($payment, 'settlement', [
            'payment_type' => 'dana',
        ]))->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->payment_status);
        $this->assertSame('qris', $payment->payment_method);
        $this->assertSame('dana', $payment->gateway_payment_type);
        // Satu kategori, satu label — di layar & pesan WhatsApp keduanya sama.
        $this->assertSame('QRIS / E-Wallet', $payment->methodLabel());
    }

    /**
     * transaction_id dari notifikasi disimpan, lalu dipakai sebagai kunci
     * pengecekan berikutnya — pencarian lewat order_id tidak selalu menemukan
     * transaksi e-wallet.
     */
    public function test_transaction_id_disimpan_dan_dipakai_untuk_cek_status(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));
        $payment->refresh();

        // Notifikasi "pending" sudah membawa transaction_id.
        $this->postJson(route('midtrans.notification'), $this->notification($payment, 'pending', [
            'payment_type' => 'gopay',
            'transaction_id' => 'trx-abc-123',
        ]))->assertOk();

        $this->assertSame('trx-abc-123', $payment->fresh()->gateway_transaction_id);

        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'settlement',
                'payment_type' => 'gopay',
            ]),
        ]);

        $this->actingAs($this->makeUser())
            ->patch(route('payments.sync-gateway', $payment))
            ->assertRedirect();

        // Pengecekan memakai transaction_id, bukan order_id.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'trx-abc-123'));
        $this->assertSame('paid', $payment->fresh()->payment_status);
    }

    public function test_cek_status_menjelaskan_bila_midtrans_tidak_punya_transaksinya(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));

        Http::fake([
            '*/v2/*/status' => Http::response([
                'status_code' => '404',
                'status_message' => "Transaction doesn't exist.",
            ]),
        ]);

        $this->actingAs($this->makeUser())
            ->patch(route('payments.sync-gateway', $payment))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($pesan) => str_contains($pesan, "Transaction doesn't exist")
                && str_contains($pesan, 'Notification URL'));
    }

    public function test_daftar_pembayaran_memperingatkan_webhook_tak_terjangkau(): void
    {
        Http::fake();
        config(['app.url' => 'http://localhost']);
        $this->makePayment();

        $this->actingAs($this->makeUser())
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Notifikasi pelunasan Midtrans tidak akan sampai');
    }

    public function test_peringatan_hilang_saat_app_url_sudah_publik(): void
    {
        Http::fake();
        config(['app.url' => 'https://tarakanartclass.com']);
        $this->makePayment();

        $this->actingAs($this->makeUser())
            ->get(route('payments.index'))
            ->assertOk()
            ->assertDontSee('Notifikasi pelunasan Midtrans tidak akan sampai');
    }

    /**
     * Domain pengembangan lokal gagal diam-diam: Midtrans tidak pernah bisa
     * menyambung, dan tidak ada pesan error yang muncul di mana pun.
     */
    public function test_alamat_lokal_dikenali_sebagai_tak_terjangkau(): void
    {
        $snap = app(MidtransSnap::class);

        foreach ([
            'http://tarakan-art-class.test:8080',   // Laragon / Valet
            'http://localhost',
            'http://127.0.0.1:8000',
            'http://app.local',
            'http://192.168.1.10',                  // IP jaringan dalam
            'http://10.0.0.5',
        ] as $url) {
            config(['app.url' => $url]);
            $this->assertFalse($snap->webhookReachable(), "{$url} seharusnya dianggap tak terjangkau");
        }

        foreach ([
            'https://tarakanartclass.com',
            'https://abc123.ngrok.app',
            'https://tarakan.my.id',
        ] as $url) {
            config(['app.url' => $url]);
            $this->assertTrue($snap->webhookReachable(), "{$url} seharusnya dianggap terjangkau");
        }
    }

    /**
     * Bukti pembayaran hanya untuk invoice lunas: membuka tautan invoice yang
     * belum lunas akan menerbitkan transaksi Snap atas nama admin, sehingga
     * invoicenya tampak "menunggu bayar" padahal tidak ada yang membayar.
     */
    public function test_tombol_bukti_pembayaran_hanya_untuk_invoice_lunas(): void
    {
        Http::fake();
        $lunas = $this->makePayment(['payment_status' => 'paid']);
        $belum = $this->makePayment();

        $halaman = $this->actingAs($this->makeUser())->get(route('payments.index'))->assertOk();

        $halaman->assertSee('bi-receipt-cutoff', false);
        $halaman->assertSee($lunas->payUrl(), false);

        $this->assertStringNotContainsString(
            'href="'.$belum->payUrl().'"',
            $halaman->getContent()
        );
    }

    public function test_daftar_pembayaran_menampilkan_tombol_salin_tautan_bayar(): void
    {
        Http::fake();
        $payment = $this->makePayment();

        $this->actingAs($this->makeUser())
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee($payment->payUrl(), false);

        // Halaman daftar tidak boleh memanggil Midtrans sama sekali.
        Http::assertNothingSent();
    }

    /**
     * Webhook bisa tiba saat halaman daftar sudah terbuka — tombol cek status
     * masih tampil di layar admin, padahal invoicenya sudah lunas. Menekannya
     * tidak boleh melaporkan "belum lunas".
     */
    public function test_cek_status_pada_invoice_yang_sudah_lunas_tidak_melaporkan_belum_lunas(): void
    {
        $this->fakeSnap();
        $payment = $this->makePayment();
        $this->get(route('pay.show', $payment->pay_token));
        $payment->refresh();

        // Webhook mendahului: invoice sudah lunas sebelum admin menekan tombol.
        $this->postJson(route('midtrans.notification'), $this->notification($payment, 'settlement'))->assertOk();

        Http::fake([
            '*/v2/*/status' => Http::response([
                'transaction_status' => 'settlement',
                'payment_type' => 'qris',
            ]),
        ]);

        $this->actingAs($this->makeUser())
            ->patch(route('payments.sync-gateway', $payment))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('error')
            ->assertSessionHas('success', fn ($pesan) => str_contains($pesan, 'sudah LUNAS'));

        // Pemasukannya tetap satu — pengecekan ulang tidak mencatat ganda.
        $this->assertDatabaseCount('transactions', 1);
    }

    /**
     * Konfirmasi lunas manual hanya untuk pembayaran tunai. Menekannya pada
     * invoice channel gateway berarti mencatat pemasukan atas uang yang belum
     * tentu masuk — dan invoice yang sudah lunas tidak pernah dicek ulang.
     */
    public function test_konfirmasi_lunas_manual_ditolak_untuk_channel_gateway(): void
    {
        Http::fake();
        $payment = $this->makePayment(['payment_method' => 'qris']);

        $this->actingAs($this->makeUser())
            ->patch(route('payments.confirm', $payment))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('unpaid', $payment->fresh()->payment_status);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_konfirmasi_lunas_manual_diterima_untuk_pembayaran_tunai(): void
    {
        Http::fake();
        $payment = $this->makePayment(['payment_method' => 'cash']);

        $this->actingAs($this->makeUser())
            ->patch(route('payments.confirm', $payment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('paid', $payment->fresh()->payment_status);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_tombol_lunas_dimatikan_untuk_invoice_non_tunai(): void
    {
        Http::fake();
        $tunai = $this->makePayment(['payment_method' => 'cash']);
        $gateway = $this->makePayment(['payment_method' => 'virtual_account']);

        $halaman = $this->actingAs($this->makeUser())->get(route('payments.index'))->assertOk();

        // Yang tunai punya form konfirmasi; yang gateway tidak.
        $halaman->assertSee(route('payments.confirm', $tunai), false);
        $halaman->assertDontSee(route('payments.confirm', $gateway), false);
        $halaman->assertSee('Hanya pembayaran Cash yang bisa dilunaskan manual', false);
    }

    public function test_tanpa_kunci_midtrans_halaman_bayar_tetap_terbuka_tanpa_gateway(): void
    {
        config(['midtrans.server_key' => null]);
        Http::fake();
        $payment = $this->makePayment();

        $this->get(route('pay.show', $payment->pay_token))
            ->assertOk()
            ->assertSee('Pembayaran online belum aktif');

        Http::assertNothingSent();
    }
}
