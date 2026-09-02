<?php

namespace App\Http\Requests;

use App\Models\ClassRoom;
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
            ClassRoom::query()->distinct()->pluck('class_category')->all(),
        );

        // Semua isian wajib kecuali usia anak, "kelas yang diminati", & pesan. Usia
        // opsional karena sudah terwakili tanggal lahir (dan terisi otomatis darinya
        // di form).
        return [
            'child_name' => ['required', 'string', 'max:100'],
            'child_age' => ['nullable', 'integer', 'min:1', 'max:99'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'class_type' => ['required', Rule::in(array_keys(Lead::classTypeOptions()))],
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
