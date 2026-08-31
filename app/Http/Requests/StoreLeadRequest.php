<?php

namespace App\Http\Requests;

use App\Models\ClassRoom;
use App\Models\HolidayClass;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Dropdown kelas diisi dari tabel `classes`, dengan cadangan slug program
        // di config saat database masih kosong — keduanya diterima di sini.
        $allowedPrograms = array_merge(
            array_column(config('site.programs', []), 'slug'),
            ClassRoom::query()->distinct()->pluck('class_name')->all(),
        );

        // Semua isian wajib kecuali usia anak & pesan. Usia opsional karena sudah
        // terwakili tanggal lahir (dan terisi otomatis darinya di form).
        // Pengecualian lain: "kelas yang diminati" dilonggarkan bila tipe kelas
        // terpilih memang belum punya jadwal — sama seperti perilaku dropdown di form.
        return [
            'child_name' => ['required', 'string', 'max:100'],
            'child_age' => ['nullable', 'integer', 'min:1', 'max:99'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'class_type' => ['required', Rule::in(array_keys(Lead::CLASS_TYPES))],
            'parent_name' => ['required', 'string', 'max:100'],
            'parent_phone' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
            'parent_email' => ['required', 'email', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'program' => ['nullable', Rule::in($allowedPrograms)],
            'message' => ['nullable', 'string', 'max:1000'],
            // Honeypot: harus tetap kosong. Bot cenderung mengisi semua field.
            'website' => ['prohibited'],
        ];
    }

    /**
     * Adakah kelas yang cocok dengan tipe kelas yang dipilih? Kelas tanpa
     * kategori dianggap cocok untuk semua tipe.
     */
    private function classExistsForSelectedType(): bool
    {
        $type = $this->input('class_type');

        if (! is_string($type) || $type === '') {
            return false; // Biar aturan class_type yang menegur lebih dulu.
        }

        // Holiday Class tidak ada di tabel `classes`: ketersediaannya ditentukan
        // sesi mendatang di modul Holiday Class, sama seperti dropdown di form.
        if ($type === Lead::HOLIDAY_TYPE) {
            return HolidayClass::upcoming()->exists();
        }

        $categories = ClassRoom::query()->distinct()->pluck('class_category');

        // Database masih kosong → dropdown memakai daftar program di config.
        if ($categories->isEmpty()) {
            $categories = collect(config('site.programs', []))->pluck('category');
        }

        return $categories->contains(fn ($category) => ! $category || $category === $type);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'child_name' => 'nama anak',
            'child_age' => 'usia anak',
            'date_of_birth' => 'tanggal lahir',
            'class_type' => 'tipe kelas',
            'parent_name' => 'nama orang tua',
            'parent_phone' => 'nomor WhatsApp',
            'parent_email' => 'email',
            'address' => 'alamat',
            'program' => 'kelas yang diminati',
            'message' => 'pesan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_phone.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, dan tanda + - ( ).',
            'website.prohibited' => 'Pengiriman gagal. Silakan coba lagi.',
        ];
    }
}
