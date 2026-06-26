/**
 * XGOUD compacte QR-generator — vaste configuratie versie 3, ECC-niveau L,
 * byte-modus, masker 0 (29×29). Genoeg voor een korte verify-URL (≤ ~50 tekens).
 * Geeft een SVG-string of null als de tekst te lang is. Geen library.
 *
 * window.XGQR.svg(text, size) → '<svg…>' | null
 */
(function () {
	'use strict';
	// GF(256) tabellen.
	var EXP = new Array(512), LOG = new Array(256);
	(function () {
		var x = 1;
		for (var i = 0; i < 255; i++) { EXP[i] = x; LOG[x] = i; x <<= 1; if (x & 0x100) x ^= 0x11d; }
		for (var j = 255; j < 512; j++) EXP[j] = EXP[j - 255];
	})();
	function mul(a, b) { return (a === 0 || b === 0) ? 0 : EXP[LOG[a] + LOG[b]]; }

	function rsGenPoly(deg) {
		var poly = [1];
		for (var i = 0; i < deg; i++) {
			var np = new Array(poly.length + 1).fill(0);
			for (var j = 0; j < poly.length; j++) {
				np[j] ^= mul(poly[j], 1);
				np[j + 1] ^= mul(poly[j], EXP[i]);
			}
			poly = np;
		}
		return poly;
	}
	function rsEncode(data, ecLen) {
		var gen = rsGenPoly(ecLen);
		var res = data.concat(new Array(ecLen).fill(0));
		for (var i = 0; i < data.length; i++) {
			var c = res[i];
			if (c !== 0) for (var j = 0; j < gen.length; j++) res[i + j] ^= mul(gen[j], c);
		}
		return res.slice(data.length);
	}

	// BCH voor format-info.
	function bch15(data) {
		var d = data << 10;
		var g = 0x537;
		for (var i = 4; i >= 0; i--) if ((d >> (10 + i)) & 1) d ^= g << i;
		return ((data << 10) | d) ^ 0x5412;
	}

	function build(text) {
		var bytes = unescape(encodeURIComponent(text)).split('').map(function (c) { return c.charCodeAt(0); });
		var DATA_CW = 55, EC_CW = 15;            // v3-L
		if (bytes.length > DATA_CW - 2) return null;
		// Bitstream.
		var bits = [];
		function push(val, n) { for (var i = n - 1; i >= 0; i--) bits.push((val >> i) & 1); }
		push(4, 4);                              // byte-modus
		push(bytes.length, 8);                   // teller (v<10)
		bytes.forEach(function (b) { push(b, 8); });
		push(0, 4);                              // terminator
		while (bits.length % 8) bits.push(0);
		var cw = [];
		for (var i = 0; i < bits.length; i += 8) { var v = 0; for (var k = 0; k < 8; k++) v = (v << 1) | bits[i + k]; cw.push(v); }
		var pad = [0xEC, 0x11], pi = 0;
		while (cw.length < DATA_CW) cw.push(pad[pi++ % 2]);
		var ec = rsEncode(cw, EC_CW);
		var all = cw.concat(ec);                 // 70 codewords, single block

		var N = 29;
		var m = [], fn = [];
		for (var r = 0; r < N; r++) { m.push(new Array(N).fill(0)); fn.push(new Array(N).fill(0)); }
		function set(r, c, v) { m[r][c] = v ? 1 : 0; fn[r][c] = 1; }
		function finder(r, c) {
			for (var dr = -1; dr <= 7; dr++) for (var dc = -1; dc <= 7; dc++) {
				var rr = r + dr, cc = c + dc; if (rr < 0 || cc < 0 || rr >= N || cc >= N) continue;
				var on = (dr >= 0 && dr <= 6 && (dc === 0 || dc === 6)) || (dc >= 0 && dc <= 6 && (dr === 0 || dr === 6)) || (dr >= 2 && dr <= 4 && dc >= 2 && dc <= 4);
				set(rr, cc, on);
			}
		}
		finder(0, 0); finder(0, N - 7); finder(N - 7, 0);
		for (var t = 8; t < N - 8; t++) { set(6, t, t % 2 === 0); set(t, 6, t % 2 === 0); }
		// Alignment v3 @ (22,22).
		for (var ar = -2; ar <= 2; ar++) for (var ac = -2; ac <= 2; ac++) {
			set(22 + ar, 22 + ac, Math.max(Math.abs(ar), Math.abs(ac)) !== 1);
		}
		set(N - 8, 8, 1);                        // dark module (21,8)
		// Format-info gebieden reserveren.
		for (var f = 0; f < 9; f++) { if (f !== 6) { fn[8][f] = 1; fn[f][8] = 1; } }
		for (var g = 0; g < 8; g++) { fn[8][N - 1 - g] = 1; fn[N - 1 - g][8] = 1; }

		// Data plaatsen (zigzag) + masker 0.
		var bi = 0, dir = -1;
		for (var col = N - 1; col > 0; col -= 2) {
			if (col === 6) col--;
			for (var cnt = 0; cnt < N; cnt++) {
				var row = dir < 0 ? N - 1 - cnt : cnt;
				for (var s = 0; s < 2; s++) {
					var cc2 = col - s;
					if (fn[row][cc2]) continue;
					var bit = bi < all.length * 8 ? (all[bi >> 3] >> (7 - (bi & 7))) & 1 : 0; bi++;
					if ((row + cc2) % 2 === 0) bit ^= 1;   // masker 0
					m[row][cc2] = bit;
				}
			}
			dir = -dir;
		}
		// Format-info (ECC L=01, masker 000).
		var fmt = bch15(0x08);                    // 01000
		var fb = []; for (var x = 14; x >= 0; x--) fb.push((fmt >> x) & 1);
		var pos1 = [[0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [7, 8], [8, 8], [8, 7], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0]];
		var pos2 = [[8, N - 1], [8, N - 2], [8, N - 3], [8, N - 4], [8, N - 5], [8, N - 6], [8, N - 7], [8, N - 8], [N - 7, 8], [N - 6, 8], [N - 5, 8], [N - 4, 8], [N - 3, 8], [N - 2, 8], [N - 1, 8]];
		for (var p = 0; p < 15; p++) { m[pos1[p][0]][pos1[p][1]] = fb[p]; m[pos2[p][0]][pos2[p][1]] = fb[p]; }
		return m;
	}

	function svg(text, size) {
		var m = build(text);
		if (!m) return null;
		var N = m.length, q = 2, dim = N + q * 2, px = (size || 200) / dim;
		var s = '<svg xmlns="http://www.w3.org/2000/svg" width="' + (size || 200) + '" height="' + (size || 200) + '" viewBox="0 0 ' + dim + ' ' + dim + '"><rect width="' + dim + '" height="' + dim + '" fill="#fff"/><path fill="#000" d="';
		for (var r = 0; r < N; r++) for (var c = 0; c < N; c++) if (m[r][c]) s += 'M' + (c + q) + ' ' + (r + q) + 'h1v1h-1z';
		return s + '"/></svg>';
	}

	window.XGQR = { svg: svg };
})();
