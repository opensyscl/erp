
const CROPPER_JS_URL = '/image-tool/lib/cropper.min.js';
const CROPPER_CSS_URL = '/image-tool/lib/cropper.min.css';

let isLoading = false;
let isLoaded = false;
const callbacks: Array<() => void> = [];


export function isCropperAvailable(): boolean {
    return typeof (window as any).Cropper !== 'undefined';
}


export function loadCropper(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (isCropperAvailable()) {
            resolve();
            return;
        }

        if (isLoading) {
            callbacks.push(resolve);
            return;
        }

        isLoading = true;

    
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = CROPPER_CSS_URL;
        document.head.appendChild(link);

        const script = document.createElement('script');
        script.src = CROPPER_JS_URL;
        script.async = true;

        script.onload = () => {
            isLoaded = true;
            isLoading = false;
            resolve();

            callbacks.forEach(cb => cb());
            callbacks.length = 0;
        };

        script.onerror = () => {
            isLoading = false;
            reject(new Error('Failed to load Cropper.js'));
        };

        document.head.appendChild(script);
    });
}


export async function ensureCropperLoaded(): Promise<void> {
    if (!isCropperAvailable()) {
        await loadCropper();
    }
}