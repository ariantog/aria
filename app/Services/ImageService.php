<?php

namespace App\Services;

use App\Models\ItemGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class ImageService
{
    // protected ImageManager $manager;

    public function __construct()
    {
        // $this->manager = new ImageManager(new Driver());
    }

    public function saveImage(ItemGroup $group, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }

        // Logic ported from ItemsManagerHelper
        // Logic ported from ItemsManagerHelper
        $folder = str_pad(substr((string) $group->id, -2), 2, '0', STR_PAD_LEFT);
        $filename = $group->id.'.jpg';

        $path = config('core-nation.item_image_path', public_path('asset/'));
        // $filePath = $path . $folder . '/' . $filename;
        $dirPath = $path.$folder;

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

        // Logic ported from ItemsManagerHelper for Items
        // Logic ported from ItemsManagerHelper for Items
        $folderId = ($item->group_id > 0) ? $item->group_id : $item->id;
        $folder = str_pad(substr((string) $folderId, -2), 2, '0', STR_PAD_LEFT);
        $filename = $folderId.'.jpg';

        $path = config('core-nation.item_image_path', public_path('asset/'));
        $dirPath = $path.$folder;

        if (! File::exists($dirPath)) {
            File::makeDirectory($dirPath, 0755, true);
        }

        $file->move($dirPath, $filename);
    }

    public function copyItemImage(\App\Models\Item $sourceItem, \App\Models\Item $targetItem): void
    {
        // Source Path
        $sFolderId = ($sourceItem->group_id > 0) ? $sourceItem->group_id : $sourceItem->id;
        $sFolder = str_pad(substr((string) $sFolderId, -2), 2, '0', STR_PAD_LEFT);
        $sFilename = $sFolderId.'.jpg';
        $sPath = config('core-nation.item_image_path', public_path('asset/')).$sFolder.'/'.$sFilename;

        if (! File::exists($sPath)) {
            return;
        }

        // Target Path
        $tFolderId = ($targetItem->group_id > 0) ? $targetItem->group_id : $targetItem->id;
        $tFolder = str_pad(substr((string) $tFolderId, -2), 2, '0', STR_PAD_LEFT);
        $tFilename = $tFolderId.'.jpg';
        $tDir = config('core-nation.item_image_path', public_path('asset/')).$tFolder;

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
