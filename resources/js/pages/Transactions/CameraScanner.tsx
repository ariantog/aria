import { X, Camera, RefreshCcw, AlertCircle, Loader2, Info } from 'lucide-react';
import { useState, useEffect } from 'react';
import { useZxing } from 'react-zxing';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    const [isInitializing, setIsInitializing] = useState(false);

    // Explicitly check for camera permissions when opening
    useEffect(() => {
        if (isOpen) {
            setError(null);
            setIsInitializing(true);
            
            // Check if mediaDevices is supported
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                setError('Your browser does not support camera access. Please use a modern browser (Chrome, Firefox, Safari).');
                setIsInitializing(false);
                return;
            }

            // Try to trigger the permission prompt manually if useZxing fails to do so
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                .then((stream) => {
                    // Success! We can stop the stream immediately as useZxing will manage its own
                    stream.getTracks().forEach(track => track.stop());
                    setIsInitializing(false);
                })
                .catch((err) => {
                    console.error('Camera permission error:', err);
                    setIsInitializing(false);
                    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                        setError('Camera permission denied. Please click the camera icon in your browser address bar to allow access.');
                    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                        setError('No camera found on this device.');
                    } else {
                        setError(`Camera Error: ${err.message || 'Unknown error'}`);
                    }
                });
        }
    }, [isOpen]);

    const { ref } = useZxing({
        constraints: {
            video: { facingMode: 'environment' }
        },
        onDecodeResult(result) {
            if (!isPaused) {
                console.log('Decoded barcode:', result.getText());
                onScan(result.getText());
                setIsPaused(true);
                setTimeout(() => setIsPaused(false), 2000);
            }
        },
        onError(err) {
            // Log ZXing errors but don't overwrite manual permission errors unless critical
            console.warn('ZXing loop error:', err);
        },
        paused: isPaused || !isOpen || !!error || isInitializing,
    });

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Camera className="h-4 w-4 text-blue-600" />
                        Barcode Scanner
                    </DialogTitle>
                </DialogHeader>
                
                <div className="space-y-4">
                    <div className="relative aspect-video overflow-hidden rounded-lg bg-zinc-950 border border-zinc-800">
                        {isInitializing ? (
                            <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 text-zinc-400">
                                <Loader2 className="h-8 w-8 animate-spin" />
                                <span className="text-sm font-medium">Requesting camera...</span>
                            </div>
                        ) : error ? (
                            <div className="absolute inset-0 flex items-center justify-center bg-red-950/20 p-6 text-center">
                                <div className="space-y-4">
                                    <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-900/20">
                                        <AlertCircle className="h-6 w-6 text-red-500" />
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-sm font-bold text-red-500">Access Failed</p>
                                        <p className="text-xs text-zinc-400 leading-relaxed">{error}</p>
                                    </div>
                                    <Button 
                                        variant="outline" 
                                        size="sm" 
                                        onClick={() => window.location.reload()}
                                        className="h-8 text-xs bg-zinc-900 border-zinc-800 hover:bg-zinc-800"
                                    >
                                        <RefreshCcw className="mr-2 h-3 w-3" />
                                        Reload Browser
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <>
                                <video
                                    ref={ref}
                                    muted
                                    playsInline
                                    className="h-full w-full object-cover"
                                />
                                
                                {/* Overlay/Target Area */}
                                <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div className="h-32 w-64 border-2 border-blue-500/50 rounded-lg shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]">
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
                            </>
                        )}
                    </div>

                    {!error && !isInitializing && (
                        <Alert className="bg-blue-50/50 border-blue-100 dark:bg-blue-900/10 dark:border-blue-900/30">
                            <Info className="h-4 w-4 text-blue-600" />
                            <AlertDescription className="text-[11px] text-zinc-600 dark:text-zinc-400">
                                <span className="font-bold text-blue-700 dark:text-blue-400 uppercase mr-1">Pro Tip:</span>
                                Hold the barcode steady inside the frame. Ensure good lighting for faster detection.
                            </AlertDescription>
                        </Alert>
                    )}
                </div>

                <DialogFooter className="mt-2">
                    <Button variant="ghost" onClick={onClose} className="text-zinc-500">
                        Cancel
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
