import React, { useRef, useState } from 'react';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { ImagePlus, X } from 'lucide-react';
import { cn } from '@/lib/utils';

interface FormImagePickerProps {
    label: string;
    onChange: (file: File | null) => void;
    error?: string;
    previewUrl?: string; // Initial preview (e.g. from existing item)
}

export default function FormImagePicker({
    label,
    onChange,
    error,
    previewUrl,
}: FormImagePickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(previewUrl || null);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setPreview(URL.createObjectURL(file));
            onChange(file);
        } else {
            // Do not clear preview if it was pre-loaded unless explicitly cleared?
            // User cancelled selection.
        }
    };

    const handleClear = () => {
        setPreview(null);
        onChange(null);
        if (inputRef.current) inputRef.current.value = '';
    };

    return (
        <div className="space-y-2">
            <Label className="font-medium text-zinc-300">{label}</Label>

            <div
                className={cn(
                    'relative flex h-64 w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed transition-colors',
                    error
                        ? 'border-red-500 bg-red-500/10'
                        : 'border-zinc-700 bg-zinc-900/50 hover:bg-zinc-800/50',
                )}
            >
                {preview ? (
                    <div className="relative h-full w-full p-2">
                        <img
                            src={preview}
                            alt="Preview"
                            className="h-full w-full rounded-md object-contain"
                        />
                        <Button
                            type="button"
                            variant="destructive"
                            size="icon"
                            className="absolute top-2 right-2 h-6 w-6 rounded-full"
                            onClick={(e) => {
                                e.stopPropagation();
                                handleClear();
                            }}
                        >
                            <X className="h-3 w-3" />
                        </Button>
                    </div>
                ) : (
                    <div
                        className="flex h-full w-full flex-col items-center justify-center pt-5 pb-6"
                        onClick={() => inputRef.current?.click()}
                    >
                        <ImagePlus className="mb-4 h-8 w-8 text-zinc-500" />
                        <p className="mb-2 text-sm text-zinc-400">
                            <span className="font-semibold">
                                Click to upload
                            </span>{' '}
                            or drag and drop
                        </p>
                        <p className="text-xs text-zinc-500">
                            SVG, PNG, JPG or GIF (MAX. 2MB)
                        </p>
                    </div>
                )}

                <input
                    ref={inputRef}
                    id="dropzone-file"
                    type="file"
                    className="hidden"
                    accept="image/*"
                    onChange={handleFileChange}
                />
            </div>

            {error && <div className="mt-1 text-sm text-red-500">{error}</div>}
        </div>
    );
}
