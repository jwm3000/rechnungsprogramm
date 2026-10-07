// Seitei GmbH – Logo: Bierflasche + Schriftzug, alles als Pfade (für das PDF)
const opentype = require('opentype.js');
const fs = require('fs');
const serif = opentype.loadSync('/usr/share/fonts/noto/NotoSerif-Black.ttf');
const sans = opentype.loadSync('/usr/share/fonts/noto/NotoSans-CondensedBlack.ttf');
const C = { glass: '#6b3410', shine: '#b0682a', cap: '#c9a227', capDark: '#9c7a12', label: '#f3e6c8', ink: '#2a1a0f', amber: '#a65d1f' };
const P = (d, fill) => `<path fill="${fill}" d="${d}"/>`;
const text = (font, str, x, y, size, track = 0) => {
	let d = '';
	let cx = x;
	for (const ch of str) {
		const g = font.charToGlyph(ch);
		d += g.getPath(cx, y, size).toPathData(2);
		cx += (g.advanceWidth / font.unitsPerEm) * size + track;
	}
	return { d, w: cx - x - track };
};
// Flasche (Breite 52, Höhe 160), links oben bei (0,0)
const bx = 0, by = 0;
const bottle = [
	// Glas: Hals, Schultern, Bauch mit runden Ecken
	P(`M${bx + 19} ${by + 14} L${bx + 33} ${by + 14} L${bx + 33} ${by + 46} C${bx + 33} ${by + 60} ${bx + 52} ${by + 66} ${bx + 52} ${by + 86} L${bx + 52} ${by + 152} C${bx + 52} ${by + 157} ${bx + 49} ${by + 160} ${bx + 44} ${by + 160} L${bx + 8} ${by + 160} C${bx + 3} ${by + 160} ${bx + 0} ${by + 157} ${bx + 0} ${by + 152} L${bx + 0} ${by + 86} C${bx + 0} ${by + 66} ${bx + 19} ${by + 60} ${bx + 19} ${by + 46} Z`, C.glass),
	// Glanz
	P(`M${bx + 6} ${by + 92} L${bx + 10} ${by + 92} L${bx + 10} ${by + 150} L${bx + 6} ${by + 150} Z M${bx + 22} ${by + 18} L${bx + 25} ${by + 18} L${bx + 25} ${by + 44} L${bx + 22} ${by + 44} Z`, C.shine),
	// Kronkorken
	P(`M${bx + 16} ${by + 2} L${bx + 36} ${by + 2} L${bx + 36} ${by + 14} L${bx + 16} ${by + 14} Z`, C.cap),
	P(`M${bx + 16} ${by + 10} L${bx + 36} ${by + 10} L${bx + 36} ${by + 14} L${bx + 16} ${by + 14} Z`, C.capDark),
	// Etikett
	P(`M${bx + 6} ${by + 100} L${bx + 46} ${by + 100} C${bx + 48} ${by + 100} ${bx + 48} ${by + 102} ${bx + 48} ${by + 104} L${bx + 48} ${by + 136} C${bx + 48} ${by + 138} ${bx + 46} ${by + 140} ${bx + 44} ${by + 140} L${bx + 8} ${by + 140} C${bx + 6} ${by + 140} ${bx + 4} ${by + 138} ${bx + 4} ${by + 136} L${bx + 4} ${by + 104} C${bx + 4} ${by + 102} ${bx + 4} ${by + 100} ${bx + 6} ${by + 100} Z`, C.label),
];
const s = text(serif, 'S', 0, 0, 34);
const sg = serif.charToGlyph('S').getBoundingBox();
const sw = (sg.x2 - sg.x1) / serif.unitsPerEm * 34;
bottle.push(P(text(serif, 'S', bx + 26 - sw / 2 - sg.x1 / serif.unitsPerEm * 34, by + 132, 34).d, C.glass));
// Schriftzug
const word = text(serif, 'Seitei', 72, 104, 112, -1);
const gmbh = text(sans, 'GMBH', 76, 150, 34, 5);
const rule = P(`M${76 + gmbh.w + 16} 137 L${72 + word.w} 137 L${72 + word.w} 140 L${76 + gmbh.w + 16} 140 Z`, C.amber);
const W = Math.ceil(72 + word.w + 4), H = 162;
const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" width="${W * 2}" height="${H * 2}">
${bottle.join('\n')}
${P(word.d, "currentColor")}
${P(gmbh.d, C.amber)}
${rule}
</svg>
`;
fs.writeFileSync(process.argv[2], svg);
console.log('ok', W, H);
