/* =====================================================================
   PreBasket - chart.js
   A small HTML5 CANVAS chart written for this project.
   No chart library is used, so it works offline in XAMPP.

   Usage:
       PBChart.draw(canvas, {
           labels: ["Mon", "Tue"],
           values: [5, 8],
           type:   "bar",          // "bar" or "line"
           color:  "#0f6b4a",
           prefix: "",             // e.g. "\u20b9" for money
           title:  "Orders per day"
       });
   ===================================================================== */
(function (global) {
    "use strict";

    /** Picks a round step for the value axis (1, 2, 5, 10, 20, 50 ...). */
    function niceStep(max, wanted) {
        var raw  = max / wanted;
        var pow  = Math.pow(10, Math.floor(Math.log(raw) / Math.LN10));
        var norm = raw / pow;
        var step = norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10;
        return step * pow;
    }

    function shortNumber(n) {
        if (n >= 100000) { return (n / 100000).toFixed(n % 100000 === 0 ? 0 : 1) + "L"; }
        if (n >= 1000)   { return (n / 1000).toFixed(n % 1000 === 0 ? 0 : 1) + "k"; }
        return String(Math.round(n * 100) / 100);
    }

    function draw(canvas, opt) {
        if (!canvas || !canvas.getContext) { return; }

        var labels = opt.labels || [];
        var values = (opt.values || []).map(Number);
        var type   = opt.type || "bar";
        var color  = opt.color || "#0f6b4a";
        var prefix = opt.prefix || "";

        // --- make the drawing sharp on high-resolution screens
        var ratio = global.devicePixelRatio || 1;
        var cssW  = canvas.clientWidth || 640;
        var cssH  = canvas.clientHeight || 300;
        canvas.width  = Math.round(cssW * ratio);
        canvas.height = Math.round(cssH * ratio);

        var ctx = canvas.getContext("2d");
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.clearRect(0, 0, cssW, cssH);

        if (!values.length) { return; }

        var padL = 52, padR = 14, padT = 18, padB = 40;
        var w = cssW - padL - padR;
        var h = cssH - padT - padB;

        // --- value axis
        var maxVal = Math.max.apply(null, values);
        if (maxVal <= 0) { maxVal = 1; }
        var step  = niceStep(maxVal, 4);
        var top   = Math.ceil(maxVal / step) * step;
        var lines = Math.round(top / step);

        ctx.font = '12px "Segoe UI", Arial, sans-serif';
        ctx.textBaseline = "middle";

        // grid lines + labels on the left
        for (var g = 0; g <= lines; g++) {
            var val = step * g;
            var y   = padT + h - (val / top) * h;

            ctx.strokeStyle = g === 0 ? "#c9d6cf" : "#eef2f0";
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(padL, Math.round(y) + 0.5);
            ctx.lineTo(padL + w, Math.round(y) + 0.5);
            ctx.stroke();

            ctx.fillStyle = "#5f6f66";
            ctx.textAlign = "right";
            ctx.fillText(prefix + shortNumber(val), padL - 9, y);
        }

        var slot = w / values.length;

        // --- the data itself
        if (type === "line") {
            var pts = values.map(function (v, i) {
                return { x: padL + slot * i + slot / 2, y: padT + h - (v / top) * h };
            });

            // soft fill under the line
            var grad = ctx.createLinearGradient(0, padT, 0, padT + h);
            grad.addColorStop(0, "rgba(15,107,74,.22)");
            grad.addColorStop(1, "rgba(15,107,74,0)");
            ctx.beginPath();
            ctx.moveTo(pts[0].x, padT + h);
            pts.forEach(function (p) { ctx.lineTo(p.x, p.y); });
            ctx.lineTo(pts[pts.length - 1].x, padT + h);
            ctx.closePath();
            ctx.fillStyle = grad;
            ctx.fill();

            ctx.beginPath();
            pts.forEach(function (p, i) { i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y); });
            ctx.strokeStyle = color;
            ctx.lineWidth = 2.5;
            ctx.lineJoin = "round";
            ctx.stroke();

            pts.forEach(function (p, i) {
                ctx.beginPath();
                ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
                ctx.fillStyle = "#fff";
                ctx.fill();
                ctx.strokeStyle = color;
                ctx.lineWidth = 2.5;
                ctx.stroke();

                if (values[i] > 0) {
                    ctx.fillStyle = "#17231d";
                    ctx.textAlign = "center";
                    ctx.font = 'bold 11px "Segoe UI", Arial, sans-serif';
                    ctx.fillText(prefix + shortNumber(values[i]), p.x, p.y - 14);
                    ctx.font = '12px "Segoe UI", Arial, sans-serif';
                }
            });
        } else {
            var barW = Math.min(46, slot * 0.58);
            values.forEach(function (v, i) {
                var bh = (v / top) * h;
                var x  = padL + slot * i + (slot - barW) / 2;
                var y  = padT + h - bh;

                var grad2 = ctx.createLinearGradient(0, y, 0, padT + h);
                grad2.addColorStop(0, color);
                grad2.addColorStop(1, "rgba(15,107,74,.55)");
                ctx.fillStyle = v > 0 ? grad2 : "#eef2f0";

                // rounded top corners
                var r = Math.min(6, barW / 2, bh);
                ctx.beginPath();
                ctx.moveTo(x, padT + h);
                ctx.lineTo(x, y + r);
                ctx.quadraticCurveTo(x, y, x + r, y);
                ctx.lineTo(x + barW - r, y);
                ctx.quadraticCurveTo(x + barW, y, x + barW, y + r);
                ctx.lineTo(x + barW, padT + h);
                ctx.closePath();
                ctx.fill();

                if (v > 0) {
                    ctx.fillStyle = "#17231d";
                    ctx.textAlign = "center";
                    ctx.font = 'bold 11px "Segoe UI", Arial, sans-serif';
                    ctx.fillText(prefix + shortNumber(v), x + barW / 2, y - 9);
                    ctx.font = '12px "Segoe UI", Arial, sans-serif';
                }
            });
        }

        // --- labels along the bottom
        ctx.fillStyle = "#5f6f66";
        ctx.textAlign = "center";
        labels.forEach(function (lb, i) {
            ctx.fillText(lb, padL + slot * i + slot / 2, padT + h + 17);
        });

        if (opt.title) {
            ctx.fillStyle = "#5f6f66";
            ctx.textAlign = "left";
            ctx.font = '12px "Segoe UI", Arial, sans-serif';
            ctx.fillText(opt.title, padL, padT - 8);
        }
    }

    global.PBChart = { draw: draw };
}(window));
