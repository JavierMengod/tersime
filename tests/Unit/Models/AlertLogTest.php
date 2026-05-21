<?php

namespace Tests\Unit\Models;

use App\Models\RegistroAlerta;
use App\Models\Dispositivo;
use App\Models\Regla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function channels_cast_deserializes_single_channel(): void
    {
        $log = RegistroAlerta::factory()->create(['canales' => ['email']]);
        $this->assertSame(['email'], $log->canales);
    }

    #[Test]
    public function channels_cast_deserializes_all_three_channels(): void
    {
        $log = RegistroAlerta::factory()->create(['canales' => ['email', 'telegram', 'discord']]);
        $this->assertSame(['email', 'telegram', 'discord'], $log->canales);
    }

    #[Test]
    public function channels_cast_deserializes_two_channels(): void
    {
        $log = RegistroAlerta::factory()->create(['canales' => ['telegram', 'discord']]);
        $this->assertSame(['telegram', 'discord'], $log->canales);
    }

    #[Test]
    public function channels_cast_returns_null_when_not_set(): void
    {
        $log = RegistroAlerta::factory()->create(['canales' => null]);
        $this->assertNull($log->canales);
    }

    #[Test]
    public function belongs_to_user(): void
    {
        $user = User::factory()->create();
        $log  = RegistroAlerta::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $log->usuario);
        $this->assertTrue($log->usuario->is($user));
    }

    #[Test]
    public function rule_relationship_returns_null_when_rule_id_is_null(): void
    {
        $log = RegistroAlerta::factory()->create(['regla_id' => null]);
        $this->assertNull($log->regla);
    }

    #[Test]
    public function rule_relationship_resolves_associated_rule(): void
    {
        $user = User::factory()->create();
        $rule = Regla::factory()->create(['user_id' => $user->id]);
        $log  = RegistroAlerta::factory()->create([
            'user_id' => $user->id,
            'regla_id' => $rule->id,
        ]);

        $this->assertInstanceOf(Regla::class, $log->regla);
        $this->assertTrue($log->regla->is($rule));
    }

    #[Test]
    public function dispositivo_relationship_returns_null_when_not_set(): void
    {
        $log = RegistroAlerta::factory()->create(['dispositivo_id' => null]);
        $this->assertNull($log->dispositivo);
    }

    #[Test]
    public function dispositivo_relationship_resolves_associated_device(): void
    {
        $device = Dispositivo::factory()->create();
        $log    = RegistroAlerta::factory()->create(['dispositivo_id' => $device->id]);

        $this->assertInstanceOf(Dispositivo::class, $log->dispositivo);
        $this->assertTrue($log->dispositivo->is($device));
    }

    #[Test]
    public function type_firing_is_stored_correctly(): void
    {
        $log = RegistroAlerta::factory()->firing()->create();
        $this->assertSame('firing', $log->tipo);
    }

    #[Test]
    public function type_resolution_is_stored_correctly(): void
    {
        $log = RegistroAlerta::factory()->resolution()->create();
        $this->assertSame('resolution', $log->tipo);
    }

    #[Test]
    public function rule_name_and_device_name_are_stored_as_snapshots(): void
    {
        $log = RegistroAlerta::factory()->create([
            'nombre_regla'   => 'Consumo excesivo planta 1',
            'nombre_dispositivo' => 'Medidor Principal',
        ]);

        $this->assertSame('Consumo excesivo planta 1', $log->nombre_regla);
        $this->assertSame('Medidor Principal', $log->nombre_dispositivo);
    }
}
