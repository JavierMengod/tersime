<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::query()->delete();
    }

    /** @test */
    public function get_returns_null_when_key_does_not_exist(): void
    {
        $this->assertNull(Setting::get('key_that_does_not_exist'));
    }

    /** @test */
    public function get_returns_default_when_key_does_not_exist(): void
    {
        $result = Setting::get('nonexistent', 'fallback_value');
        $this->assertSame('fallback_value', $result);
    }

    /** @test */
    public function get_returns_stored_value_ignoring_default(): void
    {
        Setting::set('my_key', 'stored_value');
        $this->assertSame('stored_value', Setting::get('my_key', 'should_be_ignored'));
    }

    /** @test */
    public function set_creates_new_record_in_database(): void
    {
        Setting::set('new_key', 'new_value');

        $this->assertDatabaseHas('settings', [
            'key'   => 'new_key',
            'value' => 'new_value',
        ]);
    }

    /** @test */
    public function set_updates_existing_record_without_creating_duplicate(): void
    {
        Setting::set('same_key', 'initial');
        Setting::set('same_key', 'updated');

        $this->assertSame('updated', Setting::get('same_key'));
        $this->assertDatabaseCount('settings', 1);
    }

    /** @test */
    public function set_stores_empty_string_as_valid_value(): void
    {
        Setting::set('empty_key', '');
        $this->assertSame('', Setting::get('empty_key', 'default'));
    }

    /** @test */
    public function primary_key_is_string_and_findable_by_key_name(): void
    {
        Setting::set('find_me', '42');

        $record = Setting::find('find_me');

        $this->assertNotNull($record);
        $this->assertSame('42', $record->value);
    }

    /** @test */
    public function multiple_settings_are_stored_independently(): void
    {
        Setting::set('key_a', 'value_a');
        Setting::set('key_b', 'value_b');
        Setting::set('key_c', 'value_c');

        $this->assertSame('value_a', Setting::get('key_a'));
        $this->assertSame('value_b', Setting::get('key_b'));
        $this->assertSame('value_c', Setting::get('key_c'));
        $this->assertDatabaseCount('settings', 3);
    }

    /** @test */
    public function get_returns_null_default_when_no_default_given_and_key_missing(): void
    {
        $this->assertNull(Setting::get('missing_key'));
    }

    /** @test */
    public function set_can_overwrite_with_numeric_string(): void
    {
        Setting::set('timeout', '30');
        Setting::set('timeout', '120');

        $this->assertSame('120', Setting::get('timeout'));
    }
}
