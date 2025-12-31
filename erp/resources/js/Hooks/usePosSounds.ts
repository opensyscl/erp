import { useCallback, useEffect, useState } from 'react';

interface SoundSettings {
    enabled: boolean;
    volume: number;
    scanSound: string;
    saleSound: string;
    errorSound: string;
    clickSound: string;
}

const DEFAULT_SETTINGS: SoundSettings = {
    enabled: true,
    volume: 50,
    scanSound: 'add-to-folder',
    saleSound: 'download',
    errorSound: 'delete',
    clickSound: 'toggle-theme'
};

export function usePosSounds() {
    const [settings, setSettings] = useState<SoundSettings>(DEFAULT_SETTINGS);

    useEffect(() => {
        const saved = localStorage.getItem('pos_sound_settings');
        if (saved) {
            try {
                setSettings({ ...DEFAULT_SETTINGS, ...JSON.parse(saved) });
            } catch (e) {
                console.error("Failed to parse sound settings", e);
            }
        }
    }, []);

    const playSound = useCallback((type: 'scan' | 'sale' | 'error' | 'click') => {
        if (!settings.enabled) return;

        let soundName = settings.scanSound;
        if (type === 'sale') soundName = settings.saleSound;
        if (type === 'error') soundName = settings.errorSound;
        if (type === 'click') soundName = settings.clickSound;

        // Sounds are now .wav files in root public/
        const audioPath = `/${soundName}.wav`;

        const audio = new Audio(audioPath);
        audio.volume = Math.max(0, Math.min(1, settings.volume / 100));

        audio.play().catch(err => {
            console.warn(`Could not play sound ${audioPath}:`, err);
        });
    }, [settings]);

    return {
        playScan: () => playSound('scan'),
        playSale: () => playSound('sale'),
        playError: () => playSound('error'),
        playClick: () => playSound('click'),
        settings
    };
}
