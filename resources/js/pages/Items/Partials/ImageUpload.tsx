import { useRef, useState } from 'react';
import { Label } from '@/components/ui/label';
import { Upload, X, Image as ImageIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';

interface ImageUploadProps {
    label?: string;
    onChange: (file: File | null) => void;
    error?: string;
    initialPreview?: string;
}

export default function ImageUpload({ label = "Image", onChange, error, initialPreview }: ImageUploadProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(initialPreview || null);
    const [fileName, setFileName] = useState<string | null>(null);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setFileName(file.name);
            const objectUrl = URL.createObjectURL(file);
            setPreview(objectUrl);
            onChange(file);
        } else {
            handleRemove();
        }
    };

    const handleRemove = () => {
        setFileName(null);
        setPreview(null);
        onChange(null);
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        const file = e.dataTransfer.files?.[0];
        if (file && file.type.startsWith('image/')) {
            setFileName(file.name);
            const objectUrl = URL.createObjectURL(file);
            setPreview(objectUrl);
            onChange(file);
        }
    };

    return (
        <div className="space-y-4">

            <div
                className={cn(
                    "border-2 border-dashed rounded-xl p-8 transition-all flex flex-col items-center justify-center text-center gap-4 relative overflow-hidden group min-h-[250px]",
                    error ? "border-red-500 bg-red-500/5" : "border-zinc-800 bg-zinc-950 hover:bg-zinc-900 hover:border-zinc-700",
                    preview ? "border-solid pl-0 pr-0 pt-0 pb-0" : ""
                )}
                onDragOver={(e) => e.preventDefault()}
                onDrop={handleDrop}
            >
                {preview ? (
                    <div className="relative w-full h-full min-h-[250px] flex items-center justify-center bg-black">
                        <img
                            src={preview}
                            alt="Preview"
                            className="max-w-full max-h-[250px] object-contain"
                        />
                        <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                onClick={handleRemove}
                                className="h-8 w-8 p-0 rounded-full"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => inputRef.current?.click()}
                            >
                                Change
                            </Button>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="p-4 rounded-full bg-zinc-900 border border-zinc-800 group-hover:scale-110 transition-transform duration-300">
                            <Upload className="h-8 w-8 text-zinc-500 group-hover:text-blue-500 transition-colors" />
                        </div>
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-zinc-300">
                                Click to upload image
                            </p>
                            <p className="text-xs text-zinc-500">
                                SVG, PNG, JPG or GIF (max. 800x400px)
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="mt-2 border-zinc-700 hover:bg-zinc-800 text-zinc-300"
                            onClick={() => inputRef.current?.click()}
                        >
                            Browse File
                        </Button>
                    </>
                )}

                <input
                    ref={inputRef}
                    type="file"
                    className="hidden"
                    accept="image/*"
                    onChange={handleFileChange}
                />
            </div>
            {error && <p className="text-sm text-red-500">{error}</p>}
        </div>
    );
}
