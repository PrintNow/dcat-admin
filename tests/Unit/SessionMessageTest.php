<?php

namespace Tests\Unit;

use Dcat\Admin\Support\SessionMessage;
use PHPUnit\Framework\TestCase;

class SessionMessageTest extends TestCase
{
    // -----------------------------------------------------------------------
    // make / getters
    // -----------------------------------------------------------------------

    public function test_make_returns_instance_with_correct_values(): void
    {
        $msg = SessionMessage::make('success', 'hello', ['timeOut' => 3000]);

        $this->assertSame('success', $msg->getTitle());
        $this->assertSame('hello', $msg->getMessage());
        $this->assertSame(['timeOut' => 3000], $msg->getOptions());
    }

    public function test_make_defaults_message_and_options(): void
    {
        $msg = SessionMessage::make('info');

        $this->assertSame('', $msg->getMessage());
        $this->assertSame([], $msg->getOptions());
    }

    // -----------------------------------------------------------------------
    // getToastrType — whitelist
    // -----------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('validToastrTypes')]
    public function test_get_toastr_type_returns_valid_type(string $type): void
    {
        $this->assertSame($type, SessionMessage::make($type)->getToastrType());
    }

    public static function validToastrTypes(): array
    {
        return [
            ['success'],
            ['error'],
            ['warning'],
            ['info'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidToastrTypes')]
    public function test_get_toastr_type_falls_back_to_info_for_invalid_type(string $type): void
    {
        $this->assertSame('info', SessionMessage::make($type)->getToastrType());
    }

    public static function invalidToastrTypes(): array
    {
        return [
            [''],
            ['alert'],
            ['SUCCESS'],
            ['<script>alert(1)</script>'],
            ['error; DROP TABLE'],
        ];
    }

    // -----------------------------------------------------------------------
    // jsonSerialize (JSON session 序列化)
    // -----------------------------------------------------------------------

    public function test_json_serialize_includes_class_key_and_all_fields(): void
    {
        $msg = SessionMessage::make('error', 'something went wrong', ['closeButton' => true]);
        $data = $msg->jsonSerialize();

        $this->assertSame('Dcat\Admin\Support\SessionMessage', $data['__dcat_class']);
        $this->assertSame('error', $data['title']);
        $this->assertSame('something went wrong', $data['message']);
        $this->assertSame(['closeButton' => true], $data['options']);
    }

    public function test_json_encode_roundtrip(): void
    {
        $msg = SessionMessage::make('warning', 'be careful', ['positionClass' => 'toast-top-right']);
        $decoded = json_decode(json_encode($msg), true);

        $this->assertSame('warning', $decoded['title']);
        $this->assertSame('be careful', $decoded['message']);
        $this->assertSame(['positionClass' => 'toast-top-right'], $decoded['options']);
    }

    // -----------------------------------------------------------------------
    // tryFrom — PHP 序列化路径
    // -----------------------------------------------------------------------

    public function test_try_from_accepts_existing_instance(): void
    {
        $original = SessionMessage::make('success', 'done');
        $result = SessionMessage::tryFrom($original);

        $this->assertSame($original, $result);
    }

    public function test_try_from_returns_null_for_plain_object(): void
    {
        $this->assertNull(SessionMessage::tryFrom(new \stdClass()));
    }

    public function test_try_from_returns_null_for_null(): void
    {
        $this->assertNull(SessionMessage::tryFrom(null));
    }

    public function test_try_from_returns_null_for_string(): void
    {
        $this->assertNull(SessionMessage::tryFrom('success'));
    }

    // -----------------------------------------------------------------------
    // tryFrom — JSON 序列化路径
    // -----------------------------------------------------------------------

    public function test_try_from_reconstructs_from_json_array(): void
    {
        $original = SessionMessage::make('info', 'hello', ['timeOut' => 5000]);
        $array = json_decode(json_encode($original), true);

        $restored = SessionMessage::tryFrom($array);

        $this->assertNotNull($restored);
        $this->assertSame('info', $restored->getTitle());
        $this->assertSame('hello', $restored->getMessage());
        $this->assertSame(['timeOut' => 5000], $restored->getOptions());
    }

    public function test_try_from_returns_null_for_array_without_class_key(): void
    {
        $this->assertNull(SessionMessage::tryFrom(['title' => 'info', 'message' => 'hi']));
    }

    public function test_try_from_returns_null_for_array_with_wrong_class_key(): void
    {
        $this->assertNull(SessionMessage::tryFrom([
            '__dcat_class' => 'Some\Other\Class',
            'title' => 'info',
            'message' => 'hi',
        ]));
    }

    public function test_try_from_tolerates_missing_fields_in_json_array(): void
    {
        $restored = SessionMessage::tryFrom([
            '__dcat_class' => 'Dcat\Admin\Support\SessionMessage',
        ]);

        $this->assertNotNull($restored);
        $this->assertSame('', $restored->getTitle());
        $this->assertSame('', $restored->getMessage());
        $this->assertSame([], $restored->getOptions());
    }

    public function test_try_from_ignores_non_string_title_and_message(): void
    {
        $restored = SessionMessage::tryFrom([
            '__dcat_class' => 'Dcat\Admin\Support\SessionMessage',
            'title'   => ['injected'],
            'message' => 42,
            'options' => ['timeOut' => 3000],
        ]);

        $this->assertNotNull($restored);
        $this->assertSame('', $restored->getTitle());
        $this->assertSame('', $restored->getMessage());
        $this->assertSame(['timeOut' => 3000], $restored->getOptions());
    }

    public function test_try_from_ignores_non_array_options(): void
    {
        $restored = SessionMessage::tryFrom([
            '__dcat_class' => 'Dcat\Admin\Support\SessionMessage',
            'title'   => 'info',
            'message' => 'hello',
            'options' => 'not-an-array',
        ]);

        $this->assertNotNull($restored);
        $this->assertSame('info', $restored->getTitle());
        $this->assertSame('hello', $restored->getMessage());
        $this->assertSame([], $restored->getOptions());
    }
}