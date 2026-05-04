<?php

namespace Dcat\Admin\Http\Controllers;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TinymceController
{
    public function upload(Request $request)
    {
        $file = $request->file('file');
        $dir = $this->sanitizeDir($request->get('dir'));
        $disk = $this->disk();

        $newName = $this->generateNewName($file);

        $disk->putFileAs($dir, $file, $newName);

        return ['location' => $disk->url("{$dir}/$newName")];
    }

    protected function generateNewName(UploadedFile $file)
    {
        return uniqid(md5($file->getClientOriginalName()), true).'.'.$file->getClientOriginalExtension();
    }

    /**
     * Sanitize directory path to prevent path traversal.
     */
    protected function sanitizeDir(?string $dir): string
    {
        $dir = trim($dir ?? '', '/');

        // 移除路径遍历字符
        $dir = str_replace(['../', '..\\', '..'], '', $dir);

        // 确保路径不以点开头（隐藏文件）
        $dir = ltrim($dir, '.');

        return $dir ?: 'uploads';
    }

    /**
     * @return \Illuminate\Contracts\Filesystem\Filesystem|FilesystemAdapter
     */
    protected function disk()
    {
        $disk = request()->get('disk') ?: config('admin.upload.disk');

        // 验证磁盘配置存在
        if (! config("filesystems.disks.{$disk}")) {
            $disk = config('admin.upload.disk', 'local');
        }

        return Storage::disk($disk);
    }
}
