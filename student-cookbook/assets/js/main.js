/* =====================================================================
   Student Cookbook & Meal Planner - client-side JavaScript
   Navigation, tabs, form validation, instant filters, confirm dialogs,
   shopping list ticks, and the dynamic ingredient rows.
   ===================================================================== */
document.documentElement.classList.add('js');

document.addEventListener('DOMContentLoaded', () => {
    initNav();
    initTabs();
    initValidation();
    initAutoSubmit();
    initConfirm();
    initShoppingList();
    initIngredientRows();
});

/* ---------- Mobile menu ---------- */
function initNav() {
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('site-nav');
    if (!toggle || !nav) return;
    toggle.addEventListener('click', () => {
        const open = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(open));
        toggle.textContent = open ? 'Close' : 'Menu';
    });
}

/* ---------- Sign in / Create account tabs ---------- */
function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach(box => {
        const tabs = box.querySelectorAll('[data-tab]');
        const panels = box.querySelectorAll('[data-panel]');
        const show = name => {
            tabs.forEach(t => {
                const on = t.dataset.tab === name;
                t.setAttribute('aria-selected', String(on));
                t.tabIndex = on ? 0 : -1;
            });
            panels.forEach(p => { p.hidden = p.dataset.panel !== name; });
        };
        tabs.forEach(t => {
            t.addEventListener('click', () => show(t.dataset.tab));
            t.addEventListener('keydown', e => {
                if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                    const next = [...tabs].find(x => x !== t);
                    next.focus();
                    show(next.dataset.tab);
                }
            });
        });
        show(box.dataset.initial || 'login');
    });
}

/* ---------- Client-side form validation ---------- */
function initValidation() {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', e => {
            clearErrors(form);
            let firstBad = null;

            form.querySelectorAll('input, select, textarea').forEach(field => {
                const message = checkField(field, form);
                if (message) {
                    showError(field, message);
                    firstBad = firstBad || field;
                }
            });

            if (firstBad) {
                e.preventDefault();
                firstBad.focus();
            }
        });

        // Clear a field's error as soon as the user fixes it
        form.addEventListener('input', e => {
            const field = e.target;
            if (field.classList.contains('is-invalid') && !checkField(field, form)) {
                field.classList.remove('is-invalid');
                field.closest('label, fieldset')?.querySelector('.field-error')?.remove();
            }
        });
    });
}

function checkField(field, form) {
    if (field.type === 'hidden' || field.disabled) return '';
    const custom = field.dataset.error;
    const value = field.value.trim();

    if (field.type === 'radio') {
        if (field.required && !form.querySelector(`input[name="${field.name}"]:checked`)) {
            // Only report on the first radio in the group
            return form.querySelector(`input[name="${field.name}"]`) === field ? (custom || 'Choose an option.') : '';
        }
        return '';
    }
    if (field.required && value === '') return custom || 'This field is required.';
    if (value === '') return '';
    if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return custom || 'Enter a valid email address.';
    if (field.minLength > 0 && value.length < field.minLength) return custom || `Use at least ${field.minLength} characters.`;
    if (field.pattern && !new RegExp(`^(?:${field.pattern})$`).test(value)) return custom || 'Check the format of this field.';
    if (field.type === 'number') {
        const n = Number(value);
        if (field.min !== '' && n < Number(field.min)) return custom || `Must be ${field.min} or more.`;
        if (field.max !== '' && n > Number(field.max)) return custom || `Must be ${field.max} or less.`;
    }
    if (field.dataset.match) {
        const other = document.getElementById(field.dataset.match);
        if (other && other.value !== field.value) return custom || 'Values do not match.';
    }
    if (field.type === 'file' && field.files[0] && field.dataset.maxSize && field.files[0].size > Number(field.dataset.maxSize)) {
        return 'Photos must be 2 MB or smaller.';
    }
    return '';
}

function showError(field, message) {
    field.classList.add('is-invalid');
    const holder = field.closest('label, fieldset');
    if (!holder || holder.querySelector('.field-error')) return;
    const p = document.createElement('span');
    p.className = 'field-error';
    p.setAttribute('role', 'alert');
    p.textContent = message;
    holder.appendChild(p);
}

function clearErrors(form) {
    form.querySelectorAll('.field-error').forEach(el => el.remove());
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
}

/* ---------- Filters and dropdowns that apply instantly ---------- */
function initAutoSubmit() {
    // Whole filter form on the recipe browser
    document.querySelectorAll('form[data-autosubmit]').forEach(form => {
        let timer;
        form.addEventListener('change', e => {
            if (e.target.type !== 'search') form.requestSubmit();
        });
        form.querySelectorAll('input[type="search"]').forEach(input => {
            input.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => form.requestSubmit(), 600);
            });
            // Keep the cursor in the search box after the page reloads
            if (input.value) {
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            }
        });
    });

    // Single dropdowns (plan switcher, planner cells)
    document.querySelectorAll('select[data-autosubmit-select]').forEach(select => {
        select.addEventListener('change', () => {
            if (select.value !== '') select.form.requestSubmit();
        });
    });
}

/* ---------- "Are you sure?" before destructive actions ---------- */
function initConfirm() {
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', e => {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });
}

/* ---------- Shopping list: ticks saved on this device ---------- */
function initShoppingList() {
    const list = document.querySelector('[data-shopping-list]');
    if (!list) return;

    const key = 'cookbook-ticks-' + list.dataset.shoppingList;
    const boxes = list.querySelectorAll('input[type="checkbox"]');
    const remaining = list.querySelector('[data-remaining]');

    let ticked = [];
    try { ticked = JSON.parse(localStorage.getItem(key)) || []; } catch (err) { ticked = []; }

    const update = () => {
        let left = 0;
        boxes.forEach(box => {
            box.closest('li').classList.toggle('is-ticked', box.checked);
            if (!box.checked) left++;
        });
        if (remaining) remaining.textContent = left;
        try {
            localStorage.setItem(key, JSON.stringify([...boxes].filter(b => b.checked).map(b => b.value)));
        } catch (err) { /* storage unavailable: ticks just will not persist */ }
    };

    boxes.forEach(box => {
        box.checked = ticked.includes(box.value);
        box.addEventListener('change', update);
    });
    update();

    document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
    document.querySelector('[data-clear-ticks]')?.addEventListener('click', () => {
        boxes.forEach(b => { b.checked = false; });
        update();
    });
}

/* ---------- My recipes: add/remove ingredient rows + live cost ---------- */
function initIngredientRows() {
    const container = document.querySelector('[data-ingredient-rows]');
    if (!container) return;

    const form = container.closest('form');
    const known = {};
    document.querySelectorAll('#ingredient-list option').forEach(opt => {
        known[opt.value.toLowerCase()] = { unit: opt.dataset.unit, price: parseFloat(opt.dataset.price) };
    });

    // Fill unit and price when a known ingredient is chosen
    container.addEventListener('input', e => {
        if (e.target.matches('[data-ingredient-name]')) {
            const match = known[e.target.value.trim().toLowerCase()];
            const row = e.target.closest('.ingredient-row');
            if (match) {
                row.querySelector('[data-ingredient-unit]').value = match.unit;
                row.querySelector('[data-ingredient-price]').value = match.price;
            }
        }
        updateCost();
    });

    document.querySelector('[data-add-row]').addEventListener('click', () => {
        const rows = container.querySelectorAll('.ingredient-row');
        const clone = rows[rows.length - 1].cloneNode(true);
        clone.querySelectorAll('input').forEach(i => { i.value = ''; i.classList.remove('is-invalid'); });
        clone.querySelectorAll('.field-error').forEach(el => el.remove());
        container.appendChild(clone);
        clone.querySelector('input').focus();
    });

    container.addEventListener('click', e => {
        if (!e.target.matches('[data-remove-row]')) return;
        const rows = container.querySelectorAll('.ingredient-row');
        if (rows.length > 1) {
            e.target.closest('.ingredient-row').remove();
        } else {
            rows[0].querySelectorAll('input').forEach(i => { i.value = ''; });
        }
        updateCost();
    });

    form.querySelector('[name="servings"]').addEventListener('input', updateCost);

    function updateCost() {
        let total = 0;
        container.querySelectorAll('.ingredient-row').forEach(row => {
            const qty = parseFloat(row.querySelector('[name="ing_qty[]"]').value) || 0;
            const name = row.querySelector('[data-ingredient-name]').value.trim().toLowerCase();
            const price = known[name] ? known[name].price : (parseFloat(row.querySelector('[data-ingredient-price]').value) || 0);
            total += qty * price;
        });
        const servings = Math.max(1, parseInt(form.querySelector('[name="servings"]').value, 10) || 1);
        document.querySelector('[data-cost-preview]').textContent = '$' + (total / servings).toFixed(2);
    }
    updateCost();
}
