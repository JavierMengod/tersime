<?php

namespace Tests\Unit\Models;

use App\Models\Ajuste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Ajuste::query()->delete();
    }

    #[Test]
    public function get_returns_null_when_key_does_not_exist(): void
    {
        $this->assertNull(Ajuste::get('key_that_does_not_exist'));
    }

    #[Test]
    public function get_returns_default_when_key_does_not_exist(): void
    {
        $result = Ajuste::get('nonexistent', 'fallback_value');
        $this->assertSame('fallback_value', $result);
    }

    #[Test]
    public function get_returns_stored_value_ignoring_default(): void
    {
        Ajuste::set('my_key', 'stored_value');
        $this->assertSame('stored_value', Ajuste::get('my_key', 'should_be_ignored'));
    }

    #[Test]
    public function set_creates_new_record_in_database(): void
    {
        Ajuste::set('new_key', 'new_value');

        $this->assertDatabaseHas('ajustes', [
            'key'   => 'new_key',
            'value' => 'new_value',
        ]);
    }

    #[Test]
    public function set_updates_existing_record_without_creating_duplicate(): void
    {
        Ajuste::set('same_key', 'initial');
        Ajuste::set('same_key', 'updated');

        $this->assertSame('updated', Ajuste::get('same_key'));
        $this->assertDatabaseCount('ajustes', 1);
    }

    #[Test]
    public function set_stores_empty_string_as_valid_value(): void
    {
        Ajuste::set('empty_key', '');
        $this->assertSame('', Ajuste::get('empty_key', 'default'));
    }

    #[Test]
    public function primary_key_is_string_and_findable_by_key_name(): void
    {
        Ajuste::set('find_me', '42');

        $record = Ajuste::find('find_me');

        $this->assertNotNull($record);
        $this->assertSame('42', $record->value);
    }

    #[Test]
    public function multiple_settings_are_stored_independently(): void
    {
        Ajuste::set('key_a', 'value_a');
        Ajuste::set('key_b', 'value_b');
        Ajuste::set('key_c', 'value_c');

        $this->assertSame('value_a', Ajuste::get('key_a'));
        $this->assertSame('value_b', Ajuste::get('key_b'));
        $this->assertSame('value_c', Ajuste::get('key_c'));
        $this->assertDatabaseCount('ajustes', 3);
    }

    #[Test]
    public function get_returns_null_default_when_no_default_given_and_key_missing(): void
    {
        $this->assertNull(Ajuste::get('missing_key'));
    }

    #[Test]
    public function set_can_overwrite_with_numeric_string(): void
    {
        Ajuste::set('timeout', '30');
        Ajuste::set('timeout', '120');

        $this->assertSame('120', Ajuste::get('timeout'));
    }
}
