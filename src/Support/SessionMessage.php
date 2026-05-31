<?php

namespace Dcat\Admin\Support;

/**
 * Session 消息对象（兼容 Laravel 10-13，兼容 PHP session.serialization = json/php）.
 *
 * 解决两类问题：
 * 1. Laravel 13 中 MessageBag 通过 redirect()->with() 序列化后变为数组的问题
 * 2. session.serialization = json 时，protected 属性不会被 json_encode 包含的问题
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
     * 兼容两种情况：
     * - PHP 序列化（session.serialization = php）：值已经是 SessionMessage 对象
     * - JSON 序列化（session.serialization = json）：值是带类型标识的数组
     */
    public static function tryFrom(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (
            is_array($value)
            && ($value[self::JSON_CLASS_KEY] ?? null) === self::JSON_CLASS_VALUE
        ) {
            return new self(
                is_string($value['title'] ?? null) ? $value['title'] : '',
                is_string($value['message'] ?? null) ? $value['message'] : '',
                is_array($value['options'] ?? null) ? $value['options'] : [],
            );
        }

        return null;
    }
}
