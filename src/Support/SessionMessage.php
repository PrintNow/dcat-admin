<?php

namespace Dcat\Admin\Support;

/**
 * Session 消息对象（兼容 Laravel 10-13）.
 *
 * 解决 Laravel 13 中 MessageBag 通过 redirect()->with() 序列化后变为数组的问题
 */
class SessionMessage
{
    public function __construct(
        protected string $title = '',
        protected string $message = '',
        protected array $options = [],
    ) {
    }

    public static function make(string $title, string $message = '', array $options = []): static
    {
        return new static($title, $message, $options);
    }

    public static function fromArray(array $data): static
    {
        return new static(
            title: (string) (\Illuminate\Support\Arr::first((array) ($data['title'] ?? '')) ?? ''),
            message: (string) (\Illuminate\Support\Arr::first((array) ($data['message'] ?? '')) ?? ''),
            options: (array) ($data['options'] ?? []),
        );
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'options' => $this->options,
        ];
    }
}
