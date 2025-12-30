
export interface ImageToolOptions {

    targetInput: string | null;
    previewElement: string | null;


    format: 'webp' | 'png' | 'jpeg';
    quality: number;
    maxWidth?: number;
    maxHeight?: number;

    // Default crop dimensions
    cropWidth?: number;
    cropHeight?: number;

    aspectRatio: 'free' | '1:1' | '16:9' | '9:16' | '4:3' | '4:5' | string;


    allowRemoveBg: boolean;
    removeBgApiKey: string;


    placeholder: string;
    buttonText: string;
    modalTitle: string;

    onComplete: ((result: ImageToolResult) => void) | null;
    onCancel: (() => void) | null;
    onChange: ((result: ImageToolResult) => void) | null;
}

export interface ImageToolResult {
    file: File;
    dataUrl: string;
    blob: Blob;
    width: number;
    height: number;
    originalWidth: number;
    originalHeight: number;
    format: string;
    quality: number;
    size: number;
    originalSize: number;
    savings: number;
}

export interface CropData {
    x: number;
    y: number;
    width: number;
    height: number;
    rotate: number;
    scaleX: number;
    scaleY: number;
}

export interface FilterSettings {
    brightness: number;
    contrast: number;
    saturation: number;
    preset: 'none' | 'grayscale' | 'sepia' | 'vintage' | 'vivid';
}

export interface EditorState {
    file: File | null;
    originalDataUrl: string;
    cropData: CropData | null;
    filters: FilterSettings;
    format: 'webp' | 'png' | 'jpeg';
    quality: number;
    resizeWidth: number | null;
    resizeHeight: number | null;
}
