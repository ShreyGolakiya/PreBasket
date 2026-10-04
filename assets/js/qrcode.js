/*
 * QuickCart - tiny offline QR code generator
 * ------------------------------------------
 * Written for this project so that QR codes work WITHOUT internet.
 * Supports: byte mode, error correction level M, versions 1 to 6
 * (that is enough for up to 106 characters - our Order IDs are only 14).
 *
 * Usage:   PBQR.draw(canvasElement, "PB202609200001", 6);
 *          (6 = size of one square in pixels)
 *
 * The algorithm follows the public QR Code specification (ISO/IEC 18004).
 */
(function (global) {
  "use strict";

  // Error-correction tables for level M, index = version (1..6)
  var ECC_PER_BLOCK = [0, 10, 16, 26, 18, 24, 16];
  var NUM_BLOCKS    = [0, 1, 1, 1, 2, 2, 4];

  // ---------- Reed-Solomon (Galois field 256) ----------
  function gfMul(x, y) {
    var z = 0;
    for (var i = 7; i >= 0; i--) {
      z = (z << 1) ^ ((z >>> 7) * 0x11D);
      z ^= ((y >>> i) & 1) * x;
    }
    return z & 0xFF;
  }
  function rsDivisor(degree) {
    var result = [], i, j;
    for (i = 0; i < degree - 1; i++) result.push(0);
    result.push(1);
    var root = 1;
    for (i = 0; i < degree; i++) {
      for (j = 0; j < degree; j++) {
        result[j] = gfMul(result[j], root);
        if (j + 1 < degree) result[j] ^= result[j + 1];
      }
      root = gfMul(root, 0x02);
    }
    return result;
  }
  function rsRemainder(data, divisor) {
    var result = divisor.map(function () { return 0; });
    data.forEach(function (b) {
      var factor = b ^ result.shift();
      result.push(0);
      divisor.forEach(function (coef, i) { result[i] ^= gfMul(coef, factor); });
    });
    return result;
  }

  // ---------- Capacity helpers ----------
  function numRawDataModules(ver) {
    var result = (16 * ver + 128) * ver + 64;
    if (ver >= 2) {
      var numAlign = Math.floor(ver / 7) + 2;
      result -= (25 * numAlign - 10) * numAlign - 55;
    }
    return result;
  }
  function numDataCodewords(ver) {
    return Math.floor(numRawDataModules(ver) / 8) - ECC_PER_BLOCK[ver] * NUM_BLOCKS[ver];
  }
  function alignPositions(ver) {
    if (ver === 1) return [];
    var size = ver * 4 + 17;
    return [6, size - 7];           // versions 2..6 only have two positions
  }

  // ---------- Build the data bits ----------
  function toUtf8Bytes(text) {
    var out = [], enc = unescape(encodeURIComponent(text));
    for (var i = 0; i < enc.length; i++) out.push(enc.charCodeAt(i));
    return out;
  }
  function makeCodewords(bytes, ver) {
    var capacity = numDataCodewords(ver) * 8;
    var bits = [];
    function put(val, len) { for (var i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
    put(4, 4);                 // mode: byte
    put(bytes.length, 8);      // character count (8 bits for versions 1-9)
    bytes.forEach(function (b) { put(b, 8); });
    put(0, Math.min(4, capacity - bits.length));   // terminator
    while (bits.length % 8 !== 0) bits.push(0);
    for (var pad = 0xEC; bits.length < capacity; pad ^= (0xEC ^ 0x11)) put(pad, 8);
    var codewords = [];
    for (var i = 0; i < bits.length; i += 8) {
      var v = 0;
      for (var j = 0; j < 8; j++) v = (v << 1) | bits[i + j];
      codewords.push(v);
    }
    return codewords;
  }
  function addEccAndInterleave(data, ver) {
    var numBlocks = NUM_BLOCKS[ver], eccLen = ECC_PER_BLOCK[ver];
    var rawCodewords = Math.floor(numRawDataModules(ver) / 8);
    var numShort = numBlocks - rawCodewords % numBlocks;
    var shortLen = Math.floor(rawCodewords / numBlocks);
    var blocks = [], div = rsDivisor(eccLen), k = 0, i;
    for (i = 0; i < numBlocks; i++) {
      var dat = data.slice(k, k + shortLen - eccLen + (i < numShort ? 0 : 1));
      k += dat.length;
      var ecc = rsRemainder(dat, div);
      if (i < numShort) dat.push(0);
      blocks.push(dat.concat(ecc));
    }
    var result = [];
    for (i = 0; i < blocks[0].length; i++) {
      blocks.forEach(function (block, j) {
        if (i !== shortLen - eccLen || j >= numShort) result.push(block[i]);
      });
    }
    return result;
  }

  // ---------- Matrix ----------
  function Matrix(ver) {
    this.ver = ver;
    this.size = ver * 4 + 17;
    this.mod = [];
    this.fn = [];
    for (var y = 0; y < this.size; y++) {
      this.mod.push(new Array(this.size).fill(false));
      this.fn.push(new Array(this.size).fill(false));
    }
  }
  Matrix.prototype.setFn = function (x, y, dark) {
    this.mod[y][x] = dark;
    this.fn[y][x] = true;
  };
  Matrix.prototype.drawFunctionPatterns = function () {
    var size = this.size, i, dx, dy;
    for (i = 0; i < size; i++) {                 // timing patterns
      this.setFn(6, i, i % 2 === 0);
      this.setFn(i, 6, i % 2 === 0);
    }
    var self = this;
    function finder(cx, cy) {
      for (dy = -4; dy <= 4; dy++) for (dx = -4; dx <= 4; dx++) {
        var dist = Math.max(Math.abs(dx), Math.abs(dy));
        var xx = cx + dx, yy = cy + dy;
        if (xx >= 0 && xx < size && yy >= 0 && yy < size) self.setFn(xx, yy, dist !== 2 && dist !== 4);
      }
    }
    finder(3, 3); finder(size - 4, 3); finder(3, size - 4);
    var pos = alignPositions(this.ver);
    for (var a = 0; a < pos.length; a++) for (var b = 0; b < pos.length; b++) {
      if ((a === 0 && b === 0) || (a === 0 && b === pos.length - 1) || (a === pos.length - 1 && b === 0)) continue;
      for (dy = -2; dy <= 2; dy++) for (dx = -2; dx <= 2; dx++)
        this.setFn(pos[a] + dx, pos[b] + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1);
    }
    this.drawFormat(0);                          // reserve format area
  };
  Matrix.prototype.drawFormat = function (mask) {
    var size = this.size, i;
    var data = (0 << 3) | mask;                  // level M has format bits 00
    var rem = data;
    for (i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
    var bits = ((data << 10) | rem) ^ 0x5412;
    function bit(n) { return ((bits >>> n) & 1) !== 0; }
    for (i = 0; i <= 5; i++) this.setFn(8, i, bit(i));
    this.setFn(8, 7, bit(6)); this.setFn(8, 8, bit(7)); this.setFn(7, 8, bit(8));
    for (i = 9; i < 15; i++) this.setFn(14 - i, 8, bit(i));
    for (i = 0; i < 8; i++) this.setFn(size - 1 - i, 8, bit(i));
    for (i = 8; i < 15; i++) this.setFn(8, size - 15 + i, bit(i));
    this.setFn(8, size - 8, true);               // the always-dark module
  };
  Matrix.prototype.drawCodewords = function (data) {
    var size = this.size, i = 0;
    for (var right = size - 1; right >= 1; right -= 2) {
      if (right === 6) right = 5;
      for (var vert = 0; vert < size; vert++) {
        for (var j = 0; j < 2; j++) {
          var x = right - j;
          var upward = ((right + 1) & 2) === 0;
          var y = upward ? size - 1 - vert : vert;
          if (!this.fn[y][x] && i < data.length * 8) {
            this.mod[y][x] = ((data[i >>> 3] >>> (7 - (i & 7))) & 1) !== 0;
            i++;
          }
        }
      }
    }
  };
  Matrix.prototype.applyMask = function (mask) {
    for (var y = 0; y < this.size; y++) for (var x = 0; x < this.size; x++) {
      var inv;
      switch (mask) {
        case 0: inv = (x + y) % 2 === 0; break;
        case 1: inv = y % 2 === 0; break;
        case 2: inv = x % 3 === 0; break;
        case 3: inv = (x + y) % 3 === 0; break;
        case 4: inv = (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0; break;
        case 5: inv = x * y % 2 + x * y % 3 === 0; break;
        case 6: inv = (x * y % 2 + x * y % 3) % 2 === 0; break;
        default: inv = ((x + y) % 2 + x * y % 3) % 2 === 0;
      }
      if (!this.fn[y][x] && inv) this.mod[y][x] = !this.mod[y][x];
    }
  };
  // Simplified penalty score (runs of 5+ same colour, 2x2 blocks, dark/light balance)
  Matrix.prototype.penalty = function () {
    var size = this.size, m = this.mod, score = 0, x, y, run, dark = 0;
    for (y = 0; y < size; y++) {
      run = 1;
      for (x = 1; x < size; x++) {
        if (m[y][x] === m[y][x - 1]) { run++; if (run === 5) score += 3; else if (run > 5) score++; } else run = 1;
      }
    }
    for (x = 0; x < size; x++) {
      run = 1;
      for (y = 1; y < size; y++) {
        if (m[y][x] === m[y - 1][x]) { run++; if (run === 5) score += 3; else if (run > 5) score++; } else run = 1;
      }
    }
    for (y = 0; y < size - 1; y++) for (x = 0; x < size - 1; x++) {
      if (m[y][x] === m[y][x + 1] && m[y][x] === m[y + 1][x] && m[y][x] === m[y + 1][x + 1]) score += 3;
    }
    for (y = 0; y < size; y++) for (x = 0; x < size; x++) if (m[y][x]) dark++;
    var pct = dark * 100 / (size * size);
    score += Math.floor(Math.abs(pct - 50) / 5) * 10;
    return score;
  };

  // ---------- Public API ----------
  function encode(text) {
    var bytes = toUtf8Bytes(String(text));
    var ver;
    for (ver = 1; ver <= 6; ver++) {
      if (4 + 8 + bytes.length * 8 <= numDataCodewords(ver) * 8) break;
    }
    if (ver > 6) throw new Error("Text is too long for this small QR generator");
    var codewords = addEccAndInterleave(makeCodewords(bytes, ver), ver);
    var best = null, bestScore = Infinity;
    for (var mask = 0; mask < 8; mask++) {
      var m = new Matrix(ver);
      m.drawFunctionPatterns();
      m.drawCodewords(codewords);
      m.applyMask(mask);
      m.drawFormat(mask);
      var s = m.penalty();
      if (s < bestScore) { bestScore = s; best = m; }
    }
    return best.mod;
  }

  function draw(canvas, text, scale) {
    scale = scale || 6;
    var quiet = 4;                               // white border (quiet zone)
    var modules = encode(text);
    var n = modules.length;
    var px = (n + quiet * 2) * scale;
    canvas.width = px;
    canvas.height = px;
    var ctx = canvas.getContext("2d");
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, px, px);
    ctx.fillStyle = "#000000";
    for (var y = 0; y < n; y++) for (var x = 0; x < n; x++) {
      if (modules[y][x]) ctx.fillRect((x + quiet) * scale, (y + quiet) * scale, scale, scale);
    }
  }

  global.PBQR = { draw: draw, encode: encode };
})(window);
