<?php

namespace App\Services;

use App\Models\ItemGroup;
use App\Support\ItemImageResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class ImageService
{
    public function __construct(
        protected ItemImageResolver $imageResolver,
    ) {}

    public function saveImage(ItemGroup $group, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }

        $dirPath = dirname($this->imageResolver->diskPathForId((int) $group->id));
        $filename = $this->imageResolver->filenameForId((int) $group->id);

        if (! File::exists($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        // Fallback: Just move the file without resizing
        // TODO: Implement resizing when intervention/image can be installed.
        // Current environment PHP version blocks installation of intervention/image due to conflicts.

        $file->move($dirPath, $filename);
    }

    public function saveItemImage(\App\Models\Item $item, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }

        $folderId = ($item->group_id > 0) ? (int) $item->group_id : (int) $item->id;
        $dirPath = dirname($this->imageResolver->diskPathForId($folderId));
        $filename = $this->imageResolver->filenameForId($folderId);

        if (! File::exists($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        $file->move($dirPath, $filename);
    }

    public function copyItemImage(\App\Models\Item $sourceItem, \App\Models\Item $targetItem): void
    {
        $sFolderId = ($sourceItem->group_id > 0) ? (int) $sourceItem->group_id : (int) $sourceItem->id;
        $sPath = $this->imageResolver->diskPathForId($sFolderId);

        if (! File::exists($sPath)) {
            return;
        }

        $tFolderId = ($targetItem->group_id > 0) ? (int) $targetItem->group_id : (int) $targetItem->id;
        $tDir = dirname($this->imageResolver->diskPathForId($tFolderId));
        $tFilename = $this->imageResolver->filenameForId($tFolderId);

        if (! File::exists($tDir)) {
            File::makeDirectory($tDir, 0755, true);
        }

        // Optimization: If paths are identical (same group), do nothing
        if ($sPath === $tDir.'/'.$tFilename) {
            return;
        }

        File::copy($sPath, $tDir.'/'.$tFilename);
    }
}
