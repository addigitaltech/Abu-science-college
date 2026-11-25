/*
 * UI behaviour layer. Plain JavaScript only (the site CSP forbids inline and eval scripts).
 * Every enhancement is progressive: if this file fails, forms, links and tables still work.
 *
 *  - Confirm dialog   : forms with data-confirm get a styled <dialog> instead of window.confirm()
 *  - Loading buttons  : submit buttons show a spinner and the form cannot be double-submitted
 *  - Table labels     : .table--stack cells get data-label from their column heading (phone card layout)
 *  - Password toggle  : show/hide button on password inputs
 *  - Alerts           : success notices can be dismissed and fade out on their own
 *  - Menus            : <details class="user-menu"> closes on outside click / Escape
 *  - Portal tabs      : the active tab is scrolled into view on small screens
 */

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const ICON = {
    eye: '<svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>',
    eyeOff: '<svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.6 7 10 7a10 10 0 0 0 4.2-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>',
    close: '<svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>',
    warn: '<svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" style="width:1.4rem;height:1.4rem"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>',
};

/* ----- Confirm dialog ----- */
const confirmed = new WeakSet();
let dialog = null;

function buildDialog() {
    const el = document.createElement('dialog');
    el.className = 'ui-dialog';
    el.setAttribute('aria-labelledby', 'ui-dialog-title');
    el.setAttribute('aria-describedby', 'ui-dialog-text');
    el.innerHTML = `
        <form method="dialog">
            <div class="ui-dialog__body">
                <span class="ui-dialog__icon">${ICON.warn}</span>
                <div><h2 id="ui-dialog-title">Please confirm</h2><p id="ui-dialog-text"></p></div>
            </div>
            <div class="ui-dialog__actions">
                <button type="submit" value="cancel" class="btn btn--outline btn--sm" autofocus>Cancel</button>
                <button type="submit" value="ok" class="btn btn--maroon btn--sm">Continue</button>
            </div>
        </form>`;
    document.body.append(el);
    return el;
}

function askConfirm(message) {
    return new Promise((resolve) => {
        dialog = dialog || buildDialog();
        dialog.querySelector('#ui-dialog-text').textContent = message;
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
        dialog.returnValue = 'cancel';
        dialog.showModal();
    });
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm) return;
    if (confirmed.has(form)) { confirmed.delete(form); return; }
    // Browsers without <dialog> fall back to the older confirm() handled in app.js
    if (typeof HTMLDialogElement !== 'function') return;

    event.preventDefault();
    event.stopImmediatePropagation();
    const submitter = event.submitter || null;
    askConfirm(form.dataset.confirm).then((ok) => {
        if (!ok) return;
        confirmed.add(form);
        // requestSubmit keeps the clicked button's name/value/formaction (the fee "Pay with ..." buttons rely on this)
        if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter);
        else form.submit();
    });
}, true);

/* ----- Loading buttons ----- */
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (form.hasAttribute('data-realtime-submit-search') || form.method.toLowerCase() === 'dialog') return;
    if (form.dataset.submitting === '1') { event.preventDefault(); return; }
    form.dataset.submitting = '1';
    // Forms that open a new tab or return a file download never navigate this page, so don't lock them
    if (form.target === '_blank') return;
    form.dataset.submitting = '1';
    const button = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
    if (button && !button.matches('[data-no-loading]')) button.classList.add('is-loading');
    form.setAttribute('aria-busy', 'true');
    const release = () => {
        form.dataset.submitting = '';
        form.removeAttribute('aria-busy');
        form.querySelectorAll('.is-loading').forEach((el) => el.classList.remove('is-loading'));
    };
    // Back/forward cache restores the page as it was; a download response leaves the page in place
    window.addEventListener('pageshow', release, { once: true });
    setTimeout(release, 20000);
});

/* ----- Table labels for the phone card layout ----- */
function labelTables(root = document) {
    root.querySelectorAll('table.table--stack').forEach((table) => {
        const headers = [...(table.tHead?.rows[0]?.cells || [])].map((th) => th.textContent.replace(/\s+/g, ' ').trim());
        if (!headers.length) return;
        table.tBodies[0] && [...table.tBodies[0].rows].forEach((row) => {
            let col = 0;
            [...row.cells].forEach((cell) => {
                if (!cell.hasAttribute('data-label')) cell.setAttribute('data-label', headers[col] ?? '');
                col += cell.colSpan || 1;
                // Cells with several parts (bold surname + first names, text + pill) must stay together as one
                // flex item in the phone layout. Cells whose own text is updated by scripts (data-total) are left alone.
                const parts = [...cell.childNodes].filter((n) => n.nodeType === 1 || n.textContent.trim() !== '');
                if (cell.colSpan === 1 && parts.length > 1 && !cell.hasAttribute('data-total')) {
                    const wrap = document.createElement('span');
                    wrap.className = 'cell-value';
                    while (cell.firstChild) wrap.append(cell.firstChild);
                    cell.append(wrap);
                }
            });
        });
    });
}

/* ----- Password show/hide ----- */
function enhancePasswords() {
    document.querySelectorAll('input[type="password"]').forEach((input) => {
        if (input.closest('.pw-wrap') || input.hasAttribute('data-no-toggle')) return;
        const wrap = document.createElement('span');
        wrap.className = 'pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.append(input);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pw-toggle';
        button.setAttribute('aria-label', 'Show password');
        button.setAttribute('aria-pressed', 'false');
        button.innerHTML = ICON.eye;
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.innerHTML = show ? ICON.eyeOff : ICON.eye;
            input.focus();
        });
        wrap.append(button);
    });
}

/* ----- Dismissible alerts ----- */
function enhanceAlerts() {
    document.querySelectorAll('.alert').forEach((alert) => {
        if (alert.dataset.enhanced) return;
        alert.dataset.enhanced = '1';
        if (!alert.querySelector('.alert__body')) {
            const body = document.createElement('div');
            body.className = 'alert__body';
            while (alert.firstChild) body.append(alert.firstChild);
            alert.append(body);
        }
        const dismiss = () => {
            if (reduceMotion) { alert.remove(); return; }
            alert.classList.add('is-leaving');
            setTimeout(() => alert.remove(), 320);
        };
        // Only notices that sit outside a form can be dismissed (validation errors stay until fixed)
        if (!alert.closest('form')) {
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'alert__close';
            close.setAttribute('aria-label', 'Dismiss message');
            close.innerHTML = ICON.close;
            close.addEventListener('click', dismiss);
            alert.append(close);
        }
        if (alert.classList.contains('alert--ok') && alert.getAttribute('role') === 'status') setTimeout(dismiss, 8000);
    });
}

/* ----- Menus and tabs ----- */
function initMenus() {
    const menus = document.querySelectorAll('details.user-menu');
    if (!menus.length) return;
    document.addEventListener('click', (event) => {
        menus.forEach((menu) => { if (menu.open && !menu.contains(event.target)) menu.open = false; });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        menus.forEach((menu) => { if (menu.open) { menu.open = false; menu.querySelector('summary')?.focus(); } });
    });
}

function revealActiveTab() {
    const current = document.querySelector('.portal-nav [aria-current="page"]');
    const list = current?.closest('ul');
    if (!current || !list || list.scrollWidth <= list.clientWidth) return;
    list.scrollLeft = current.offsetLeft - (list.clientWidth - current.offsetWidth) / 2;
}

export function initUi() {
    labelTables();
    enhancePasswords();
    enhanceAlerts();
    initMenus();
    revealActiveTab();
}
