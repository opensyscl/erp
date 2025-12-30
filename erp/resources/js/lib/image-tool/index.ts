
import { ImageToolModal } from './modal';
import { ImageToolDropzone } from './dropzone';
import type { ImageToolOptions, ImageToolResult } from './types';

export class ImageTool {
    private dropzone: ImageToolDropzone;
    private modal: ImageToolModal;
    private options: ImageToolOptions;
    private element: HTMLElement;

    constructor(element: HTMLElement | string, options: Partial<ImageToolOptions> = {}) {

        if (typeof element === 'string') {
            const el = document.querySelector<HTMLElement>(element);
            if (!el) throw new Error(`ImageTool: Element "${element}" not found`);
            this.element = el;
        } else {
            this.element = element;
        }


        this.options = {
            targetInput: this.element.dataset.target || null,
            previewElement: this.element.dataset.preview || null,
            format: (this.element.dataset.format as 'webp' | 'png' | 'jpeg') || 'webp',
            quality: parseInt(this.element.dataset.quality || '90'),
            maxWidth: parseInt(this.element.dataset.maxWidth || '0') || undefined,
            maxHeight: parseInt(this.element.dataset.maxHeight || '0') || undefined,
            aspectRatio: this.element.dataset.aspectRatio || 'free',
            allowRemoveBg: this.element.dataset.allowRemoveBg === 'true',
            removeBgApiKey: this.element.dataset.removeBgApiKey || '9fbSbQuJMi9a4GyPkvpXsdQK',
            placeholder: this.element.dataset.placeholder || 'Arrastra una imagen o haz clic',
            buttonText: this.element.dataset.buttonText || 'Seleccionar imagen',
            modalTitle: this.element.dataset.modalTitle || 'Editor de Imagen',
            onComplete: options.onComplete || null,
            onCancel: options.onCancel || null,
            onChange: options.onChange || null,
            ...options
        };

        this.modal = ImageToolModal.getInstance();


        this.dropzone = new ImageToolDropzone(this.element, this.options, (file: File) => {
            this.openEditor(file);
        });
    }

    openEditor(file: File): void {
        this.modal.open(file, this.options, (result: ImageToolResult | null) => {
            if (result) {
                this.handleResult(result);
            } else if (this.options.onCancel) {
                this.options.onCancel();
            }
        });
    }


    openFromUrl(url: string): void {
        fetch(url)
            .then(res => res.blob())
            .then(blob => {
                const filename = url.split('/').pop() || 'image.jpg';
                const file = new File([blob], filename, { type: blob.type });
                this.openEditor(file);
            })
            .catch(err => {
                console.error('ImageTool: Error loading image from URL', err);
            });
    }


    private handleResult(result: ImageToolResult): void {
        if (this.options.targetInput) {
            const input = document.querySelector<HTMLInputElement>(this.options.targetInput);
            if (input) {

                if (input.type === 'file') {
                    const dt = new DataTransfer();
                    dt.items.add(result.file);
                    input.files = dt.files;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } else {

                    input.value = result.dataUrl;
                }
            }
        }


        if (this.options.previewElement) {
            const preview = document.querySelector<HTMLElement>(this.options.previewElement);
            if (preview) {
                if (preview.tagName === 'IMG') {
                    (preview as HTMLImageElement).src = result.dataUrl;
                } else {
                    preview.style.backgroundImage = `url(${result.dataUrl})`;
                }
            }
        }


        this.dropzone.setPreview(result.dataUrl);


        if (this.options.onComplete) {
            this.options.onComplete(result);
        }

        if (this.options.onChange) {
            this.options.onChange(result);
        }
    }


    clear(): void {
        this.dropzone.clear();

        if (this.options.targetInput) {
            const input = document.querySelector<HTMLInputElement>(this.options.targetInput);
            if (input) {
                input.value = '';
            }
        }

        if (this.options.previewElement) {
            const preview = document.querySelector<HTMLElement>(this.options.previewElement);
            if (preview) {
                if (preview.tagName === 'IMG') {
                    (preview as HTMLImageElement).src = '';
                } else {
                    preview.style.backgroundImage = '';
                }
            }
        }
    }


    destroy(): void {
        this.dropzone.destroy();
    }
}

function initImageTools(): void {
    document.querySelectorAll<HTMLElement>('.image-tool-dropzone').forEach(el => {
        if (!el.dataset.imageToolInitialized) {
            new ImageTool(el);
            el.dataset.imageToolInitialized = 'true';
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initImageTools);
} else {
    initImageTools();
}

(window as any).ImageTool = ImageTool;

export { ImageToolModal, ImageToolDropzone };
export type { ImageToolOptions, ImageToolResult };
