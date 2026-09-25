<?php

namespace App\Http\Requests;

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
        // Semua isian wajib kecuali usia anak & pesan. Usia opsional karena sudah
        // terwakili tanggal lahir (dan terisi otomatis darinya di form).
        return [
            'child_name' => ['required', 'string', 'max:100'],
            'child_age' => ['nullable', 'integer', 'min:1', 'max:99'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'class_type' => ['required', Rule::in(array_keys(Lead::classTypeOptions()))],
            'parent_name' => ['required', 'string', 'max:100'],
            'parent_phone' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['required', 'string', 'max:500'],
            'program' => ['required', Rule::in(array_keys(Lead::programOptions()))],
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
            'address' => 'alamat',
            'program' => 'program',
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
