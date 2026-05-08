import { useZxing } from 'react-zxing';
import { X, Camera, RefreshCcw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    onScan: (barcode: string) => void;
}

export default function CameraScanner({ isOpen, onClose, onScan }: Props) {
    const [error, setError] = useState<string | null>(null);
    const [isPaused, setIsPaused] = useState(false);

    const { ref } = useZxing({
        onDecodeResult(result) {
            if (!isPaused) {
                onScan(result.getText());
                // Optional: add a small delay before allowing next scan or close
                setIsPaused(true);
                setTimeout(() => setIsPaused(false), 2000);
            }
        },
        onError(err) {
            console.error(err);
            // Don't show error for every frame fail, only major ones
            if (err.name === 'NotAllowedError') {
                setError('Camera permission denied.');
            }
        },
        paused: isPaused || !isOpen,
    });

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Camera className="h-4 w-4" />
                        Scan Barcode
                    </DialogTitle>
                </DialogHeader>
                
                <div className="relative aspect-video overflow-hidden rounded-lg bg-black">
                    <video
                        ref={ref}
                        className="h-full w-full object-cover"
                    />
                    
                    {/* Overlay/Target Area */}
                    <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div className="h-32 w-64 border-2 border-blue-500/50 rounded-lg">
                            <div className="h-full w-full border border-white/20 animate-pulse bg-blue-500/5" />
                        </div>
                    </div>

                    {isPaused && (
                        <div className="absolute inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm">
                            <div className="flex flex-col items-center gap-3">
                                <div className="h-8 w-8 rounded-full border-2 border-white border-t-transparent animate-spin" />
                                <span className="text-xs font-medium text-white">Processing scan...</span>
                            </div>
                        </div>
                    )}

                    {error && (
                        <div className="absolute inset-0 flex items-center justify-center bg-red-950/80 p-6 text-center">
                            <div className="space-y-3">
                                <X className="mx-auto h-8 w-8 text-red-500" />
                                <p className="text-sm font-medium text-red-200">{error}</p>
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    onClick={() => window.location.reload()}
                                    className="text-white border-white/20 hover:bg-white/10"
                                >
                                    <RefreshCcw className="mr-2 h-3 w-3" />
                                    Reload Page
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                <div className="text-center text-xs text-zinc-500">
                    Position the barcode within the frame to scan.
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
