
import type { ImageToolOptions, ImageToolResult, FilterSettings, EditorState } from './types';
import { ensureCropperLoaded } from './loader';

declare const Cropper: any;

export class ImageToolModal {
    private static instance: ImageToolModal | null = null;
    private modal: HTMLElement | null = null;
    private cropper: any = null;
    private state: EditorState;
    private currentOptions: ImageToolOptions | null = null;
    private onCompleteCallback: ((result: ImageToolResult | null) => void) | null = null;


    private imagePreview: HTMLImageElement | null = null;
    private qualitySlider: HTMLInputElement | null = null;
    private qualityValue: HTMLElement | null = null;
    private formatButtons: NodeListOf<HTMLButtonElement> | null = null;
    private resizeW: HTMLInputElement | null = null;
    private resizeH: HTMLInputElement | null = null;


    private updateStatsTimeout: ReturnType<typeof setTimeout> | null = null;
    private isCalculating: boolean = false;

    private constructor() {
        this.state = this.getDefaultState();
        this.createModal();
    }

    static getInstance(): ImageToolModal {
        if (!ImageToolModal.instance) {
            ImageToolModal.instance = new ImageToolModal();
        }
        return ImageToolModal.instance;
    }

    private getDefaultState(): EditorState {
        return {
            file: null,
            originalDataUrl: '',
            cropData: null,
            filters: {
                brightness: 100,
                contrast: 100,
                saturation: 100,
                preset: 'none'
            },
            format: 'webp',
            quality: 90,
            resizeWidth: null,
            resizeHeight: null
        };
    }

    private createModal(): void {
        // Remove existing modal to force recreation with latest HTML
        const existingModal = document.getElementById('image-tool-modal');
        if (existingModal) {
            existingModal.remove();
        }

        const modalHtml = `
        <div id="image-tool-modal" class="it-modal it-hidden">
            <div class="it-modal-backdrop"></div>
            <div class="it-modal-container">
                <!-- Header -->
                <div class="it-modal-header">
                    <div class="it-modal-brand">
                        <img src="https://0284i2z2w3.ufs.sh/f/NcbcfBhcN02PuZQyToicelS2385fujvhWQRzgCZnKcAkqH6o" alt="CropSnap" class="it-modal-logo">
                        <h3 class="it-modal-title">CropSnap</h3>
                        <span class="it-beta-badge">BETA</span>
                    </div>
                    <button type="button" class="it-modal-close" data-action="close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="it-modal-body">
                    <!-- Sidebar -->
                    <aside class="it-sidebar">
                        <!-- Transform Section -->
                        <div class="it-section">
                            <h3 class="it-section-title">Transformar</h3>
                            <div class="it-transform-buttons">
                                <button type="button" class="it-btn-icon" data-action="rotate-left" title="Rotar -90°">
                                    <span>↺</span>
                                    <small>-90°</small>
                                </button>
                                <button type="button" class="it-btn-icon" data-action="rotate-right" title="Rotar +90°">
                                    <span>↻</span>
                                    <small>+90°</small>
                                </button>
                                <button type="button" class="it-btn-icon" data-action="flip-h" title="Espejo Horizontal">
                                    <span>↔</span>
                                    <small>Flip H</small>
                                </button>
                                <button type="button" class="it-btn-icon" data-action="flip-v" title="Espejo Vertical">
                                    <span>↕</span>
                                    <small>Flip V</small>
                                </button>
                            </div>
                        </div>

                        <!-- Aspect Ratio -->
                        <div class="it-section">
                            <h3 class="it-section-title">Proporción</h3>
                            <div class="it-ratio-buttons">
                                <button type="button" class="it-ratio-btn active" data-ratio="NaN">Libre</button>
                                <button type="button" class="it-ratio-btn" data-ratio="1">1:1</button>
                                <button type="button" class="it-ratio-btn" data-ratio="1.7777">16:9</button>
                                <button type="button" class="it-ratio-btn" data-ratio="1.3333">4:3</button>
                                <button type="button" class="it-ratio-btn" data-ratio="0.5625">9:16</button>
                                <button type="button" class="it-ratio-btn" data-ratio="0.8">4:5</button>
                            </div>
                        </div>

                        <!-- Filters Section -->
                        <div class="it-section">
                            <h3 class="it-section-title">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 2a10 10 0 0 1 0 20"/>
                                </svg>
                                Ajustes
                            </h3>
                            <div class="it-filter-sliders">
                                <div class="it-filter-group">
                                    <label>
                                        <span>Brillo</span>
                                        <span class="it-filter-value" data-filter="brightness">100%</span>
                                    </label>
                                    <input type="range" data-filter-slider="brightness" min="0" max="200" value="100">
                                </div>
                                <div class="it-filter-group">
                                    <label>
                                        <span>Contraste</span>
                                        <span class="it-filter-value" data-filter="contrast">100%</span>
                                    </label>
                                    <input type="range" data-filter-slider="contrast" min="0" max="200" value="100">
                                </div>
                                <div class="it-filter-group">
                                    <label>
                                        <span>Saturación</span>
                                        <span class="it-filter-value" data-filter="saturation">100%</span>
                                    </label>
                                    <input type="range" data-filter-slider="saturation" min="0" max="200" value="100">
                                </div>
                            </div>

                            <div class="it-filter-presets">
                                <button type="button" class="it-filter-preset active" data-preset="none">Normal</button>
                                <button type="button" class="it-filter-preset" data-preset="grayscale">B&N</button>
                                <button type="button" class="it-filter-preset" data-preset="sepia">Sepia</button>
                                <button type="button" class="it-filter-preset" data-preset="vintage">Vintage</button>
                                <button type="button" class="it-filter-preset" data-preset="vivid">Vívido</button>
                            </div>

                            <button type="button" class="it-btn-reset-filters" data-action="reset-filters">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 4v6h6M23 20v-6h-6"/>
                                    <path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/>
                                </svg>
                                Resetear filtros
                            </button>
                        </div>

                        <!-- Remove Background Section -->
                        <div class="it-section it-remove-bg-section">
                            <h3 class="it-section-title">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.24 12.24a6 6 0 0 0-8.49-8.49L5 10.5V19h8.5l6.74-6.76z"/>
                                    <line x1="16" y1="8" x2="2" y2="22"/>
                                    <line x1="17.5" y1="15" x2="9" y2="15"/>
                                </svg>
                                Fondo
                            </h3>
                            <button type="button" class="it-btn-remove-bg" data-action="remove-bg">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                Remover fondo
                                <span class="it-remove-bg-badge">AI</span>
                            </button>
                            <div class="it-remove-bg-loading it-hidden">
                                <div class="it-spinner"></div>
                                <span>Procesando...</span>
                            </div>
                        </div>

                        <!-- Export Section -->
                        <div class="it-section">
                            <h3 class="it-section-title">Exportar</h3>

                            <div class="it-input-group">
                                <label>Formato</label>
                                <div class="it-format-buttons">
                                    <button type="button" class="it-format-btn active" data-format="webp">WebP</button>
                                    <button type="button" class="it-format-btn" data-format="png">PNG</button>
                                    <button type="button" class="it-format-btn" data-format="jpeg">JPG</button>
                                </div>
                            </div>

                            <div class="it-input-group">
                                <label>
                                    Calidad
                                    <span class="it-quality-badge">90%</span>
                                </label>
                                <div class="it-slider-container">
                                    <input type="range" class="it-quality-slider" min="10" max="100" value="90">
                                    <div class="it-slider-tick" style="left: 77.78%">
                                        <div class="it-tick-line"></div>
                                        <span class="it-tick-label">Rec</span>
                                    </div>
                                </div>
                                <div class="it-range-labels">
                                    <span>Menor tamaño</span>
                                    <span>Mayor calidad</span>
                                </div>
                            </div>

                            <div class="it-input-group">
                                <label>Redimensionar (px)</label>
                                <div class="it-resize-inputs">
                                    <input type="number" class="it-resize-w" placeholder="Ancho">
                                    <span class="it-input-separator">×</span>
                                    <input type="number" class="it-resize-h" placeholder="Alto">
                                    <button type="button" class="it-btn-clear" data-action="clear-resize" title="Limpiar">×</button>
                                </div>
                            </div>

                            <!-- Size Templates -->
                            <div class="it-input-group">
                                <label>Templates</label>
                                <div class="it-size-templates">
                                    <button type="button" class="it-template-btn" data-w="1080" data-h="1080">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
                                        1080×1080
                                    </button>
                                    <button type="button" class="it-template-btn" data-w="1200" data-h="630">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="6" width="20" height="12" rx="2"/></svg>
                                        1200×630
                                    </button>
                                    <button type="button" class="it-template-btn" data-w="1080" data-h="1920">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="6" y="2" width="12" height="20" rx="2"/><line x1="9" y1="19" x2="15" y2="19"/></svg>
                                        1080×1920
                                    </button>
                                    <button type="button" class="it-template-btn" data-w="1920" data-h="1080">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="4" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="18" x2="12" y2="21"/></svg>
                                        1920×1080
                                    </button>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- Canvas Area -->
                    <main class="it-canvas-area">
                        <div class="it-editor-container">
                            <img class="it-image-preview" src="" alt="Preview">
                            <!-- Crop Dimensions Tooltip -->
                            <div class="it-crop-tooltip">
                                <span class="it-crop-dimensions">0 × 0</span>
                            </div>
                        </div>

                        <!-- Zoom Controls -->
                        <div class="it-zoom-controls">
                            <button type="button" class="it-zoom-btn" data-action="zoom-out">−</button>
                            <span class="it-zoom-level">100%</span>
                            <button type="button" class="it-zoom-btn" data-action="zoom-in">+</button>
                            <button type="button" class="it-zoom-btn" data-action="zoom-fit" title="Ajustar">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
                                </svg>
                            </button>
                            <div class="it-zoom-divider"></div>
                            <button type="button" class="it-zoom-btn it-compare-btn" data-action="toggle-compare" title="Comparar Original vs Comprimida">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <line x1="12" y1="3" x2="12" y2="21"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Compare Modal (Fullscreen overlay) -->
                        <div class="it-compare-modal it-hidden">
                            <!-- Close button -->
                            <button type="button" class="it-compare-close-btn" data-action="toggle-compare">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 6L6 18M6 6l12 12"/>
                                </svg>
                            </button>

                            <!-- Stats bar -->
                            <div class="it-compare-stats">
                                <div class="it-compare-stat-item">
                                    <span class="it-compare-stat-label">Original</span>
                                    <span class="it-compare-stat-value it-compare-original-size">-</span>
                                </div>
                                <div class="it-compare-stat-divider"></div>
                                <div class="it-compare-stat-item">
                                    <span class="it-compare-stat-label">Comprimida</span>
                                    <span class="it-compare-stat-value it-compare-compressed-size">-</span>
                                </div>
                                <div class="it-compare-stat-divider"></div>
                                <div class="it-compare-stat-item it-compare-savings">
                                    <span class="it-compare-stat-label">Ahorro</span>
                                    <span class="it-compare-stat-value it-compare-savings-value">-</span>
                                </div>
                            </div>

                            <!-- Compare container -->
                            <div class="it-compare-container">
                                <div class="it-compare-wrapper">
                                    <!-- Before label -->
                                    <div class="it-compare-label before">Before</div>
                                    <!-- After label -->
                                    <div class="it-compare-label after">After</div>

                                    <!-- Original image (right side / After) -->
                                    <img class="it-compare-img it-compare-img-original" src="" alt="Original">

                                    <!-- Compressed overlay (left side / Before) -->
                                    <div class="it-compare-overlay">
                                        <img class="it-compare-img it-compare-img-compressed" src="" alt="Comprimida">
                                    </div>

                                    <!-- Slider handle -->
                                    <div class="it-compare-slider">
                                        <div class="it-compare-line"></div>
                                        <div class="it-compare-handle">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M15 18l-6-6 6-6"/>
                                            </svg>
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M9 6l6 6-6 6"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer with quality control -->
                            <div class="it-compare-footer">
                                <div class="it-compare-quality-control">
                                    <span class="it-compare-quality-label">CALIDAD</span>
                                    <div class="it-compare-slider-container">
                                        <input type="range" class="it-compare-quality-slider" min="10" max="100" value="80">
                                        <div class="it-compare-tick" style="left: 77.78%"></div>
                                        <span class="it-compare-tick-label">80%</span>
                                    </div>
                                    <span class="it-compare-quality-value">80%</span>
                                    <button type="button" class="it-compare-update-btn" data-action="update-compare">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 4v6h6M23 20v-6h-6"/>
                                            <path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/>
                                        </svg>
                                        Actualizar
                                    </button>
                                    <div class="it-compare-divider"></div>
                                    <button type="button" class="it-compare-download-btn" data-action="download-compare">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                            <polyline points="7 10 12 15 17 10"/>
                                            <line x1="12" y1="15" x2="12" y2="3"/>
                                        </svg>
                                        Descargar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </main>
                </div>

                <!-- Footer -->
                <div class="it-modal-footer">
                    <div class="it-info-bar">
                        <span class="it-info-item">
                            <strong>Original:</strong> <span class="it-original-size">-</span>
                        </span>
                        <span class="it-info-item">
                            <strong>Resultado:</strong> <span class="it-result-size">-</span>
                        </span>
                        <span class="it-info-item it-savings">
                            <strong>Ahorro:</strong> <span class="it-savings-value">-</span>
                        </span>
                    </div>
                    <div class="it-footer-actions">
                        <button type="button" class="it-btn it-btn-secondary" data-action="cancel">
                            Cancelar
                        </button>
                        <button type="button" class="it-btn it-btn-secondary" data-action="download">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/>
                                <line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Descargar
                        </button>
                        <button type="button" class="it-btn it-btn-primary" data-action="apply">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 13l4 4L19 7"/>
                            </svg>
                            Aplicar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        `;


        document.body.insertAdjacentHTML('beforeend', modalHtml);
        this.modal = document.getElementById('image-tool-modal');

        this.bindElements();
        this.setupEventListeners();
    }

    private bindElements(): void {
        if (!this.modal) return;

        this.imagePreview = this.modal.querySelector('.it-image-preview');
        this.qualitySlider = this.modal.querySelector('.it-quality-slider');
        this.qualityValue = this.modal.querySelector('.it-quality-badge');
        this.formatButtons = this.modal.querySelectorAll('.it-format-btn');
        this.resizeW = this.modal.querySelector('.it-resize-w');
        this.resizeH = this.modal.querySelector('.it-resize-h');
    }

    private setupEventListeners(): void {
        if (!this.modal) return;

        this.modal.querySelector('.it-modal-close')?.addEventListener('click', () => this.close());
        this.modal.querySelector('.it-modal-backdrop')?.addEventListener('click', () => this.close());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !this.modal?.classList.contains('it-hidden')) {
                this.close();
            }
        });


        this.modal.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = (e.currentTarget as HTMLElement).dataset.action;
                this.handleAction(action!);
            });
        });


        this.modal.querySelectorAll('.it-ratio-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget as HTMLButtonElement;
                this.modal?.querySelectorAll('.it-ratio-btn').forEach(b => b.classList.remove('active'));
                target.classList.add('active');

                const ratio = parseFloat(target.dataset.ratio || 'NaN');
                this.cropper?.setAspectRatio(ratio);
            });
        });


        this.modal.querySelectorAll('.it-format-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget as HTMLButtonElement;
                this.modal?.querySelectorAll('.it-format-btn').forEach(b => b.classList.remove('active'));
                target.classList.add('active');

                this.state.format = target.dataset.format as 'webp' | 'png' | 'jpeg';


                this.updateStatsRealtime();
            });
        });


        this.qualitySlider?.addEventListener('input', (e) => {
            const value = parseInt((e.target as HTMLInputElement).value);
            this.state.quality = this.applyMagneticSnap(value);
            (e.target as HTMLInputElement).value = this.state.quality.toString();
            if (this.qualityValue) {
                this.qualityValue.textContent = `${this.state.quality}%`;
            }


            this.updateStatsRealtime();
        });


        this.modal.querySelectorAll('[data-filter-slider]').forEach(slider => {
            slider.addEventListener('input', (e) => {
                const target = e.target as HTMLInputElement;
                const filter = target.dataset.filterSlider as keyof Omit<FilterSettings, 'preset'>;
                const value = parseInt(target.value);

                this.state.filters[filter] = value;

                const valueEl = this.modal?.querySelector(`[data-filter="${filter}"]`);
                if (valueEl) valueEl.textContent = `${value}%`;

                this.applyFilters();
            });
        });


        this.modal.querySelectorAll('.it-filter-preset').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget as HTMLButtonElement;
                this.modal?.querySelectorAll('.it-filter-preset').forEach(b => b.classList.remove('active'));
                target.classList.add('active');

                this.state.filters.preset = target.dataset.preset as FilterSettings['preset'];
                this.applyFilters();
            });
        });


        this.resizeW?.addEventListener('input', () => {
            this.state.resizeWidth = parseInt(this.resizeW!.value) || null;
        });

        this.resizeH?.addEventListener('input', () => {
            this.state.resizeHeight = parseInt(this.resizeH!.value) || null;
        });


        this.modal.querySelectorAll('.it-template-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.currentTarget as HTMLButtonElement;
                const w = parseInt(target.dataset.w || '0');
                const h = parseInt(target.dataset.h || '0');

                if (this.resizeW) this.resizeW.value = w.toString();
                if (this.resizeH) this.resizeH.value = h.toString();

                this.state.resizeWidth = w;
                this.state.resizeHeight = h;
            });
        });
    }

    private handleAction(action: string): void {
        switch (action) {
            case 'close':
            case 'cancel':
                this.close();
                break;
            case 'apply':
                this.apply();
                break;
            case 'download':
                this.download();
                break;
            case 'rotate-left':
                this.cropper?.rotate(-90);
                break;
            case 'rotate-right':
                this.cropper?.rotate(90);
                break;
            case 'flip-h':
                const scaleX = this.cropper?.getData().scaleX || 1;
                this.cropper?.scaleX(-scaleX);
                break;
            case 'flip-v':
                const scaleY = this.cropper?.getData().scaleY || 1;
                this.cropper?.scaleY(-scaleY);
                break;
            case 'zoom-in':
                this.cropper?.zoom(0.1);
                break;
            case 'zoom-out':
                this.cropper?.zoom(-0.1);
                break;
            case 'zoom-fit':
                this.cropper?.reset();
                break;
            case 'reset-filters':
                this.resetFilters();
                break;
            case 'clear-resize':
                if (this.resizeW) this.resizeW.value = '';
                if (this.resizeH) this.resizeH.value = '';
                this.state.resizeWidth = null;
                this.state.resizeHeight = null;
                break;
            case 'toggle-compare':
                this.toggleCompare();
                break;
            case 'update-compare':
                this.updateCompare();
                break;
            case 'download-compare':
                this.downloadCompare();
                break;
            case 'remove-bg':
                this.removeBackground();
                break;
        }
    }

    private applyMagneticSnap(value: number): number {
        const SNAP_VALUE = 80;
        const SNAP_RANGE = 2;

        if (Math.abs(value - SNAP_VALUE) <= SNAP_RANGE) {
            return SNAP_VALUE;
        }
        return value;
    }

    private applyFilters(): void {
        if (!this.imagePreview) return;

        const { brightness, contrast, saturation, preset } = this.state.filters;

        let filterString = `brightness(${brightness}%) contrast(${contrast}%) saturate(${saturation}%)`;

        switch (preset) {
            case 'grayscale':
                filterString += ' grayscale(100%)';
                break;
            case 'sepia':
                filterString += ' sepia(80%)';
                break;
            case 'vintage':
                filterString += ' sepia(30%) saturate(120%)';
                break;
            case 'vivid':
                filterString += ' saturate(150%) contrast(110%)';
                break;
        }


        const cropperImage = this.modal?.querySelector('.cropper-view-box img') as HTMLImageElement;
        const cropperCanvas = this.modal?.querySelector('.cropper-canvas img') as HTMLImageElement;

        if (cropperImage) cropperImage.style.filter = filterString;
        if (cropperCanvas) cropperCanvas.style.filter = filterString;
    }

    private resetFilters(): void {
        this.state.filters = {
            brightness: 100,
            contrast: 100,
            saturation: 100,
            preset: 'none'
        };


        this.modal?.querySelectorAll<HTMLInputElement>('[data-filter-slider]').forEach(slider => {
            slider.value = '100';
        });


        this.modal?.querySelectorAll('.it-filter-value').forEach(el => {
            el.textContent = '100%';
        });


        this.modal?.querySelectorAll('.it-filter-preset').forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-preset') === 'none') {
                btn.classList.add('active');
            }
        });

        this.applyFilters();
    }


    async open(
        file: File,
        options: ImageToolOptions,
        onComplete: (result: ImageToolResult | null) => void
    ): Promise<void> {
        if (!this.modal || !this.imagePreview) return;


        await ensureCropperLoaded();

        this.currentOptions = options;
        this.onCompleteCallback = onComplete;
        this.state = this.getDefaultState();
        this.state.file = file;
        this.state.format = options.format;
        this.state.quality = options.quality;


        const reader = new FileReader();
        reader.onload = (e) => {
            const dataUrl = e.target?.result as string;
            this.state.originalDataUrl = dataUrl;

            if (this.imagePreview) {
                this.imagePreview.src = dataUrl;
            }


            this.imagePreview!.onload = () => {
                this.initCropper();
                this.updateInfoBar(file.size);
            };
        };
        reader.readAsDataURL(file);


        this.modal.classList.remove('it-hidden');
        document.body.style.overflow = 'hidden';


        this.applyInitialOptions(options);
    }

    private applyInitialOptions(options: ImageToolOptions): void {

        this.modal?.querySelectorAll('.it-format-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-format') === options.format) {
                btn.classList.add('active');
            }
        });


        if (this.qualitySlider) {
            this.qualitySlider.value = options.quality.toString();
        }
        if (this.qualityValue) {
            this.qualityValue.textContent = `${options.quality}%`;
        }


        if (options.maxWidth && this.resizeW) {
            this.resizeW.value = options.maxWidth.toString();
            this.state.resizeWidth = options.maxWidth;
        }
        if (options.maxHeight && this.resizeH) {
            this.resizeH.value = options.maxHeight.toString();
            this.state.resizeHeight = options.maxHeight;
        }


        if (options.aspectRatio !== 'free') {
            const ratioMap: Record<string, string> = {
                '1:1': '1',
                '16:9': '1.7777',
                '9:16': '0.5625',
                '4:3': '1.3333',
                '4:5': '0.8'
            };
            const ratioValue = ratioMap[options.aspectRatio] || 'NaN';

            this.modal?.querySelectorAll('.it-ratio-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('data-ratio') === ratioValue) {
                    btn.classList.add('active');
                }
            });
        }
    }

    private initCropper(): void {
        if (this.cropper) {
            this.cropper.destroy();
        }

        if (!this.imagePreview) return;


        let aspectRatio = NaN;
        if (this.currentOptions?.aspectRatio !== 'free') {
            const ratioMap: Record<string, number> = {
                '1:1': 1,
                '16:9': 16/9,
                '9:16': 9/16,
                '4:3': 4/3,
                '4:5': 4/5
            };
            aspectRatio = ratioMap[this.currentOptions?.aspectRatio || ''] || NaN;
        }

        this.cropper = new Cropper(this.imagePreview, {
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 1,
            restore: false,
            guides: true,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            aspectRatio,
            ready: () => {
                this.updateZoomLevel();
                this.updateCropDimensions();

                // Set initial crop dimensions if provided
                if (this.currentOptions?.cropWidth && this.currentOptions?.cropHeight) {
                    const containerData = this.cropper.getContainerData();
                    const cropWidth = Math.min(this.currentOptions.cropWidth, containerData.width);
                    const cropHeight = Math.min(this.currentOptions.cropHeight, containerData.height);

                    this.cropper.setCropBoxData({
                        width: cropWidth,
                        height: cropHeight,
                        left: (containerData.width - cropWidth) / 2,
                        top: (containerData.height - cropHeight) / 2
                    });
                }
            },
            zoom: () => {
                this.updateZoomLevel();
            },
            crop: () => {
                this.updateCropDimensions();
            },
            cropmove: () => {
                this.updateCropDimensions();
            }
        });
    }


    private updateCropDimensions(): void {
        const dimensionsEl = this.modal?.querySelector('.it-crop-dimensions');
        if (!dimensionsEl || !this.cropper) return;

        const data = this.cropper.getData(true);
        const width = Math.round(data.width);
        const height = Math.round(data.height);

        dimensionsEl.textContent = `${width} × ${height}`;
    }

    private updateZoomLevel(): void {
        const zoomLevelEl = this.modal?.querySelector('.it-zoom-level');
        if (zoomLevelEl && this.cropper) {
            const data = this.cropper.getCanvasData();
            const imageData = this.cropper.getImageData();
            const zoom = Math.round((data.width / imageData.naturalWidth) * 100);
            zoomLevelEl.textContent = `${zoom}%`;
        }
    }

    private updateInfoBar(originalSize: number): void {
        const originalSizeEl = this.modal?.querySelector('.it-original-size');
        if (originalSizeEl) {
            originalSizeEl.textContent = this.formatFileSize(originalSize);
        }
    }

    private formatFileSize(bytes: number): string {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }


    private updateStatsRealtime(): void {

        if (this.updateStatsTimeout) {
            clearTimeout(this.updateStatsTimeout);
        }


        const resultSizeEl = this.modal?.querySelector('.it-result-size');
        const savingsEl = this.modal?.querySelector('.it-savings-value');

        if (resultSizeEl && !this.isCalculating) {
            resultSizeEl.textContent = '...';
        }
        if (savingsEl && !this.isCalculating) {
            savingsEl.textContent = '...';
        }


        this.updateStatsTimeout = setTimeout(async () => {
            await this.calculateRealtimeStats();
        }, 150);
    }


    private async calculateRealtimeStats(): Promise<void> {
        if (!this.cropper || !this.state.file || this.isCalculating) return;

        this.isCalculating = true;

        try {

            const cropperCanvas = this.cropper.getCroppedCanvas({
                maxWidth: this.state.resizeWidth || 1920,
                maxHeight: this.state.resizeHeight || 1080,
                fillColor: this.state.format === 'jpeg' ? '#fff' : undefined,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'medium'
            });

            if (!cropperCanvas) {
                this.isCalculating = false;
                return;
            }


            const filterCanvas = this.applyFiltersToCanvas(cropperCanvas);


            const format = this.state.format;
            let mimeType: string;

            switch (format) {
                case 'jpeg':
                    mimeType = 'image/jpeg';
                    break;
                case 'png':
                    mimeType = 'image/png';
                    break;
                case 'webp':
                default:
                    mimeType = 'image/webp';
                    break;
            }


            const quality = format === 'png' ? undefined : this.state.quality / 100;

            filterCanvas.toBlob((blob) => {
                this.isCalculating = false;

                if (!blob || !this.state.file) return;

                const resultSize = blob.size;
                const originalSize = this.state.file.size;
                const savings = ((originalSize - resultSize) / originalSize) * 100;


                const resultSizeEl = this.modal?.querySelector('.it-result-size');
                const savingsEl = this.modal?.querySelector('.it-savings-value');

                if (resultSizeEl) {
                    resultSizeEl.textContent = this.formatFileSize(resultSize);
                }

                if (savingsEl) {
                    const el = savingsEl as HTMLElement;

                    if (savings >= 0) {
                        savingsEl.textContent = `${savings.toFixed(1)}%`;
                        el.style.color = 'var(--it-success, #10b981)';
                    } else {
                        savingsEl.textContent = `+${Math.abs(savings).toFixed(1)}%`;
                        el.style.color = 'var(--it-warning, #f59e0b)';
                    }
                }
            }, mimeType, quality);
        } catch (error) {
            console.error('Error calculating realtime stats:', error);
            this.isCalculating = false;
        }
    }

    /**
     * Toggle compare modal - Original vs Compressed
     */
    private async toggleCompare(): Promise<void> {
        const compareModal = this.modal?.querySelector('.it-compare-modal');
        const compareBtn = this.modal?.querySelector('.it-compare-btn');

        if (!compareModal || !this.cropper || !this.state.file) return;

        const isHidden = compareModal.classList.contains('it-hidden');

        if (isHidden) {
            // Sync quality slider with main quality
            const qualitySlider = compareModal.querySelector('.it-compare-quality-slider') as HTMLInputElement;
            const qualityValue = compareModal.querySelector('.it-compare-quality-value');
            if (qualitySlider) {
                qualitySlider.value = String(this.state.quality);
            }
            if (qualityValue) {
                qualityValue.textContent = `${this.state.quality}%`;
            }

            // Generate images for comparison
            await this.generateCompareImages();
            compareModal.classList.remove('it-hidden');
            compareBtn?.classList.add('active');
            this.initCompareSlider();
            this.initCompareQualitySlider();
        } else {
            // Closing - sync quality back to main tool
            const qualitySlider = compareModal.querySelector('.it-compare-quality-slider') as HTMLInputElement;
            if (qualitySlider) {
                const newQuality = parseInt(qualitySlider.value);

                // Update main state
                this.state.quality = newQuality;

                // Update main quality slider
                if (this.qualitySlider) {
                    this.qualitySlider.value = String(newQuality);
                }

                // Update main quality badge
                if (this.qualityValue) {
                    this.qualityValue.textContent = `${newQuality}%`;
                }

                // Update realtime stats with new quality
                this.updateStatsRealtime();
            }

            compareModal.classList.add('it-hidden');
            compareBtn?.classList.remove('active');
        }
    }

    /**
     * Generate original and compressed images for comparison
     */
    private async generateCompareImages(): Promise<void> {
        if (!this.cropper || !this.state.file) return;

        const originalImg = this.modal?.querySelector('.it-compare-img-original') as HTMLImageElement;
        const compressedImg = this.modal?.querySelector('.it-compare-img-compressed') as HTMLImageElement;

        if (!originalImg || !compressedImg) return;

        // Get cropped canvas
        const cropperCanvas = this.cropper.getCroppedCanvas({
            maxWidth: this.state.resizeWidth || 1920,
            maxHeight: this.state.resizeHeight || 1080,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });

        if (!cropperCanvas) return;

        // Original: PNG full quality
        const originalUrl = cropperCanvas.toDataURL('image/png');
        originalImg.src = originalUrl;

        // Get compare quality from slider
        const qualitySlider = this.modal?.querySelector('.it-compare-quality-slider') as HTMLInputElement;
        const compareQuality = qualitySlider ? parseInt(qualitySlider.value) : this.state.quality;

        // Compressed: Apply format and quality
        const format = this.state.format;
        let mimeType: string;

        switch (format) {
            case 'jpeg':
                mimeType = 'image/jpeg';
                break;
            case 'png':
                mimeType = 'image/png';
                break;
            case 'webp':
            default:
                mimeType = 'image/webp';
                break;
        }

        const quality = format === 'png' ? undefined : compareQuality / 100;

        // Apply filters
        const filterCanvas = this.applyFiltersToCanvas(cropperCanvas);

        // Get blob to calculate size
        filterCanvas.toBlob((blob) => {
            if (!blob || !this.state.file) return;

            const compressedUrl = URL.createObjectURL(blob);
            compressedImg.src = compressedUrl;

            // Update stats
            const originalSize = this.state.file.size;
            const compressedSize = blob.size;
            const savings = ((originalSize - compressedSize) / originalSize) * 100;

            const originalSizeEl = this.modal?.querySelector('.it-compare-original-size');
            const compressedSizeEl = this.modal?.querySelector('.it-compare-compressed-size');
            const savingsEl = this.modal?.querySelector('.it-compare-savings-value');

            if (originalSizeEl) {
                originalSizeEl.textContent = this.formatFileSize(originalSize);
            }
            if (compressedSizeEl) {
                compressedSizeEl.textContent = this.formatFileSize(compressedSize);
            }
            if (savingsEl) {
                if (savings >= 0) {
                    savingsEl.textContent = `-${savings.toFixed(0)}%`;
                } else {
                    savingsEl.textContent = `+${Math.abs(savings).toFixed(0)}%`;
                }
            }
        }, mimeType, quality);
    }

    /**
     * Initialize compare slider drag functionality
     */
    private initCompareSlider(): void {
        const wrapper = this.modal?.querySelector('.it-compare-wrapper');
        const overlay = this.modal?.querySelector('.it-compare-overlay') as HTMLElement;
        const slider = this.modal?.querySelector('.it-compare-slider') as HTMLElement;

        if (!wrapper || !overlay || !slider) return;

        let isDragging = false;

        const updateSlider = (e: MouseEvent | TouchEvent) => {
            const rect = wrapper.getBoundingClientRect();
            const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
            let x = clientX - rect.left;
            x = Math.max(0, Math.min(x, rect.width));
            const percentage = (x / rect.width) * 100;
            overlay.style.width = `${percentage}%`;
            slider.style.left = `${percentage}%`;
        };

        const onMouseDown = (e: MouseEvent | TouchEvent) => {
            isDragging = true;
            updateSlider(e);
        };

        const onMouseMove = (e: MouseEvent | TouchEvent) => {
            if (!isDragging) return;
            updateSlider(e);
        };

        const onMouseUp = () => {
            isDragging = false;
        };

        // Add listeners
        wrapper.addEventListener('mousedown', onMouseDown as EventListener);
        document.addEventListener('mousemove', onMouseMove as EventListener);
        document.addEventListener('mouseup', onMouseUp);

        // Touch support
        wrapper.addEventListener('touchstart', onMouseDown as EventListener);
        document.addEventListener('touchmove', onMouseMove as EventListener);
        document.addEventListener('touchend', onMouseUp);
    }

    /**
     * Initialize quality slider in compare modal
     */
    private initCompareQualitySlider(): void {
        const qualitySlider = this.modal?.querySelector('.it-compare-quality-slider') as HTMLInputElement;
        const qualityValue = this.modal?.querySelector('.it-compare-quality-value');

        if (!qualitySlider) return;

        qualitySlider.addEventListener('input', () => {
            const value = qualitySlider.value;
            if (qualityValue) {
                qualityValue.textContent = `${value}%`;
            }
        });
    }

    /**
     * Update comparison with new quality
     */
    private async updateCompare(): Promise<void> {
        await this.generateCompareImages();
    }

    /**
     * Download from compare modal
     */
    private async downloadCompare(): Promise<void> {
        if (!this.cropper || !this.state.file) return;

        const qualitySlider = this.modal?.querySelector('.it-compare-quality-slider') as HTMLInputElement;
        const compareQuality = qualitySlider ? parseInt(qualitySlider.value) : this.state.quality;

        const cropperCanvas = this.cropper.getCroppedCanvas({
            maxWidth: this.state.resizeWidth || 1920,
            maxHeight: this.state.resizeHeight || 1080,
            fillColor: this.state.format === 'jpeg' ? '#fff' : undefined,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });

        if (!cropperCanvas) return;

        const filterCanvas = this.applyFiltersToCanvas(cropperCanvas);

        const format = this.state.format;
        let mimeType: string;
        let extension: string;

        switch (format) {
            case 'jpeg':
                mimeType = 'image/jpeg';
                extension = 'jpg';
                break;
            case 'png':
                mimeType = 'image/png';
                extension = 'png';
                break;
            case 'webp':
            default:
                mimeType = 'image/webp';
                extension = 'webp';
                break;
        }

        const quality = format === 'png' ? undefined : compareQuality / 100;

        filterCanvas.toBlob((blob) => {
            if (!blob) return;

            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;

            const originalName = this.state.file?.name.replace(/\.[^/.]+$/, '') || 'image';
            a.download = `${originalName}-cropsnap.${extension}`;

            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }, mimeType, quality);
    }


    /**
     * Remove background using remove.bg API
     */
    private async removeBackground(): Promise<void> {
        if (!this.cropper || !this.state.file) {
            alert('Primero carga una imagen');
            return;
        }

        // Use configured API key or fallback to default
        const apiKey = this.currentOptions?.removeBgApiKey || '9fbSbQuJMi9a4GyPkvpXsdQK';

        if (!confirm('¿Remover el fondo de la imagen? Esto usa la API de remove.bg')) return;

        const loadingEl = this.modal?.querySelector('.it-remove-bg-loading');
        const btnEl = this.modal?.querySelector('.it-btn-remove-bg') as HTMLButtonElement;

        if (loadingEl) loadingEl.classList.remove('it-hidden');
        if (btnEl) btnEl.disabled = true;

        try {
            const apiUrl = 'https://api.remove.bg/v1.0/removebg';

            const formData = new FormData();
            formData.append('image_file', this.state.file);
            formData.append('size', 'auto');

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'X-Api-Key': apiKey },
                body: formData
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.errors?.[0]?.title || 'Error procesando imagen');
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);

            // Update the image in the editor
            if (this.imagePreview) {
                this.imagePreview.src = url;
            }

            // Reinitialize cropper with new image
            if (this.cropper) {
                this.cropper.destroy();
            }

            // Update state file with new blob
            const newFile = new File([blob], this.state.file.name.replace(/\.[^.]+$/, '-nobg.png'), { type: 'image/png' });
            this.state.file = newFile;
            this.state.originalDataUrl = url;

            // Wait for image to load then reinit cropper
            this.imagePreview!.onload = () => {
                this.initCropper();
                this.updateInfoBar(newFile.size);
            };

        } catch (error: any) {
            console.error('Error:', error);
            alert('Error al remover fondo: ' + error.message);
        } finally {
            if (loadingEl) loadingEl.classList.add('it-hidden');
            if (btnEl) btnEl.disabled = false;
        }
    }


    close(): void {
        if (!this.modal) return;

        this.modal.classList.add('it-hidden');
        document.body.style.overflow = '';

        if (this.cropper) {
            this.cropper.destroy();
            this.cropper = null;
        }

        if (this.onCompleteCallback) {
            this.onCompleteCallback(null);
        }
    }


    private async apply(): Promise<void> {
        if (!this.cropper || !this.state.file) return;

        try {
            const result = await this.processImage();


            const resultSizeEl = this.modal?.querySelector('.it-result-size');
            const savingsEl = this.modal?.querySelector('.it-savings-value');

            if (resultSizeEl) {
                resultSizeEl.textContent = this.formatFileSize(result.size);
            }

            if (savingsEl) {
                savingsEl.textContent = `${result.savings.toFixed(1)}%`;
            }


            this.modal?.classList.add('it-hidden');
            document.body.style.overflow = '';

            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }

            if (this.onCompleteCallback) {
                this.onCompleteCallback(result);
            }
        } catch (error) {
            console.error('ImageTool: Error processing image', error);
        }
    }

    /**
     * Descarga la imagen procesada
     */
    private async download(): Promise<void> {
        if (!this.cropper || !this.state.file) return;

        try {
            const result = await this.processImage();

            // Crear link de descarga
            const link = document.createElement('a');
            link.href = result.dataUrl;

            // Nombre del archivo
            const originalName = this.state.file.name.replace(/\.[^.]+$/, '');
            const format = this.state.format === 'jpeg' ? 'jpg' : this.state.format;
            link.download = `${originalName}-cropsnap.${format}`;

            // Trigger download
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            console.log('CropSnap: Image downloaded', result.file.name);
        } catch (error) {
            console.error('CropSnap: Error downloading image', error);
        }
    }

    private async processImage(): Promise<ImageToolResult> {
        return new Promise((resolve, reject) => {
            if (!this.cropper || !this.state.file) {
                reject(new Error('No cropper or file'));
                return;
            }


            const format = this.state.format;
            let mimeType: string;
            let extension: string;

            switch (format) {
                case 'jpeg':
                    mimeType = 'image/jpeg';
                    extension = 'jpg';
                    break;
                case 'png':
                    mimeType = 'image/png';
                    extension = 'png';
                    break;
                case 'webp':
                default:
                    mimeType = 'image/webp';
                    extension = 'webp';
                    break;
            }

            console.log('ImageTool: Exporting as', format, mimeType, extension);


            const cropperCanvas = this.cropper.getCroppedCanvas({
                maxWidth: this.state.resizeWidth || undefined,
                maxHeight: this.state.resizeHeight || undefined,
                fillColor: format === 'jpeg' ? '#fff' : undefined,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });


            const finalCanvas = this.applyFiltersToCanvas(cropperCanvas);


            const quality = format === 'png' ? undefined : this.state.quality / 100;

            finalCanvas.toBlob((blob) => {
                if (!blob) {
                    reject(new Error('Failed to create blob'));
                    return;
                }

                console.log('ImageTool: Blob created, type:', blob.type, 'size:', blob.size);


                const originalName = this.state.file!.name.replace(/\.[^.]+$/, '');
                const filename = `${originalName}.${extension}`;
                const file = new File([blob], filename, { type: mimeType });


                const reader = new FileReader();
                reader.onload = (e) => {
                    const result: ImageToolResult = {
                        file,
                        dataUrl: e.target?.result as string,
                        blob,
                        width: finalCanvas.width,
                        height: finalCanvas.height,
                        originalWidth: this.cropper!.getImageData().naturalWidth,
                        originalHeight: this.cropper!.getImageData().naturalHeight,
                        format: format,
                        quality: this.state.quality,
                        size: blob.size,
                        originalSize: this.state.file!.size,
                        savings: ((this.state.file!.size - blob.size) / this.state.file!.size) * 100
                    };
                    resolve(result);
                };
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            }, mimeType, quality);
        });
    }

    private applyFiltersToCanvas(sourceCanvas: HTMLCanvasElement): HTMLCanvasElement {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d')!;

        canvas.width = sourceCanvas.width;
        canvas.height = sourceCanvas.height;


        const { brightness, contrast, saturation, preset } = this.state.filters;

        let filterString = `brightness(${brightness}%) contrast(${contrast}%) saturate(${saturation}%)`;

        switch (preset) {
            case 'grayscale':
                filterString += ' grayscale(100%)';
                break;
            case 'sepia':
                filterString += ' sepia(80%)';
                break;
            case 'vintage':
                filterString += ' sepia(30%) saturate(120%)';
                break;
            case 'vivid':
                filterString += ' saturate(150%) contrast(110%)';
                break;
        }

        ctx.filter = filterString;
        ctx.drawImage(sourceCanvas, 0, 0);

        return canvas;
    }
}
