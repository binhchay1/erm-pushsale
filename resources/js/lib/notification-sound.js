/**
 * Notification sounds (Web Audio — no asset files).
 * Lead / order → coin-count cascade; other types → short distinct cues.
 */

let sharedCtx = null;
let unlockBound = false;

function audioContext() {
    if (typeof window === 'undefined') return null;
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    if (!sharedCtx || sharedCtx.state === 'closed') {
        sharedCtx = new Ctx();
    }
    return sharedCtx;
}

/** Resume AudioContext after first user gesture (browser autoplay policy). */
export function unlockNotificationAudio() {
    if (unlockBound || typeof window === 'undefined') return;
    unlockBound = true;
    const resume = () => {
        const ctx = audioContext();
        if (ctx?.state === 'suspended') {
            ctx.resume().catch(() => {});
        }
    };
    const once = () => {
        resume();
        window.removeEventListener('pointerdown', once);
        window.removeEventListener('keydown', once);
    };
    window.addEventListener('pointerdown', once, { passive: true });
    window.addEventListener('keydown', once);
}

function tone(ctx, {
    frequency,
    start,
    duration = 0.08,
    type = 'sine',
    gain = 0.12,
    detune = 0,
}) {
    const osc = ctx.createOscillator();
    const amp = ctx.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(frequency, start);
    if (detune) osc.detune.setValueAtTime(detune, start);
    amp.gain.setValueAtTime(0.0001, start);
    amp.gain.exponentialRampToValueAtTime(Math.max(0.0002, gain), start + 0.012);
    amp.gain.exponentialRampToValueAtTime(0.0001, start + duration);
    osc.connect(amp);
    amp.connect(ctx.destination);
    osc.start(start);
    osc.stop(start + duration + 0.02);
}

/** Cascading metallic plinks — money / coin count. */
function playCoinCount(ctx, when) {
    const hits = 7;
    for (let i = 0; i < hits; i += 1) {
        const t = when + i * 0.075;
        const base = 980 + i * 95;
        tone(ctx, { frequency: base, start: t, duration: 0.07, type: 'triangle', gain: 0.14 });
        tone(ctx, { frequency: base * 1.55, start: t + 0.01, duration: 0.05, type: 'sine', gain: 0.08 });
        tone(ctx, { frequency: base * 2.1, start: t + 0.018, duration: 0.035, type: 'square', gain: 0.035 });
    }
}

/** Soft two-tone ding (approvals / reminders). */
function playSoftDing(ctx, when) {
    tone(ctx, { frequency: 660, start: when, duration: 0.14, type: 'sine', gain: 0.11 });
    tone(ctx, { frequency: 880, start: when + 0.11, duration: 0.18, type: 'sine', gain: 0.1 });
}

/** Bright success chime. */
function playSuccess(ctx, when) {
    tone(ctx, { frequency: 523.25, start: when, duration: 0.12, type: 'sine', gain: 0.1 });
    tone(ctx, { frequency: 659.25, start: when + 0.1, duration: 0.12, type: 'sine', gain: 0.1 });
    tone(ctx, { frequency: 783.99, start: when + 0.2, duration: 0.22, type: 'sine', gain: 0.11 });
}

/** Short attention beep. */
function playAlert(ctx, when) {
    tone(ctx, { frequency: 440, start: when, duration: 0.1, type: 'square', gain: 0.07 });
    tone(ctx, { frequency: 349, start: when + 0.12, duration: 0.14, type: 'square', gain: 0.06 });
}

/** Soft message pop. */
function playPop(ctx, when) {
    tone(ctx, { frequency: 520, start: when, duration: 0.06, type: 'triangle', gain: 0.09 });
    tone(ctx, { frequency: 390, start: when + 0.05, duration: 0.08, type: 'sine', gain: 0.07 });
}

const SOUND_BY_TYPE = {
    lead: 'coin',
    order_update: 'coin',
    landing_approval: 'ding',
    landing_approved: 'success',
    customer_internal_message: 'pop',
    reminder: 'ding',
    delivery_issue: 'alert',
    kpi_alert: 'alert',
};

/**
 * @param {string|null|undefined} type
 * @param {{ sound?: boolean }} prefs
 */
export function playNotificationSound(type, prefs = {}) {
    if (prefs.sound === false) return;

    const ctx = audioContext();
    if (!ctx) return;

    const resume = ctx.state === 'suspended' ? ctx.resume() : Promise.resolve();
    resume.then(() => {
        const when = ctx.currentTime + 0.01;
        const kind = SOUND_BY_TYPE[String(type || '')] || 'ding';
        switch (kind) {
            case 'coin':
                playCoinCount(ctx, when);
                break;
            case 'success':
                playSuccess(ctx, when);
                break;
            case 'alert':
                playAlert(ctx, when);
                break;
            case 'pop':
                playPop(ctx, when);
                break;
            default:
                playSoftDing(ctx, when);
        }
    }).catch(() => {
        // Autoplay / AudioContext blocked until user gesture — ignore.
    });
}
