import { ref } from 'vue';

const STORAGE_KEY = 'masala_pos_sound_v1';

function readInitialSoundPref() {
    if (typeof window === 'undefined') {
        return true;
    }
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        return stored === null ? true : stored === 'true';
    } catch {
        return true;
    }
}

const isSoundEnabled = ref(readInitialSoundPref());
let audioCtx = null;

function getContext() {
    if (typeof window === 'undefined') {
        return null;
    }
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) {
        return null;
    }
    if (!audioCtx) {
        audioCtx = new Ctx();
    }
    if (audioCtx.state === 'suspended') {
        audioCtx.resume().catch(() => {});
    }
    return audioCtx;
}

function playTone(frequency, durationSeconds, type = 'sine', gainLevel = 0.06, startOffset = 0) {
    if (!isSoundEnabled.value) {
        return;
    }
    try {
        const ctx = getContext();
        if (!ctx) {
            return;
        }
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        const startAt = ctx.currentTime + startOffset;

        osc.type = type;
        osc.frequency.setValueAtTime(frequency, startAt);

        gain.gain.setValueAtTime(gainLevel, startAt);
        gain.gain.exponentialRampToValueAtTime(0.0001, startAt + durationSeconds);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(startAt);
        osc.stop(startAt + durationSeconds);
    } catch {
        // Ignore browser autoplay restrictions
    }
}

export function usePosAudio() {
    const toggleSound = () => {
        isSoundEnabled.value = !isSoundEnabled.value;
        try {
            window.localStorage.setItem(STORAGE_KEY, String(isSoundEnabled.value));
        } catch {
            // Ignore storage errors
        }
    };

    const playScanBeep = () => {
        playTone(1320, 0.065, 'triangle', 0.07, 0);
    };

    const playSuccessChime = () => {
        playTone(880, 0.09, 'sine', 0.07, 0);
        playTone(1174.66, 0.14, 'sine', 0.08, 0.08);
    };

    const playErrorBuzz = () => {
        playTone(220, 0.12, 'sawtooth', 0.05, 0);
        playTone(185, 0.14, 'sawtooth', 0.05, 0.1);
    };

    return {
        isSoundEnabled,
        toggleSound,
        playScanBeep,
        playSuccessChime,
        playErrorBuzz,
    };
}

