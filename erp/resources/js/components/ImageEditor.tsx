import { useEffect, useRef, useState } from 'react';
import type { ImageToolOptions, ImageToolResult } from '@/lib/image-tool/types';

interface ImageEditorProps {
    value?: string; // Current image URL
    onChange?: (result: ImageToolResult) => void;
    onComplete?: (result: ImageToolResult) => void;
    aspectRatio?: 'free' | '1:1' | '16:9' | '9:16' | '4:3' | '4:5';
    format?: 'webp' | 'png' | 'jpeg';
    quality?: number;
    maxWidth?: number;
    maxHeight?: number;
    cropWidth?: number;  // Default crop width
    cropHeight?: number; // Default crop height
    removeBgApiKey?: string; // remove.bg API key for background removal
    placeholder?: string;
    buttonText?: string;
    className?: string;
    name?: string;
}

export default function ImageEditor({
    value,
    onChange,
    onComplete,
    aspectRatio = 'free',
    format = 'webp',
    quality = 90,
    maxWidth,
    maxHeight,
    cropWidth,
    cropHeight,
    removeBgApiKey,
    placeholder = 'Arrastra una imagen o haz clic',
    buttonText = 'Seleccionar imagen',
    className = '',
    name,
}: ImageEditorProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const imageToolRef = useRef<any>(null);
    const [preview, setPreview] = useState<string | null>(value || null);
    const [resultFile, setResultFile] = useState<File | null>(null);

    useEffect(() => {
        let mounted = true;

        const initImageTool = async () => {
            if (!containerRef.current || imageToolRef.current) return;

            // Dynamic import to avoid SSR issues
            const { ImageTool } = await import('@/lib/image-tool/index');

            if (!mounted || !containerRef.current) return;

            const options: Partial<ImageToolOptions> = {
                format,
                quality,
                aspectRatio,
                maxWidth,
                maxHeight,
                cropWidth,
                cropHeight,
                removeBgApiKey,
                placeholder,
                buttonText,
                onComplete: (result: ImageToolResult) => {
                    setPreview(result.dataUrl);
                    setResultFile(result.file);
                    onComplete?.(result);
                    onChange?.(result);
                },
            };

            imageToolRef.current = new ImageTool(containerRef.current, options);

            // If there's an initial value, show it as preview
            if (value) {
                imageToolRef.current.dropzone?.setPreview(value);
            }
        };

        initImageTool();

        return () => {
            mounted = false;
            if (imageToolRef.current) {
                imageToolRef.current.destroy?.();
                imageToolRef.current = null;
            }
        };
    }, []);

    // Update preview when value changes externally
    useEffect(() => {
        if (value && imageToolRef.current?.dropzone) {
            imageToolRef.current.dropzone.setPreview(value);
            setPreview(value);
        }
    }, [value]);

    return (
        <div className={`image-editor-wrapper ${className}`}>
            <div
                ref={containerRef}
                className="image-tool-dropzone"
                data-format={format}
                data-quality={quality}
                data-aspect-ratio={aspectRatio}
                data-max-width={maxWidth}
                data-max-height={maxHeight}
                data-placeholder={placeholder}
                data-button-text={buttonText}
            />

            {/* Hidden input for form submission */}
            {name && resultFile && (
                <input
                    type="hidden"
                    name={name}
                    value={preview || ''}
                />
            )}
        </div>
    );
}
