<?php

namespace Tests\Unit\Models;

use App\Models\Dispositivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DispositivoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function nombre_returns_influx_tag_as_fallback_when_no_user_context(): void
    {
        $device = Dispositivo::factory()->create(['influx_tag' => 'RAW_TAG_001']);

        $this->assertSame('RAW_TAG_001', $device->nombre);
    }

    #[Test]
    public function nombre_returns_pivot_nombre_when_loaded_via_user_relation(): void
    {
        $user   = User::factory()->create();
        $device = Dispositivo::factory()->create(['influx_tag' => 'DEV_001']);

        $user->dispositivos()->attach($device->id, [
            'nombre'    => 'Medidor Planta Baja',
            'habilitado' => 1,
        ]);

        $loaded = $user->dispositivos()->where('dispositivos.id', $device->id)->first();

        $this->assertSame('Medidor Planta Baja', $loaded->nombre);
    }

    #[Test]
    public function nombre_returns_db_lookup_for_authenticated_user_without_pivot(): void
    {
        $user   = User::factory()->create();
        $device = Dispositivo::factory()->create(['influx_tag' => 'DEV_002']);

        $user->dispositivos()->attach($device->id, [
            'nombre'    => 'Contador Principal',
            'habilitado' => 1,
        ]);

        $this->actingAs($user);

        $fresh = Dispositivo::find($device->id);
        $this->assertSame('Contador Principal', $fresh->nombre);
    }

    #[Test]
    public function nombre_returns_influx_tag_for_authenticated_user_with_no_association(): void
    {
        $user   = User::factory()->create();
        $device = Dispositivo::factory()->create(['influx_tag' => 'ORPHAN_DEV']);

        $this->actingAs($user);

        $this->assertSame('ORPHAN_DEV', $device->nombre);
    }

    #[Test]
    public function belongs_to_many_users_with_nombre_pivot(): void
    {
        $user1  = User::factory()->create();
        $user2  = User::factory()->create();
        $device = Dispositivo::factory()->create();

        $user1->dispositivos()->attach($device->id, ['nombre' => 'Medidor A', 'habilitado' => 1]);
        $user2->dispositivos()->attach($device->id, ['nombre' => 'Medidor B', 'habilitado' => 1]);

        $this->assertCount(2, $device->usuarios);
        $this->assertTrue($device->usuarios->contains($user1));
        $this->assertTrue($device->usuarios->contains($user2));
    }

    #[Test]
    public function different_users_can_assign_different_nombres_to_same_device(): void
    {
        $user1  = User::factory()->create();
        $user2  = User::factory()->create();
        $device = Dispositivo::factory()->create(['influx_tag' => 'SHARED_DEV']);

        $user1->dispositivos()->attach($device->id, ['nombre' => 'Nombre User1', 'habilitado' => 1]);
        $user2->dispositivos()->attach($device->id, ['nombre' => 'Nombre User2', 'habilitado' => 1]);

        $fromUser1 = $user1->dispositivos()->where('dispositivos.id', $device->id)->first();
        $fromUser2 = $user2->dispositivos()->where('dispositivos.id', $device->id)->first();

        $this->assertSame('Nombre User1', $fromUser1->nombre);
        $this->assertSame('Nombre User2', $fromUser2->nombre);
    }

    #[Test]
    public function habilitado_pivot_filters_devices_correctly(): void
    {
        $user     = User::factory()->create();
        $enabled  = Dispositivo::factory()->create();
        $disabled = Dispositivo::factory()->create();

        $user->dispositivos()->attach($enabled->id,  ['nombre' => 'On',  'habilitado' => 1]);
        $user->dispositivos()->attach($disabled->id, ['nombre' => 'Off', 'habilitado' => 0]);

        $active = $user->dispositivos()->wherePivot('habilitado', 1)->get();

        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->is($enabled));
    }
}
