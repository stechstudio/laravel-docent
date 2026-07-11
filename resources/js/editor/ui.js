/* ---------------------------------------------------------------------------
 * Framework-free UI primitives for the editor's node views and slash menu:
 * a tiny DOM builder, a floating popover anchored to an element, and a
 * registry-metadata picker list. Everything is styled with the `.dax-*`
 * tokens shared with the rest of the admin panel.
 * ------------------------------------------------------------------------- */

import { ui as uiIcon } from './icons.js';

/** Minimal hyperscript. `attrs.html` sets innerHTML; `on*` keys bind events. */
export function h(tag, attrs = {}, children = []) {
    const el = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
        if (value == null || value === false) continue;
        if (key === 'html') el.innerHTML = value;
        else if (key === 'class') el.className = value;
        else if (key === 'style') el.setAttribute('style', value);
        else if (key.startsWith('on') && typeof value === 'function') {
            el.addEventListener(key.slice(2).toLowerCase(), value);
        } else el.setAttribute(key, value);
    }
    for (const child of [].concat(children)) {
        if (child == null || child === false) continue;
        el.append(child.nodeType ? child : document.createTextNode(String(child)));
    }
    return el;
}

/**
 * Float `panel` next to `anchor`, appended to <body>, closing on outside
 * pointerdown, Escape, or scroll. Returns a handle with `close()`. The
 * positioning flips above the anchor if there isn't room below.
 */
export function openPopover(anchor, panel, { onClose, placement = 'bottom-start' } = {}) {
    panel.classList.add('dax-pop');
    document.body.appendChild(panel);

    const place = () => {
        const rect = anchor.getBoundingClientRect();
        const ph = panel.offsetHeight;
        const pw = panel.offsetWidth;
        const below = window.innerHeight - rect.bottom;
        let top = placement.startsWith('top') ? rect.top - ph - 6 : rect.bottom + 6;
        if (placement.startsWith('bottom') && below < ph + 12 && rect.top > ph) top = rect.top - ph - 6;
        let left = placement.endsWith('end') ? rect.right - pw : rect.left;
        left = Math.max(8, Math.min(left, window.innerWidth - pw - 8));
        top = Math.max(8, top);
        panel.style.top = `${top}px`;
        panel.style.left = `${left}px`;
    };

    let closed = false;
    const close = () => {
        if (closed) return;
        closed = true;
        document.removeEventListener('pointerdown', onDown, true);
        document.removeEventListener('keydown', onKey, true);
        window.removeEventListener('resize', place);
        window.removeEventListener('scroll', onScroll, true);
        panel.remove();
        onClose && onClose();
    };
    const onDown = (e) => {
        if (!panel.contains(e.target) && !anchor.contains(e.target)) close();
    };
    const onKey = (e) => {
        if (e.key === 'Escape') { e.stopPropagation(); close(); }
    };
    const onScroll = (e) => {
        if (!panel.contains(e.target)) close();
    };

    place();
    // Defer listener attach so the opening click doesn't immediately close it.
    setTimeout(() => {
        if (closed) return;
        document.addEventListener('pointerdown', onDown, true);
        document.addEventListener('keydown', onKey, true);
        window.addEventListener('resize', place);
        window.addEventListener('scroll', onScroll, true);
    }, 0);

    return { close, reposition: place, panel };
}

/**
 * A filterable list of registry entries ({name,label,description}). Calls
 * `onPick(item)` on selection. Renders a search box when there are enough
 * items, and highlights the `current` value.
 */
export function pickerList({ items, current, onPick, empty = 'Nothing registered.', search = null }) {
    const wrap = h('div', { class: 'dax-pick' });
    const list = h('div', { class: 'dax-pick-list' });

    const render = (q) => {
        list.innerHTML = '';
        const needle = (q || '').trim().toLowerCase();
        const filtered = items.filter((it) => {
            if (!needle) return true;
            return `${it.label || ''} ${it.name} ${it.description || ''}`.toLowerCase().includes(needle);
        });
        if (!filtered.length) {
            list.appendChild(h('p', { class: 'dax-pick-empty' }, needle ? 'No matches.' : empty));
            return;
        }
        for (const it of filtered) {
            const active = current != null && it.name === current;
            list.appendChild(h('button', {
                type: 'button',
                class: 'dax-pick-item' + (active ? ' is-active' : ''),
                onclick: () => onPick(it),
            }, [
                h('span', { class: 'dax-pick-title' }, it.label || it.name),
                (it.description || it.label) && h('span', { class: 'dax-pick-desc' }, [
                    h('code', {}, it.name),
                    it.description ? ` — ${it.description}` : '',
                ]),
            ]));
        }
    };

    if (items.length > 6 || search) {
        const input = h('input', {
            type: 'text',
            class: 'dax-pick-search',
            placeholder: search || 'Search…',
            oninput: (e) => render(e.target.value),
        });
        wrap.appendChild(input);
        setTimeout(() => input.focus(), 0);
    }
    wrap.appendChild(list);
    render('');
    return wrap;
}

/** A labelled section header inside a popover. */
export function popHeader(title, { onDelete } = {}) {
    return h('div', { class: 'dax-pop-head' }, [
        h('span', { class: 'dax-pop-title' }, title),
        onDelete && h('button', {
            type: 'button', class: 'dax-pop-x', title: 'Delete block', 'aria-label': 'Delete block',
            html: uiIcon('trash', 14), onclick: onDelete,
        }),
    ]);
}

/** A small labelled text field for popovers. */
export function field(label, value, onInput, { placeholder = '', mono = false } = {}) {
    const input = h('input', {
        type: 'text', class: 'dax-input dax-pop-input' + (mono ? ' dax-mono' : ''),
        value: value || '', placeholder,
        oninput: (e) => onInput(e.target.value),
    });
    return h('label', { class: 'dax-pop-field' }, [
        h('span', { class: 'dax-pop-label' }, label),
        input,
    ]);
}
