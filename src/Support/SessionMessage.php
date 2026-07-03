<?php

namespace Dcat\Admin\Support;

use Illuminate\Contracts\Support\MessageBag;

/**
 * Session 消息对象（兼容 Laravel 10-13，兼容 PHP session.serialization = json/php）.
 *
 * 解决两类问题：
 * 1. Laravel 13 中 MessageBag 通过 redirect()->with() 序列化后变为数组的问题
 * 2. session.serialization = json 时，protected 属性不会被 json_encode 包含的问题
 *
 * 同时向后兼容旧契约：外部代码直接 flash 的 MessageBag（实例或其序列化数组）
 * 仍可被 tryFrom() 识别，不会被静默丢弃。
 */
final class SessionMessage implements \JsonSerializable
{
    private const JSON_CLASS_KEY = '__dcat_class';

    private const JSON_CLASS_VALUE = self::class;

    private const TOASTR_TYPES = ['success', 'error', 'warning', 'info'];

    public function __construct(
        protected string $title = '',
        protected string $message = '',
        protected array $options = [],
    ) {
    }

    public static function make(string $title, string $message = '', array $options = []): self
    {
        return new self($title, $message, $options);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getToastrType(): string
    {
        return in_array($this->title, self::TOASTR_TYPES, true) ? $this->title : 'info';
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * 支持 JSON session 序列化（session.serialization = json）.
     *
     * 将对象编码为包含类型标识符的数组，确保 protected 属性不丢失。
     */
    public function jsonSerialize(): mixed
    {
        return [
            self::JSON_CLASS_KEY => self::JSON_CLASS_VALUE,
            'title' => $this->title,
            'message' => $this->message,
            'options' => $this->options,
        ];
    }

    /**
     * 从 session 中读取的值尝试构造实例.
     *
     * 兼容以下情况：
     * - PHP 序列化（session.serialization = php）：值已经是 SessionMessage 对象
     * - JSON 序列化（session.serialization = json）：值是带类型标识的数组
     * - 旧契约：外部代码直接 flash 的 MessageBag 实例，或其序列化后的数组
     *   （形如 ['title' => ['x'], 'message' => ['y']]）
     */
    public static function tryFrom(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value instanceof MessageBag) {
            $value = $value->getMessages();
        }

        if (! is_array($value)) {
            return null;
        }

        if (($value[self::JSON_CLASS_KEY] ?? null) === self::JSON_CLASS_VALUE) {
            return new self(
                is_string($value['title'] ?? null) ? $value['title'] : '',
                is_string($value['message'] ?? null) ? $value['message'] : '',
                is_array($value['options'] ?? null) ? $value['options'] : [],
            );
        }

        return self::tryFromMessageBagArray($value);
    }

    /**
     * 尝试按 MessageBag 序列化数组的形态构造实例（title/message 值为字符串数组）.
     */
    private static function tryFromMessageBagArray(array $value): ?self
    {
        if (
            (! isset($value['title']) && ! isset($value['message']))
            || ! is_array($value['title'] ?? [])
            || ! is_array($value['message'] ?? [])
        ) {
            return null;
        }

        $title = $value['title'][0] ?? '';
        $message = $value['message'][0] ?? '';

        if (! is_string($title) || ! is_string($message)) {
            return null;
        }

        return new self($title, $message);
    }
}
