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
        $log = RegistroAlerta::factory()->create(['channels' => ['email']]);
        $this->assertSame(['email'], $log->channels);
    }

    #[Test]
    public function channels_cast_deserializes_all_three_channels(): void
    {
        $log = RegistroAlerta::factory()->create(['channels' => ['email', 'telegram', 'discord']]);
        $this->assertSame(['email', 'telegram', 'discord'], $log->channels);
    }

    #[Test]
    public function channels_cast_deserializes_two_channels(): void
    {
        $log = RegistroAlerta::factory()->create(['channels' => ['telegram', 'discord']]);
        $this->assertSame(['telegram', 'discord'], $log->channels);
    }

    #[Test]
    public function channels_cast_returns_null_when_not_set(): void
    {
        $log = RegistroAlerta::factory()->create(['channels' => null]);
        $this->assertNull($log->channels);
    }

    #[Test]
    public function belongs_to_user(): void
    {
        $user = User::factory()->create();
        $log  = RegistroAlerta::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $log->user);
        $this->assertTrue($log->user->is($user));
    }

    #[Test]
    public function rule_relationship_returns_null_when_rule_id_is_null(): void
    {
        $log = RegistroAlerta::factory()->create(['rule_id' => null]);
        $this->assertNull($log->regla);
    }

    #[Test]
    public function rule_relationship_resolves_associated_rule(): void
    {
        $user = User::factory()->create();
        $rule = Regla::factory()->create(['user_id' => $user->id]);
        $log  = RegistroAlerta::factory()->create([
            'user_id' => $user->id,
            'rule_id' => $rule->id,
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
        $this->assertSame('firing', $log->type);
    }

    #[Test]
    public function type_resolution_is_stored_correctly(): void
    {
        $log = RegistroAlerta::factory()->resolution()->create();
        $this->assertSame('resolution', $log->type);
    }

    #[Test]
    public function rule_name_and_device_name_are_stored_as_snapshots(): void
    {
        $log = RegistroAlerta::factory()->create([
            'rule_name'   => 'Consumo excesivo planta 1',
            'device_name' => 'Medidor Principal',
        ]);

        $this->assertSame('Consumo excesivo planta 1', $log->rule_name);
        $this->assertSame('Medidor Principal', $log->device_name);
    }
}
