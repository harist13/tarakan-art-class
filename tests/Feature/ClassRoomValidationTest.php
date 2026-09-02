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

    public function test_cannot_create_class_with_duplicate_category(): void
    {
        ClassRoom::create([
            'class_category' => 'Coloring',
            'tutor_id' => $this->tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '09:00',
            'is_recurring' => true,
            'class_fee' => 150000,
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('classes.create'))
            ->post(route('classes.store'), [
                'class_category' => 'Coloring',
                'tutor_id' => $this->tutor->id,
                'capacity' => 10,
                'schedule_date' => now()->toDateString(),
                'schedule_time' => '10:00',
                'schedule_end_time' => '11:00',
                'class_type' => 'regular',
                'class_fee' => 150000,
            ]);

        $response->assertRedirect(route('classes.create'));
        $response->assertSessionHasErrors([
            'class_category' => 'Kelas sudah ada.',
        ]);
    }

    public function test_can_update_class_keeping_same_category(): void
    {
        $class = ClassRoom::create([
            'class_category' => 'Drawing',
            'tutor_id' => $this->tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '09:00',
            'is_recurring' => true,
            'class_fee' => 150000,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('classes.update', $class), [
                'class_category' => 'Drawing',
                'tutor_id' => $this->tutor->id,
                'capacity' => 12,
                'schedule_date' => now()->toDateString(),
                'schedule_time' => '09:00',
                'schedule_end_time' => '10:00',
                'class_type' => 'regular',
                'class_fee' => 150000,
            ]);

        $response->assertRedirect(route('classes.index'));
        $response->assertSessionHasNoErrors();
        $this->assertEquals(12, $class->fresh()->capacity);
    }

    public function test_cannot_update_class_to_another_existing_category(): void
    {
        ClassRoom::create([
            'class_category' => 'Preschool',
            'tutor_id' => $this->tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '09:00',
            'is_recurring' => true,
            'class_fee' => 150000,
        ]);

        $class2 = ClassRoom::create([
            'class_category' => 'Drawing',
            'tutor_id' => $this->tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '09:00',
            'is_recurring' => true,
            'class_fee' => 150000,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('classes.update', $class2), [
                'class_category' => 'Preschool',
                'tutor_id' => $this->tutor->id,
                'capacity' => 10,
                'schedule_date' => now()->toDateString(),
                'schedule_time' => '09:00',
                'schedule_end_time' => '10:00',
                'class_type' => 'regular',
                'class_fee' => 150000,
            ]);

        $response->assertSessionHasErrors([
            'class_category' => 'Kelas sudah ada.',
        ]);
    }

    public function test_create_form_displays_validation_error_message(): void
    {
        ClassRoom::create([
            'class_category' => 'Coloring',
            'tutor_id' => $this->tutor->id,
            'capacity' => 10,
            'schedule_date' => now()->toDateString(),
            'schedule_time' => '09:00',
            'is_recurring' => true,
            'class_fee' => 150000,
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('classes.create'))
            ->post(route('classes.store'), [
                'class_category' => 'Coloring',
                'tutor_id' => $this->tutor->id,
                'capacity' => 10,
                'schedule_date' => now()->toDateString(),
                'schedule_time' => '10:00',
                'schedule_end_time' => '11:00',
                'class_type' => 'regular',
                'class_fee' => 150000,
            ]);

        $followed = $this->followRedirects($response);
        $followed->assertOk();
        $followed->assertSee('is-invalid');
        $followed->assertSee('Kelas sudah ada.');
    }
}
