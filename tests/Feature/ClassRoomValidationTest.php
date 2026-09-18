<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassRoomValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tutor $tutor;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);
        $this->admin = User::create([
            'full_name' => 'Admin Test',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->admin->assignRole('admin');

        $this->tutor = Tutor::create([
            'name' => 'Kak Tutor',
            'status' => 'full-time',
        ]);
    }

    private function makeClass(string $category, string $time = '09:00', array $overrides = []): ClassRoom
    {
        return ClassRoom::create(array_merge([
            'class_category' => $category,
            'tutor_id' => $this->tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => $time,
            'is_recurring' => true,
            'class_fee' => 150000,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'class_category' => 'Coloring',
            'tutor_id' => $this->tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '09:00',
            'schedule_end_time' => '10:30',
            'class_type' => 'regular',
            'class_fee' => 150000,
        ], $overrides);
    }

    private function clashMessage(ClassRoom $existing): string
    {
        return "Kelas {$existing->class_category} sudah ada di jadwal {$existing->scheduleLabel()} ({$existing->class_code}).";
    }

    public function test_same_category_can_have_another_schedule(): void
    {
        $this->makeClass('Coloring', '09:00');

        $this->actingAs($this->admin)
            ->post(route('classes.store'), $this->payload(['schedule_time' => '10:30', 'schedule_end_time' => '12:00']))
            ->assertRedirect(route('classes.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ClassRoom::where('class_category', 'Coloring')->count());
    }

    public function test_cannot_create_same_category_on_same_day_and_time(): void
    {
        $existing = $this->makeClass('Coloring', '09:00');

        // Sepekan kemudian tetap hari yang sama — slot mingguannya kembar.
        $this->actingAs($this->admin)
            ->from(route('classes.create'))
            ->post(route('classes.store'), $this->payload([
                'class_category' => 'coloring',
                'schedule_date' => now()->addWeek()->toDateString(),
            ]))
            ->assertRedirect(route('classes.create'))
            ->assertSessionHasErrors(['class_category' => $this->clashMessage($existing)]);
    }

    public function test_can_update_class_that_shares_category_with_other_schedules(): void
    {
        // Kasus hasil impor: banyak kelas sekategori, admin mengganti tutornya.
        $this->makeClass('Drawing', '09:00');
        $class = $this->makeClass('Drawing', '13:30');
        $tutorBaru = Tutor::create(['name' => 'Kak Baru', 'status' => 'part-time']);

        $this->actingAs($this->admin)
            ->put(route('classes.update', $class), $this->payload([
                'class_category' => 'Drawing',
                'tutor_id' => $tutorBaru->id,
                'schedule_time' => '13:30',
                'schedule_end_time' => '15:00',
            ]))
            ->assertRedirect(route('classes.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($tutorBaru->id, $class->fresh()->tutor_id);
    }

    public function test_cannot_move_class_onto_an_existing_slot(): void
    {
        $existing = $this->makeClass('Preschool', '09:00');
        $class = $this->makeClass('Drawing', '09:00');

        $this->actingAs($this->admin)
            ->put(route('classes.update', $class), $this->payload(['class_category' => 'Preschool']))
            ->assertSessionHasErrors(['class_category' => $this->clashMessage($existing)]);
    }

    public function test_trial_classes_on_different_dates_do_not_clash(): void
    {
        $this->makeClass('Coloring', '09:00', ['is_recurring' => false, 'class_type' => 'trial']);

        $this->actingAs($this->admin)
            ->post(route('classes.store'), $this->payload([
                'class_type' => 'trial',
                'schedule_date' => now()->addWeek()->toDateString(),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ClassRoom::count());
    }

    public function test_trial_on_a_regular_session_clashes(): void
    {
        $existing = $this->makeClass('Coloring', '09:00');

        $this->actingAs($this->admin)
            ->post(route('classes.store'), $this->payload([
                'class_type' => 'trial',
                'schedule_date' => now()->addWeeks(2)->toDateString(),
            ]))
            ->assertSessionHasErrors(['class_category' => $this->clashMessage($existing)]);
    }

    public function test_create_form_displays_validation_error_message(): void
    {
        $this->makeClass('Coloring', '09:00');

        $response = $this->actingAs($this->admin)
            ->from(route('classes.create'))
            ->post(route('classes.store'), $this->payload());

        $followed = $this->followRedirects($response);
        $followed->assertOk();
        $followed->assertSee('is-invalid');
        $followed->assertSee('sudah ada di jadwal');
    }

    /**
     * Kelas boleh disimpan tanpa tutor: di sanggar, jadwal & muridnya sering
     * lebih dulu ada daripada keputusan siapa yang mengajar.
     */
    public function test_kelas_boleh_disimpan_tanpa_tutor(): void
    {
        $this->actingAs($this->admin)
            ->post(route('classes.store'), $this->payload(['tutor_id' => null]))
            ->assertSessionHasNoErrors();

        $class = ClassRoom::where('class_category', 'Coloring')->sole();

        $this->assertNull($class->tutor_id);
        $this->assertTrue($class->needsTutor());
        $this->assertSame('Tutor kosong', $class->availability()['text']);
    }

    /** Tutor yang sudah ditunjuk boleh dilepas lagi lewat form yang sama. */
    public function test_tutor_boleh_dilepas_dari_kelas_yang_sudah_ada(): void
    {
        $class = $this->makeClass('Coloring', '09:00');

        $this->actingAs($this->admin)
            ->put(route('classes.update', $class), $this->payload([
                'tutor_id' => null,
                'schedule_time' => '09:00',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($class->fresh()->tutor_id);
    }

    /** Filter "Tutor kosong" juga menjaring kelas yang tutor_id-nya memang NULL. */
    public function test_filter_tutor_kosong_menjaring_kelas_tanpa_tutor(): void
    {
        $tanpa = $this->makeClass('Kelas Tanpa Tutor', '09:00', ['tutor_id' => null]);
        $this->makeClass('Kelas Bertutor', '11:00');

        $response = $this->actingAs($this->admin)
            ->get(route('classes.index', ['tab' => 'kelas', 'status' => 'tanpa-tutor']));

        $response->assertOk();
        $this->assertSame([$tanpa->id], $response->viewData('classes')->pluck('id')->all());
    }

    /**
     * Ikon "tentukan tutor" di kalender membuka form ini dengan ?tutor=kosong.
     * Yang dijaga: isian tutornya benar-benar ditandai — pesan merah & pilihan
     * tutor titipan dilepas — bukan sekadar halaman edit biasa yang terbuka.
     */
    public function test_edit_form_highlights_tutor_field_when_asked_to_assign_one(): void
    {
        $titipan = Tutor::create(['name' => Tutor::PLACEHOLDER_NAME, 'status' => 'part-time']);
        $class = $this->makeClass('Coloring', '09:00', ['tutor_id' => $titipan->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('classes.edit', [$class, 'tutor' => 'kosong']));

        $response->assertOk();
        $response->assertSee('Silakan tentukan tutor.');
        $response->assertSee('data-perlu-tutor', false);
        // Tutor titipan tidak boleh tetap terpilih: Simpan akan lolos tanpa satu
        // pun tutor sungguhan ditunjuk.
        $response->assertDontSee('value="'.$titipan->id.'" selected', false);
    }

    /** Tanpa penanda itu, form edit tetap seperti biasa. */
    public function test_edit_form_stays_plain_without_the_assign_tutor_flag(): void
    {
        $class = $this->makeClass('Coloring', '09:00');

        $response = $this->actingAs($this->admin)->get(route('classes.edit', $class));

        $response->assertOk();
        $response->assertDontSee('Silakan tentukan tutor.');
        $response->assertSee('value="'.$this->tutor->id.'" selected', false);
    }
}
