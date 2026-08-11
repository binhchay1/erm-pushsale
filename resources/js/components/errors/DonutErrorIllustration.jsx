import { cn } from '@/lib/utils';

const STATUS_THEME = {
    401: {
        ring: 'from-amber-300 to-orange-400',
        glaze: '#f59e0b',
        glazeLight: '#fde68a',
        crumb: '#fbbf24',
        shadow: 'rgba(245, 158, 11, 0.18)',
    },
    403: {
        ring: 'from-amber-300 to-orange-500',
        glaze: '#f97316',
        glazeLight: '#fed7aa',
        crumb: '#fb923c',
        shadow: 'rgba(249, 115, 22, 0.18)',
    },
    404: {
        ring: 'from-pink-300 to-rose-400',
        glaze: '#fb7185',
        glazeLight: '#fecdd3',
        crumb: '#fda4af',
        shadow: 'rgba(251, 113, 133, 0.18)',
    },
    419: {
        ring: 'from-violet-300 to-fuchsia-400',
        glaze: '#a855f7',
        glazeLight: '#e9d5ff',
        crumb: '#c084fc',
        shadow: 'rgba(168, 85, 247, 0.18)',
    },
    429: {
        ring: 'from-sky-300 to-cyan-400',
        glaze: '#06b6d4',
        glazeLight: '#bae6fd',
        crumb: '#67e8f9',
        shadow: 'rgba(6, 182, 212, 0.18)',
    },
    500: {
        ring: 'from-orange-300 to-amber-400',
        glaze: '#fb923c',
        glazeLight: '#fdba74',
        crumb: '#fb923c',
        shadow: 'rgba(251, 146, 60, 0.18)',
    },
    503: {
        ring: 'from-slate-300 to-blue-300',
        glaze: '#60a5fa',
        glazeLight: '#bfdbfe',
        crumb: '#93c5fd',
        shadow: 'rgba(96, 165, 250, 0.18)',
    },
    client: {
        ring: 'from-red-300 to-orange-400',
        glaze: '#ef4444',
        glazeLight: '#fecaca',
        crumb: '#fca5a5',
        shadow: 'rgba(239, 68, 68, 0.18)',
    },
    default: {
        ring: 'from-orange-300 to-amber-400',
        glaze: '#fb923c',
        glazeLight: '#fdba74',
        crumb: '#fb923c',
        shadow: 'rgba(251, 146, 60, 0.18)',
    },
};

function sprinklePositions() {
    return [
        { x: 66, y: 65, r: -18 },
        { x: 85, y: 76, r: 22 },
        { x: 108, y: 61, r: -8 },
        { x: 131, y: 73, r: 14 },
        { x: 145, y: 95, r: -18 },
        { x: 77, y: 111, r: 12 },
        { x: 116, y: 120, r: -22 },
        { x: 148, y: 117, r: 18 },
    ];
}

export default function DonutErrorIllustration({ status = 404, className }) {
    const theme = STATUS_THEME[status] ?? STATUS_THEME.default;
    const code = status === 'client' ? 'ERR' : String(status);

    return (
        <div className={cn('relative mx-auto flex w-full max-w-[340px] justify-center', className)}>
            <div className="absolute left-4 top-10 h-14 w-14 rounded-full bg-primary/10 blur-2xl" aria-hidden />
            <div className="absolute right-6 top-3 h-20 w-20 rounded-full bg-pink-200/40 blur-3xl" aria-hidden />
            <div className="absolute bottom-1/2 left-2 h-2.5 w-2.5 rounded-full bg-primary/30" aria-hidden />
            <div className="absolute right-10 top-1/3 h-3 w-3 rounded-full bg-amber-300/80" aria-hidden />

            <svg viewBox="0 0 260 230" className="relative z-10 h-auto w-full drop-shadow-[0_26px_40px_rgba(15,23,42,0.08)]" role="img" aria-label={`Error ${code}`}>
                <defs>
                    <linearGradient id={`donut-ring-${code}`} x1="0%" x2="100%" y1="0%" y2="100%">
                        <stop offset="0%" stopColor={theme.glazeLight} />
                        <stop offset="100%" stopColor={theme.glaze} />
                    </linearGradient>
                    <linearGradient id={`dough-${code}`} x1="0%" x2="100%" y1="0%" y2="100%">
                        <stop offset="0%" stopColor="#fde7be" />
                        <stop offset="100%" stopColor="#f7c97a" />
                    </linearGradient>
                </defs>

                <ellipse cx="130" cy="198" rx="80" ry="18" fill={theme.shadow} />

                <path
                    d="M186 69c24 16 39 43 39 74 0 49-40 88-89 88S47 192 47 143 87 55 136 55c12 0 24 3 35 7l-8 13c-7-3-15-4-23-4-39 0-71 32-71 72s32 72 71 72 71-32 71-72c0-24-12-45-31-58l6-16z"
                    fill={`url(#dough-${code})`}
                />
                <circle cx="136" cy="143" r="29" fill="#fff8ea" />

                <path
                    d="M176 63c15 6 28 16 38 29l-23 11-15-6-8-20 8-14z"
                    fill={`url(#dough-${code})`}
                />
                <path
                    d="M171 61c-7 14-4 29 8 37 11 8 26 7 38-6-11-15-27-26-46-31z"
                    fill={`url(#donut-ring-${code})`}
                />

                <path
                    d="M62 110c9-33 39-55 74-55 15 0 29 4 41 11l-8 16c-10-5-21-8-33-8-28 0-52 17-61 42l-13-6z"
                    fill={`url(#donut-ring-${code})`}
                    opacity="0.96"
                />
                <path
                    d="M58 117c-2 8-3 17-3 26 0 44 36 80 80 80 32 0 60-18 73-45l13 6c-15 32-47 54-86 54-53 0-96-42-96-95 0-10 2-20 5-30l14 4z"
                    fill={`url(#donut-ring-${code})`}
                    opacity="0.94"
                />

                {sprinklePositions().map((item, index) => (
                    <rect
                        key={`${item.x}-${item.y}`}
                        x={item.x}
                        y={item.y}
                        width="10"
                        height="4"
                        rx="2"
                        fill={index % 3 === 0 ? '#ffffff' : index % 3 === 1 ? theme.crumb : '#7dd3fc'}
                        transform={`rotate(${item.r} ${item.x} ${item.y})`}
                    />
                ))}

                <circle cx="105" cy="129" r="4" fill="#1f2937" />
                <circle cx="146" cy="129" r="4" fill="#1f2937" />
                <path d="M103 157c8 11 30 11 38 0" fill="none" stroke="#1f2937" strokeWidth="5" strokeLinecap="round" />

                <g transform="translate(196 39)">
                    <path d="M0 20c8-11 16-18 25-20-1 10-7 20-18 29L0 20z" fill={`url(#dough-${code})`} />
                    <circle cx="9" cy="22" r="3" fill={theme.glaze} />
                    <circle cx="15" cy="13" r="2.6" fill="#fff" />
                </g>

                <g transform="translate(204 67)">
                    <path d="M0 10c6-8 13-12 20-14-1 8-5 14-12 20L0 10z" fill={`url(#dough-${code})`} opacity="0.85" />
                </g>

                <text x="130" y="215" textAnchor="middle" fontSize="16" fontWeight="700" fill="#334155">
                    {code}
                </text>
            </svg>
        </div>
    );
}
