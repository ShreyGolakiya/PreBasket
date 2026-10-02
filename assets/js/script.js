/* =====================================================================
   PreBasket - script.js
   All the small pieces of JavaScript used by the website:
     1  menu + flash messages          5  live search, filter and sort
     2  quantity boxes                 6  form validation
     3  add to cart (without reload)   7  confirm dialogs
     4  favourite hearts               8  helper: toast
   Remember: JavaScript here is only for a nicer experience.
   PHP checks everything again on the server.
   ===================================================================== */
(function () {
    "use strict";

    var $  = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    /* ---------- 8. toast (small message in the corner) ---------------- */
    function toast(message, type) {
        var box = $("#toastBox");
        if (!box) {
            box = document.createElement("div");
            box.id = "toastBox";
            box.style.cssText = "position:fixed;right:16px;bottom:16px;z-index:999;display:grid;gap:8px";
            document.body.appendChild(box);
        }
        var t = document.createElement("div");
        t.className = "flash flash-" + (type || "success");
        t.style.cssText = "box-shadow:0 8px 24px rgba(16,48,35,.18);min-width:210px";
        t.innerHTML = "<span></span>";
        t.firstChild.textContent = message;
        box.appendChild(t);
        setTimeout(function () {
            t.style.transition = "opacity .3s";
            t.style.opacity = "0";
            setTimeout(function () { t.remove(); }, 320);
        }, 2600);
    }
    window.PBToast = toast;

    /* ---------- 1. menu + flash messages ------------------------------ */
    var navToggle = $("#navToggle");
    if (navToggle) {
        navToggle.addEventListener("click", function () {
            var nav = $("#mainNav");
            var open = nav.classList.toggle("open");
            navToggle.setAttribute("aria-expanded", open ? "true" : "false");
        });
    }

    var sideToggle = $("#sideToggle");
    if (sideToggle) {
        sideToggle.addEventListener("click", function () { $("#adminSide").classList.toggle("open"); });
    }

    document.addEventListener("click", function (ev) {
        var btn = ev.target.closest(".flash-close");
        if (btn) { btn.parentElement.remove(); }
    });

    // flash messages disappear on their own after 6 seconds
    $$(".flash-wrap .flash").forEach(function (f) {
        setTimeout(function () {
            f.style.transition = "opacity .4s";
            f.style.opacity = "0";
            setTimeout(function () { f.remove(); }, 420);
        }, 6000);
    });

    /* ---------- 2. quantity boxes ------------------------------------- */
    // Works for any <div class="qty-box"> with - input +
    document.addEventListener("click", function (ev) {
        var btn = ev.target.closest("[data-step]");
        if (!btn) { return; }
        ev.preventDefault();
        var box   = btn.closest(".qty-box");
        var input = $("input", box);
        var max   = parseInt(input.getAttribute("max") || "99", 10);
        var min   = parseInt(input.getAttribute("min") || "1", 10);
        var now   = parseInt(input.value, 10) || min;
        var next  = now + parseInt(btn.getAttribute("data-step"), 10);

        if (next < min) { next = min; }
        if (next > max) {
            next = max;
            showStockMsg(box, "Only " + max + " items available.");
        } else {
            showStockMsg(box, "");
        }
        input.value = next;
        input.dispatchEvent(new Event("change", { bubbles: true }));
    });

    function showStockMsg(box, text) {
        var holder = box.parentElement.querySelector(".stock-msg");
        if (holder) { holder.textContent = text; }
    }

    // typing directly in the box
    document.addEventListener("input", function (ev) {
        var input = ev.target;
        if (!input.matches(".qty-box input")) { return; }
        var max = parseInt(input.getAttribute("max") || "99", 10);
        var v   = parseInt(input.value, 10);
        if (isNaN(v) || v < 1) { return; }           // let the user finish typing
        if (v > max) {
            input.value = max;
            showStockMsg(input.closest(".qty-box"), "Only " + max + " items available.");
        }
    });

    // cart page: quantity change submits its own small form
    $$("form.qty-form .qty-box input").forEach(function (input) {
        input.addEventListener("change", function () {
            var v = parseInt(input.value, 10);
            if (isNaN(v) || v < 1) { input.value = 1; }
            input.form.submit();
        });
    });

    /* ---------- 3. add to cart without reloading the page ------------- */
    function cartFetch(params) {
        var body = new URLSearchParams(params);
        body.set("ajax", "1");
        return fetch(BASE + "/cart_action.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "X-CSRF-Token": CSRF,
                "X-Requested-With": "XMLHttpRequest"
            },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }
    window.PBCart = cartFetch;

    function setBadge(n) {
        var badge = $("#cartBadge");
        if (!badge) { return; }
        badge.textContent = n;
        badge.classList.toggle("is-hidden", !n);
    }

    document.addEventListener("submit", function (ev) {
        var form = ev.target;
        if (!form.matches(".add-form")) { return; }
        if (typeof fetch !== "function") { return; }     // very old browser: normal submit
        ev.preventDefault();

        var btn = $("button", form);
        var qty = form.querySelector('[name="quantity"]').value;
        var pid = form.querySelector('[name="product_id"]').value;
        btn.disabled = true;

        cartFetch({ action: "add", product_id: pid, quantity: qty })
            .then(function (res) {
                if (res.ok) {
                    setBadge(res.cart_count);
                    btn.innerHTML = "&#10003; ADDED";
                    btn.classList.add("btn-accent");
                    toast(res.message, "success");
                    setTimeout(function () {
                        btn.innerHTML = "+ ADD";
                        btn.classList.remove("btn-accent");
                        btn.disabled = false;
                    }, 1400);
                } else {
                    toast(res.message, "error");
                    btn.disabled = false;
                    if (res.login) { window.location.href = BASE + "/login.php"; }
                }
            })
            .catch(function () {
                btn.disabled = false;
                form.submit();                            // fall back to a normal page post
            });
    });

    /* ---------- 4. favourite hearts (saved in this browser) ----------- */
    var FAV_KEY = "pb_favourites";
    function readFavs() {
        try { return JSON.parse(localStorage.getItem(FAV_KEY) || "[]"); } catch (e) { return []; }
    }
    function writeFavs(list) {
        try { localStorage.setItem(FAV_KEY, JSON.stringify(list)); } catch (e) { /* ignore */ }
    }
    var favs = readFavs();
    $$("[data-fav]").forEach(function (b) {
        if (favs.indexOf(b.getAttribute("data-fav")) !== -1) { b.setAttribute("aria-pressed", "true"); }
    });
    document.addEventListener("click", function (ev) {
        var b = ev.target.closest("[data-fav]");
        if (!b) { return; }
        ev.preventDefault();
        var id = b.getAttribute("data-fav");
        var on = b.getAttribute("aria-pressed") === "true";
        favs = readFavs();
        if (on) {
            favs = favs.filter(function (x) { return x !== id; });
        } else if (favs.indexOf(id) === -1) {
            favs.push(id);
        }
        writeFavs(favs);
        b.setAttribute("aria-pressed", on ? "false" : "true");
    });

    /* ---------- 5. live search, filter and sort ----------------------- */
    var liveInput = $("#liveFilter");
    var sortSelect = $("#sortSelect");
    var grid = $("#productGrid");

    function applyFilterSort() {
        if (!grid) { return; }
        var cards = $$(".p-card", grid);
        var term  = liveInput ? liveInput.value.trim().toLowerCase() : "";
        var shown = 0;

        cards.forEach(function (c) {
            var match = !term || c.getAttribute("data-name").indexOf(term) !== -1;
            c.style.display = match ? "" : "none";
            if (match) { shown++; }
        });

        if (sortSelect && sortSelect.value !== "relevance") {
            var mode = sortSelect.value;
            cards.sort(function (a, b) {
                var av, bv;
                if (mode === "price_low" || mode === "price_high") {
                    av = parseFloat(a.getAttribute("data-price"));
                    bv = parseFloat(b.getAttribute("data-price"));
                    return mode === "price_low" ? av - bv : bv - av;
                }
                if (mode === "rating") {
                    return parseFloat(b.getAttribute("data-rating")) - parseFloat(a.getAttribute("data-rating"));
                }
                if (mode === "name") {
                    return a.getAttribute("data-name").localeCompare(b.getAttribute("data-name"));
                }
                return 0;
            });
            cards.forEach(function (c) { grid.appendChild(c); });
        }

        var none = $("#noResults");
        if (none) { none.classList.toggle("is-hidden", shown > 0); }
        var cnt = $("#resultCount");
        if (cnt) { cnt.textContent = shown; }
    }

    if (liveInput) { liveInput.addEventListener("input", applyFilterSort); }
    if (sortSelect) {
        sortSelect.addEventListener("change", function () {
            applyFilterSort();
            // also remember the choice in the address bar so a reload keeps it
            var u = new URL(window.location.href);
            u.searchParams.set("sort", sortSelect.value);
            history.replaceState(null, "", u.toString());
        });
        if (sortSelect.value !== "relevance") { applyFilterSort(); }
    }

    /* ---------- 6. form validation ------------------------------------ */
    // Rules are written as data-* attributes in the HTML.
    function fieldError(input, message) {
        var field = input.closest(".field");
        if (!field) { return; }
        var slot = field.querySelector(".err");
        if (!slot) {
            slot = document.createElement("span");
            slot.className = "err";
            field.appendChild(slot);
        }
        slot.textContent = message || "";
        field.classList.toggle("has-error", !!message);
    }

    function checkInput(input) {
        var v = (input.value || "").trim();
        var name = input.getAttribute("data-label") || "This field";

        if (input.hasAttribute("required") && v === "") {
            fieldError(input, name + " is required."); return false;
        }
        if (v === "") { fieldError(input, ""); return true; }

        var rule = input.getAttribute("data-rule");
        if (rule === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) {
            fieldError(input, "Please enter a valid email address."); return false;
        }
        if (rule === "phone" && !/^[0-9]{10}$/.test(v)) {
            fieldError(input, "Phone number must be exactly 10 digits."); return false;
        }
        if (rule === "name" && v.length < 3) {
            fieldError(input, "Please enter your full name."); return false;
        }
        if (rule === "password") {
            if (v.length < 6) { fieldError(input, "Password must be at least 6 characters."); return false; }
            if (!/[A-Za-z]/.test(v) || !/[0-9]/.test(v)) {
                fieldError(input, "Use at least one letter and one number."); return false;
            }
        }
        if (rule === "match") {
            var other = $("#" + input.getAttribute("data-match"));
            if (other && other.value !== v) { fieldError(input, "The two passwords do not match."); return false; }
        }
        if (rule === "money") {
            var n = parseFloat(v);
            if (isNaN(n) || n <= 0) { fieldError(input, "Enter a price greater than 0."); return false; }
        }
        if (rule === "int0") {
            var i = parseInt(v, 10);
            if (isNaN(i) || i < 0 || String(i) !== v) { fieldError(input, "Enter a whole number (0 or more)."); return false; }
        }
        fieldError(input, "");
        return true;
    }

    $$("form[data-validate]").forEach(function (form) {
        var inputs = $$("input[data-rule], input[required], select[required], textarea[required]", form);
        inputs.forEach(function (i) {
            i.addEventListener("blur", function () { checkInput(i); });
            i.addEventListener("input", function () {
                if (i.closest(".field") && i.closest(".field").classList.contains("has-error")) { checkInput(i); }
            });
        });
        form.addEventListener("submit", function (ev) {
            var ok = true;
            inputs.forEach(function (i) { if (!checkInput(i)) { ok = false; } });
            if (!ok) {
                ev.preventDefault();
                var bad = form.querySelector(".has-error input, .has-error select");
                if (bad) { bad.focus(); }
                toast("Please correct the highlighted fields.", "error");
            }
        });
    });

    // show / hide password
    $$(".pw-toggle").forEach(function (b) {
        b.addEventListener("click", function () {
            var input = b.parentElement.querySelector("input");
            var show = input.type === "password";
            input.type = show ? "text" : "password";
            b.textContent = show ? "HIDE" : "SHOW";
        });
    });

    /* ---------- 7. confirm dialogs ------------------------------------ */
    document.addEventListener("submit", function (ev) {
        var form = ev.target;
        var msg = form.getAttribute("data-confirm");
        if (msg && !window.confirm(msg)) { ev.preventDefault(); }
    });
    document.addEventListener("click", function (ev) {
        var a = ev.target.closest("a[data-confirm]");
        if (a && !window.confirm(a.getAttribute("data-confirm"))) { ev.preventDefault(); }
    });

    /* ---------- payment method cards highlight ------------------------ */
    $$(".pay-opt input").forEach(function (r) {
        r.addEventListener("change", function () {
            $$(".pay-opt").forEach(function (o) { o.classList.remove("selected"); });
            if (r.checked) { r.closest(".pay-opt").classList.add("selected"); }
        });
        if (r.checked) { r.closest(".pay-opt").classList.add("selected"); }
    });
}());
