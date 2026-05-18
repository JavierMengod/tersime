<?php

namespace Tests\Unit\Models;

use App\Models\AlertLog;
use App\Models\Dispositivo;
use App\Models\Rule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertLogTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function channel_list_returns_array_with_single_channel(): void
    {
        $log = AlertLog::factory()->create(['channels' => ['email']]);
        $this->assertSame(['email'], $log->channels);
    }

    /** @test */
    public function channel_list_returns_all_three_channels(): void
    {
        $log = AlertLog::factory()->create(['channels' => ['email', 'telegram', 'discord']]);
        $this->assertSame(['email', 'telegram', 'discord'], $log->channels);
    }

    /** @test */
    public function channel_list_returns_two_channels(): void
    {
        $log = AlertLog::factory()->create(['channels' => ['telegram', 'discord']]);
        $this->assertSame(['telegram', 'discord'], $log->channels);
    }

    /** @test */
    public function channel_list_returns_empty_array_when_channels_is_null(): void
    {
        $log = AlertLog::factory()->create(['channels' => null]);
        $this->assertSame([], $log->channels);
    }

    /** @test */
    public function belongs_to_user(): void
    {
        $user = User::factory()->create();
        $log  = AlertLog::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $log->user);
        $this->assertTrue($log->user->is($user));
    }

    /** @test */
    public function rule_relationship_returns_null_when_rule_id_is_null(): void
    {
        $log = AlertLog::factory()->create(['rule_id' => null]);
        $this->assertNull($log->rule);
    }

    /** @test */
    public function rule_relationship_resolves_associated_rule(): void
    {
        $user = User::factory()->create();
        $rule = Rule::factory()->create(['user_id' => $user->id]);
        $log  = AlertLog::factory()->create([
            'user_id' => $user->id,
            'rule_id' => $rule->id,
        ]);

        $this->assertInstanceOf(Rule::class, $log->rule);
        $this->assertTrue($log->rule->is($rule));
    }

    /** @test */
    public function dispositivo_relationship_returns_null_when_not_set(): void
    {
        $log = AlertLog::factory()->create(['dispositivo_id' => null]);
        $this->assertNull($log->dispositivo);
    }

    /** @test */
    public function dispositivo_relationship_resolves_associated_device(): void
    {
        $device = Dispositivo::factory()->create();
        $log    = AlertLog::factory()->create(['dispositivo_id' => $device->id]);

        $this->assertInstanceOf(Dispositivo::class, $log->dispositivo);
        $this->assertTrue($log->dispositivo->is($device));
    }

    /** @test */
    public function type_firing_is_stored_correctly(): void
    {
        $log = AlertLog::factory()->firing()->create();
        $this->assertSame('firing', $log->type);
    }

    /** @test */
    public function type_resolution_is_stored_correctly(): void
    {
        $log = AlertLog::factory()->resolution()->create();
        $this->assertSame('resolution', $log->type);
    }

    /** @test */
    public function rule_name_and_device_name_are_stored_as_snapshots(): void
    {
        $log = AlertLog::factory()->create([
            'rule_name'   => 'Consumo excesivo planta 1',
            'device_name' => 'Medidor Principal',
        ]);

        $this->assertSame('Consumo excesivo planta 1', $log->rule_name);
        $this->assertSame('Medidor Principal', $log->device_name);
    }
}
