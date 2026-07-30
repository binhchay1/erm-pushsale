/**
 * Minimal Code128B SVG barcode for shipping labels (no external deps).
 */
const CODE128B = (() => {
    // Patterns from Code 128 B (start B = 104). Values are bar/space widths.
    const patterns = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];
    return { patterns, START_B: 104, STOP: 106 };
})();

function encodeCode128B(text) {
    const value = String(text || '').replace(/[^\x20-\x7F]/g, '?');
    if (!value) return [];

    const codes = [CODE128B.START_B];
    let checksum = CODE128B.START_B;
    for (let i = 0; i < value.length; i += 1) {
        const code = value.charCodeAt(i) - 32;
        codes.push(code);
        checksum += code * (i + 1);
    }
    codes.push(checksum % 103);
    codes.push(CODE128B.STOP);
    return codes;
}

export function Code128Barcode({ value, height = 42, className = '' }) {
    const codes = encodeCode128B(value);
    if (!codes.length) {
        return <div className={`ps-print-barcode-fallback ${className}`}>{value || '—'}</div>;
    }

    const modules = [];
    codes.forEach((code) => {
        const pattern = CODE128B.patterns[code] || CODE128B.patterns[0];
        for (let i = 0; i < pattern.length; i += 1) {
            modules.push(Number(pattern[i]));
        }
    });

    const total = modules.reduce((sum, w) => sum + w, 0);
    let x = 0;
    const bars = [];
    modules.forEach((width, index) => {
        if (index % 2 === 0) {
            bars.push(`<rect x="${x}" y="0" width="${width}" height="${height}" fill="#000"/>`);
        }
        x += width;
    });

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${height}" preserveAspectRatio="none" width="100%" height="${height}">${bars.join('')}</svg>`;

    return (
        <div className={`ps-print-barcode-wrap ${className}`}>
            <div className="ps-print-barcode-svg" dangerouslySetInnerHTML={{ __html: svg }} />
            <div className="ps-print-barcode-caption">{value}</div>
        </div>
    );
}
