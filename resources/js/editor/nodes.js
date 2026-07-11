/* ---------------------------------------------------------------------------
 * Custom Docent nodes for the Tiptap editor.
 *
 * Node names and attributes are the WIRE FORMAT — they must match
 * DESIGN.md §"Tiptap schema contract" and the PHP TiptapDocumentParser /
 * AstToTiptap exactly. parseHTML/renderHTML are minimal (clipboard only; the
 * real interchange is getJSON), but `name` and `addAttributes` are precise.
 *
 * Every node renders as a styled vanilla-JS node view with an inline editing
 * affordance (popover) fed by registry metadata passed in via `context`.
 * ------------------------------------------------------------------------- */

import { Node, mergeAttributes } from '@tiptap/core';
import CodeBlock from '@tiptap/extension-code-block';
import { h, openPopover, pickerList, popHeader, field } from './ui.js';
import { ui, cardIcon, ICON_NAMES } from './icons.js';

/* --- shared editing helpers ------------------------------------------------ */

export function setAttrsOnListItem(editor, getPos, patch) {
    return setAttrs(editor, getPos, patch);
}

function setAttrs(editor, getPos, patch) {
    editor.chain().focus(undefined, { scrollIntoView: false }).command(({ tr }) => {
        const pos = typeof getPos === 'function' ? getPos() : null;
        if (pos == null) return false;
        const node = tr.doc.nodeAt(pos);
        if (!node) return false;
        tr.setNodeMarkup(pos, undefined, { ...node.attrs, ...patch });
        return true;
    }).run();
}

function deleteNode(editor, getPos) {
    editor.chain().focus().command(({ tr }) => {
        const pos = typeof getPos === 'function' ? getPos() : null;
        if (pos == null) return false;
        const node = tr.doc.nodeAt(pos);
        if (!node) return false;
        tr.delete(pos, pos + node.nodeSize);
        return true;
    }).run();
}

function labelFor(list, name) {
    const found = (list || []).find((i) => i.name === name);
    return found ? (found.label || found.name) : name;
}

/** A compact segmented control. options: [{value,label}]. */
function segmented(options, current, onPick) {
    const wrap = h('div', { class: 'dax-seg' });
    for (const opt of options) {
        wrap.appendChild(h('button', {
            type: 'button',
            class: 'dax-seg-btn' + (opt.value === current ? ' is-active' : ''),
            onclick: () => onPick(opt.value),
        }, opt.label));
    }
    return wrap;
}

/** Skeleton for a labelled block container: coloured frame + header + body. */
function frame(accentClass) {
    const dom = h('div', { class: `dax-node ${accentClass}` });
    const header = h('div', { class: 'dax-node-head', contenteditable: 'false' });
    const body = h('div', { class: 'dax-node-body' });
    dom.append(header, body);
    return { dom, header, body };
}

/** A header pill: icon + text, e.g. "🔒 Shown when user CAN: billing.manage". */
function pill(iconName, parts) {
    const el = h('span', { class: 'dax-node-pill' }, [
        h('span', { class: 'dax-node-ic', html: ui(iconName, 14) }),
    ]);
    for (const p of [].concat(parts)) {
        if (p == null) continue;
        el.appendChild(typeof p === 'string' ? h('span', { class: 'dax-node-kicker' }, p) : p);
    }
    return el;
}

function editButton(onClick) {
    return h('button', {
        type: 'button', class: 'dax-node-btn', title: 'Edit', 'aria-label': 'Edit block',
        html: ui('edit', 14), onmousedown: (e) => e.preventDefault(), onclick: onClick,
    });
}

function deleteButton(onClick) {
    return h('button', {
        type: 'button', class: 'dax-node-btn dax-node-btn-danger', title: 'Delete', 'aria-label': 'Delete block',
        html: ui('close', 15), onmousedown: (e) => e.preventDefault(), onclick: onClick,
    });
}

/** Common node-view plumbing for container nodes with a live header. */
function containerView({ editor, getPos, node, accentClass, renderHead }) {
    const { dom, header, body } = frame(accentClass);
    let current = node;
    const paint = () => {
        header.innerHTML = '';
        renderHead(header, current, { edit: () => paint(), anchor: header });
    };
    paint();
    return {
        dom,
        contentDOM: body,
        update(updated) {
            if (updated.type !== node.type) return false;
            current = updated;
            paint();
            return true;
        },
        ignoreMutation: (m) => m.type !== 'selection' && !body.contains(m.target),
        stopEvent: (e) => !body.contains(e.target),
    };
}

/* --- docsGate -------------------------------------------------------------- */

export function DocsGate(context) {
    return Node.create({
        name: 'docsGate',
        group: 'block',
        content: 'block+',
        defining: true,
        addOptions: () => ({ context }),
        addAttributes: () => ({
            mode: { default: 'can' },
            ability: { default: '' },
            arguments: { default: [] },
        }),
        parseHTML: () => [{ tag: 'div[data-docs-gate]' }],
        renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes(HTMLAttributes, { 'data-docs-gate': '' }), 0],
        addNodeView() {
            const ctx = this.options.context;
            return ({ node, editor, getPos }) => containerView({
                editor, getPos, node, accentClass: 'dax-node-gate',
                renderHead: (head, n, { anchor }) => {
                    const verb = n.attrs.mode === 'cannot' ? 'Hidden when user CAN' : 'Shown when user CAN';
                    head.append(
                        pill('lock', [verb + ': ', h('code', { class: 'dax-node-code' }, n.attrs.ability || '—')]),
                        h('span', { class: 'dax-node-actions' }, [
                            editButton(() => openGateEditor(editor, getPos, n, ctx, anchor)),
                            deleteButton(() => deleteNode(editor, getPos)),
                        ]),
                    );
                },
            });
        },
    });
}

function openGateEditor(editor, getPos, node, ctx, anchor) {
    const panel = h('div', { class: 'dax-pop-body' });
    panel.append(
        popHeader('Authorization gate'),
        h('div', { class: 'dax-pop-field' }, [
            h('span', { class: 'dax-pop-label' }, 'Mode'),
            segmented(
                [{ value: 'can', label: 'Shown when CAN' }, { value: 'cannot', label: 'Hidden when CAN' }],
                node.attrs.mode,
                (v) => { setAttrs(editor, getPos, { mode: v }); pop.close(); },
            ),
        ]),
        field('Arguments (comma-separated)', (node.attrs.arguments || []).join(', '),
            (v) => setAttrs(editor, getPos, { arguments: splitArgs(v) }), { mono: true }),
        h('span', { class: 'dax-pop-label', style: 'padding:0 .1rem' }, 'Ability'),
        pickerList({
            items: (ctx.meta().abilities || []).map((a) => ({ name: a, label: a })),
            current: node.attrs.ability,
            search: 'Search abilities…',
            empty: 'No gate abilities registered.',
            onPick: (it) => { setAttrs(editor, getPos, { ability: it.name }); pop.close(); },
        }),
    );
    const pop = openPopover(anchor, panel);
}

/* --- docsCondition --------------------------------------------------------- */

export function DocsCondition(context) {
    return Node.create({
        name: 'docsCondition',
        group: 'block',
        content: 'block+',
        defining: true,
        addOptions: () => ({ context }),
        addAttributes: () => ({
            condition: { default: '' },
            negated: { default: false },
            arguments: { default: [] },
        }),
        parseHTML: () => [{ tag: 'div[data-docs-condition]' }],
        renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes(HTMLAttributes, { 'data-docs-condition': '' }), 0],
        addNodeView() {
            const ctx = this.options.context;
            return ({ node, editor, getPos }) => containerView({
                editor, getPos, node, accentClass: 'dax-node-cond',
                renderHead: (head, n, { anchor }) => {
                    head.append(
                        pill('branch', [
                            (n.attrs.negated ? 'Hidden when' : 'Shown when') + ': ',
                            h('code', { class: 'dax-node-code' }, labelFor(ctx.meta().conditions, n.attrs.condition) || '—'),
                        ]),
                        h('span', { class: 'dax-node-actions' }, [
                            editButton(() => openConditionEditor(editor, getPos, n, ctx, anchor)),
                            deleteButton(() => deleteNode(editor, getPos)),
                        ]),
                    );
                },
            });
        },
    });
}

function openConditionEditor(editor, getPos, node, ctx, anchor) {
    const panel = h('div', { class: 'dax-pop-body' });
    panel.append(
        popHeader('Condition'),
        h('div', { class: 'dax-pop-field' }, [
            h('span', { class: 'dax-pop-label' }, 'Behaviour'),
            segmented(
                [{ value: false, label: 'Show when true' }, { value: true, label: 'Hide when true' }],
                node.attrs.negated,
                (v) => { setAttrs(editor, getPos, { negated: v }); pop.close(); },
            ),
        ]),
        field('Arguments (comma-separated)', (node.attrs.arguments || []).join(', '),
            (v) => setAttrs(editor, getPos, { arguments: splitArgs(v) }), { mono: true }),
        h('span', { class: 'dax-pop-label', style: 'padding:0 .1rem' }, 'Condition'),
        pickerList({
            items: ctx.meta().conditions || [],
            current: node.attrs.condition,
            search: 'Search conditions…',
            empty: 'No conditions registered.',
            onPick: (it) => { setAttrs(editor, getPos, { condition: it.name }); pop.close(); },
        }),
    );
    const pop = openPopover(anchor, panel);
}

/* --- docsAudience ---------------------------------------------------------- */

export function DocsAudience(context) {
    return Node.create({
        name: 'docsAudience',
        group: 'block',
        content: 'block+',
        defining: true,
        addOptions: () => ({ context }),
        addAttributes: () => ({ name: { default: '' } }),
        parseHTML: () => [{ tag: 'div[data-docs-audience]' }],
        renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes(HTMLAttributes, { 'data-docs-audience': '' }), 0],
        addNodeView() {
            const ctx = this.options.context;
            return ({ node, editor, getPos }) => containerView({
                editor, getPos, node, accentClass: 'dax-node-aud',
                renderHead: (head, n, { anchor }) => {
                    head.append(
                        pill('audience', ['Audience: ', h('code', { class: 'dax-node-code' }, labelFor(ctx.meta().audiences, n.attrs.name) || '—')]),
                        h('span', { class: 'dax-node-actions' }, [
                            editButton(() => openAudienceEditor(editor, getPos, n, ctx, anchor)),
                            deleteButton(() => deleteNode(editor, getPos)),
                        ]),
                    );
                },
            });
        },
    });
}

function openAudienceEditor(editor, getPos, node, ctx, anchor) {
    const panel = h('div', { class: 'dax-pop-body' });
    panel.append(
        popHeader('Audience'),
        pickerList({
            items: ctx.meta().audiences || [],
            current: node.attrs.name,
            search: 'Search audiences…',
            empty: 'No audiences registered.',
            onPick: (it) => { setAttrs(editor, getPos, { name: it.name }); pop.close(); },
        }),
    );
    const pop = openPopover(anchor, panel);
}

/* --- docsCallout ----------------------------------------------------------- */

const CALLOUT_TYPES = ['note', 'tip', 'info', 'warning', 'danger'];

export function DocsCallout(context) {
    return Node.create({
        name: 'docsCallout',
        group: 'block',
        content: 'block+',
        defining: true,
        addOptions: () => ({ context }),
        addAttributes: () => ({
            type: { default: 'note' },
            title: { default: null },
        }),
        parseHTML: () => [{ tag: 'div[data-docs-callout]' }],
        renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes(HTMLAttributes, { 'data-docs-callout': '' }), 0],
        addNodeView() {
            const ctx = this.options.context;
            // Render with the READER's callout markup + classes so what you
            // write is exactly what readers get; the editing affordances float
            // in the corner and appear on hover.
            return ({ node, editor, getPos }) => {
                const dom = h('div', {});
                const actions = h('span', { class: 'dax-float-actions', contenteditable: 'false' });
                const titleEl = h('div', { class: 'docent-callout-title', contenteditable: 'false' });
                const content = h('div', { class: 'docent-callout-content' });
                dom.append(actions, titleEl, content);

                let current = node;
                const paint = () => {
                    dom.className = `docent-callout docent-callout-${current.attrs.type} dax-nv-callout`;
                    dom.setAttribute('data-callout', current.attrs.type);
                    titleEl.textContent = current.attrs.title || '';
                    titleEl.style.display = current.attrs.title ? '' : 'none';
                    actions.innerHTML = '';
                    actions.append(
                        editButton(() => openCalloutEditor(editor, getPos, current, ctx, actions)),
                        deleteButton(() => deleteNode(editor, getPos)),
                    );
                };
                paint();

                return {
                    dom,
                    contentDOM: content,
                    update(updated) {
                        if (updated.type !== node.type) return false;
                        current = updated;
                        paint();
                        return true;
                    },
                    ignoreMutation: (m) => m.type !== 'selection' && !content.contains(m.target),
                    stopEvent: (e) => actions.contains(e.target),
                };
            };
        },
    });
}

function openCalloutEditor(editor, getPos, node, ctx, anchor) {
    const panel = h('div', { class: 'dax-pop-body' });
    panel.append(
        popHeader('Callout'),
        h('div', { class: 'dax-pop-field' }, [
            h('span', { class: 'dax-pop-label' }, 'Type'),
            segmented(
                CALLOUT_TYPES.map((t) => ({ value: t, label: t.charAt(0).toUpperCase() + t.slice(1) })),
                node.attrs.type,
                (v) => setAttrs(editor, getPos, { type: v }),
            ),
        ]),
        field('Title (optional)', node.attrs.title || '',
            (v) => setAttrs(editor, getPos, { title: v.trim() === '' ? null : v })),
    );
    const pop = openPopover(anchor, panel);
}

/* --- docsCards / docsCard -------------------------------------------------- */

export function DocsCards(context) {
    return Node.create({
        name: 'docsCards',
        group: 'block',
        content: 'docsCard+',
        addOptions: () => ({ context }),
        addAttributes: () => ({ columns: { default: 2 } }),
        parseHTML: () => [{ tag: 'div[data-docs-cards]' }],
        renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes(HTMLAttributes, { 'data-docs-cards': '' }), 0],
        addNodeView() {
            return ({ node, editor, getPos }) => {
                const dom = h('div', { class: 'dax-node dax-node-cards' });
                const header = h('div', { class: 'dax-node-head', contenteditable: 'false' });
                const grid = h('div', { class: 'dax-cards-grid', 'data-columns': node.attrs.columns });
                dom.append(header, grid);
                let current = node;
                const paint = () => {
                    header.innerHTML = '';
                    grid.setAttribute('data-columns', current.attrs.columns);
                    header.append(
                        pill('cards', ['Cards']),
                        h('span', { class: 'dax-node-actions' }, [
                            segmented([2, 3, 4].map((c) => ({ value: c, label: `${c}` })), current.attrs.columns,
                                (v) => setAttrs(editor, getPos, { columns: v })),
                            h('button', {
                                type: 'button', class: 'dax-node-btn', title: 'Add card',
                                html: ui('plus', 14), onmousedown: (e) => e.preventDefault(),
                                onclick: () => addCard(editor, getPos),
                            }),
                            deleteButton(() => deleteNode(editor, getPos)),
                        ]),
                    );
                };
                paint();
                return {
                    dom,
                    contentDOM: grid,
                    update(updated) {
                        if (updated.type !== node.type) return false;
                        current = updated;
                        paint();
                        return true;
                    },
                    ignoreMutation: (m) => m.type !== 'selection' && !grid.contains(m.target),
                    stopEvent: (e) => !grid.contains(e.target),
                };
            };
        },
    });
}

function addCard(editor, getPos) {
    editor.chain().focus().command(({ tr, state }) => {
        const pos = typeof getPos === 'function' ? getPos() : null;
        if (pos == null) return false;
        const node = tr.doc.nodeAt(pos);
        if (!node) return false;
        const card = state.schema.nodes.docsCard.createAndFill();
        if (!card) return false;
        tr.insert(pos + node.nodeSize - 1, card);
        return true;
    }).run();
}

export function DocsCard(context) {
    return Node.create({
        name: 'docsCard',
        content: 'block+',
        defining: true,
        addOptions: () => ({ context }),
        addAttributes: () => ({
            title: { default: null },
            icon: { default: null },
            href: { default: null },
        }),
        parseHTML: () => [{ tag: 'div[data-docs-card]' }],
        renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes(HTMLAttributes, { 'data-docs-card': '' }), 0],
        addNodeView() {
            const ctx = this.options.context;
            return ({ node, editor, getPos }) => {
                const dom = h('div', { class: 'dax-card' });
                const header = h('div', { class: 'dax-card-head', contenteditable: 'false' });
                const body = h('div', { class: 'dax-card-body' });
                dom.append(header, body);
                let current = node;
                const paint = () => {
                    header.innerHTML = '';
                    const ic = current.attrs.icon
                        ? h('span', { class: 'dax-card-ic', html: cardIcon(current.attrs.icon, 18) })
                        : h('span', { class: 'dax-card-ic dax-card-ic-empty', html: ui('image', 15) });
                    header.append(
                        h('button', {
                            type: 'button', class: 'dax-card-metabtn', onmousedown: (e) => e.preventDefault(),
                            onclick: () => openCardEditor(editor, getPos, current, ctx, header),
                        }, [
                            ic,
                            h('span', { class: 'dax-card-title' }, current.attrs.title || 'Untitled card'),
                            current.attrs.href && h('span', { class: 'dax-card-href', html: ui('link', 12) }),
                        ]),
                        deleteButton(() => deleteNode(editor, getPos)),
                    );
                };
                paint();
                return {
                    dom,
                    contentDOM: body,
                    update(updated) {
                        if (updated.type !== node.type) return false;
                        current = updated;
                        paint();
                        return true;
                    },
                    ignoreMutation: (m) => m.type !== 'selection' && !body.contains(m.target),
                    stopEvent: (e) => !body.contains(e.target),
                };
            };
        },
    });
}

function openCardEditor(editor, getPos, node, ctx, anchor) {
    const panel = h('div', { class: 'dax-pop-body' });
    const iconGrid = h('div', { class: 'dax-icon-grid' });
    for (const name of ICON_NAMES) {
        iconGrid.appendChild(h('button', {
            type: 'button', title: name,
            class: 'dax-icon-cell' + (node.attrs.icon === name ? ' is-active' : ''),
            html: cardIcon(name, 18),
            onclick: () => { setAttrs(editor, getPos, { icon: node.attrs.icon === name ? null : name }); pop.close(); },
        }));
    }
    panel.append(
        popHeader('Card'),
        field('Title', node.attrs.title || '', (v) => setAttrs(editor, getPos, { title: v.trim() === '' ? null : v })),
        field('Link (href / slug)', node.attrs.href || '', (v) => setAttrs(editor, getPos, { href: v.trim() === '' ? null : v }), { mono: true, placeholder: 'getting-started' }),
        h('span', { class: 'dax-pop-label', style: 'padding:0 .1rem' }, 'Icon'),
        iconGrid,
    );
    const pop = openPopover(anchor, panel);
}

/* --- docsValue (inline atom) ----------------------------------------------- */

export function DocsValue(context) {
    return Node.create({
        name: 'docsValue',
        group: 'inline',
        inline: true,
        atom: true,
        selectable: true,
        addOptions: () => ({ context }),
        addAttributes: () => ({
            key: { default: '' },
            arguments: { default: [] },
        }),
        parseHTML: () => [{ tag: 'span[data-docs-value]' }],
        renderHTML: ({ HTMLAttributes }) => ['span', mergeAttributes(HTMLAttributes, { 'data-docs-value': '' })],
        addNodeView() {
            const ctx = this.options.context;
            return ({ node, editor, getPos }) => {
                const dom = h('span', { class: 'dax-inline-chip dax-chip-value', contenteditable: 'false' });
                const paint = (n) => {
                    dom.innerHTML = '';
                    dom.append(
                        h('span', { class: 'dax-inline-ic', html: ui('value', 12) }),
                        h('span', {}, labelFor(ctx.meta().values, n.attrs.key) || n.attrs.key || 'value'),
                    );
                };
                paint(node);
                dom.addEventListener('click', () => openValueEditor(editor, getPos, node, ctx, dom));
                return {
                    dom,
                    update(updated) {
                        if (updated.type !== node.type) return false;
                        paint(updated);
                        return true;
                    },
                };
            };
        },
    });
}

function openValueEditor(editor, getPos, node, ctx, anchor) {
    const panel = h('div', { class: 'dax-pop-body' });
    panel.append(
        popHeader('Dynamic value'),
        pickerList({
            items: ctx.meta().values || [],
            current: node.attrs.key,
            search: 'Search values…',
            empty: 'No values registered.',
            onPick: (it) => { setAttrs(editor, getPos, { key: it.name }); pop.close(); },
        }),
    );
    const pop = openPopover(anchor, panel);
}

/* --- docsAppLink (inline atom) --------------------------------------------- */

export function DocsAppLink(context) {
    return Node.create({
        name: 'docsAppLink',
        group: 'inline',
        inline: true,
        atom: true,
        selectable: true,
        addOptions: () => ({ context }),
        addAttributes: () => ({
            kind: { default: 'link' },
            key: { default: '' },
            parameters: { default: [] },
        }),
        parseHTML: () => [{ tag: 'span[data-docs-applink]' }],
        renderHTML: ({ HTMLAttributes }) => ['span', mergeAttributes(HTMLAttributes, { 'data-docs-applink': '' })],
        addNodeView() {
            const ctx = this.options.context;
            return ({ node, editor, getPos }) => {
                const dom = h('span', { class: 'dax-inline-chip dax-chip-link', contenteditable: 'false' });
                const paint = (n) => {
                    dom.innerHTML = '';
                    const label = n.attrs.kind === 'route' ? n.attrs.key : (labelFor(ctx.meta().links, n.attrs.key) || n.attrs.key);
                    dom.append(
                        h('span', { class: 'dax-inline-ic', html: ui('link', 12) }),
                        h('span', {}, label || 'link'),
                    );
                };
                paint(node);
                dom.addEventListener('click', () => openAppLinkEditor(editor, getPos, node, ctx, dom));
                return {
                    dom,
                    update(updated) {
                        if (updated.type !== node.type) return false;
                        paint(updated);
                        return true;
                    },
                };
            };
        },
    });
}

function openAppLinkEditor(editor, getPos, node, ctx, anchor) {
    const panel = h('div', { class: 'dax-pop-body' });
    panel.append(
        popHeader('App link'),
        h('div', { class: 'dax-pop-field' }, [
            h('span', { class: 'dax-pop-label' }, 'Kind'),
            segmented(
                [{ value: 'link', label: 'Registered link' }, { value: 'route', label: 'Named route' }],
                node.attrs.kind,
                (v) => setAttrs(editor, getPos, { kind: v }),
            ),
        ]),
        field('Route name', node.attrs.key || '', (v) => setAttrs(editor, getPos, { key: v }), { mono: true, placeholder: 'billing.settings' }),
        h('span', { class: 'dax-pop-label', style: 'padding:0 .1rem' }, 'Registered links'),
        pickerList({
            items: ctx.meta().links || [],
            current: node.attrs.kind === 'link' ? node.attrs.key : null,
            search: 'Search links…',
            empty: 'No links registered.',
            onPick: (it) => { setAttrs(editor, getPos, { kind: 'link', key: it.name }); pop.close(); },
        }),
    );
    const pop = openPopover(anchor, panel);
}

/* --- docsInclude (block atom) ---------------------------------------------- */

export function DocsInclude(context) {
    return Node.create({
        name: 'docsInclude',
        group: 'block',
        atom: true,
        selectable: true,
        addOptions: () => ({ context }),
        addAttributes: () => ({ name: { default: '' } }),
        parseHTML: () => [{ tag: 'div[data-docs-include]' }],
        renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes(HTMLAttributes, { 'data-docs-include': '' })],
        addNodeView() {
            return ({ node, editor, getPos }) => {
                const dom = h('div', { class: 'dax-node dax-widget', contenteditable: 'false' });
                const paint = (n) => {
                    dom.innerHTML = '';
                    dom.append(
                        h('span', { class: 'dax-widget-ic', html: ui('include', 16) }),
                        h('span', { class: 'dax-widget-label' }, [
                            h('span', { class: 'dax-widget-kind' }, 'Include'),
                            h('code', { class: 'dax-node-code' }, n.attrs.name || '—'),
                        ]),
                        h('span', { class: 'dax-node-actions' }, [
                            editButton(() => openIncludeEditor(editor, getPos, n, dom)),
                            deleteButton(() => deleteNode(editor, getPos)),
                        ]),
                    );
                };
                paint(node);
                return {
                    dom,
                    update(updated) {
                        if (updated.type !== node.type) return false;
                        paint(updated);
                        return true;
                    },
                    stopEvent: () => true,
                    ignoreMutation: () => true,
                };
            };
        },
    });
}

function openIncludeEditor(editor, getPos, node, anchor) {
    const panel = h('div', { class: 'dax-pop-body' });
    panel.append(
        popHeader('Include partial'),
        field('Partial name', node.attrs.name || '', (v) => setAttrs(editor, getPos, { name: v }), { mono: true, placeholder: 'permissions-note' }),
    );
    openPopover(anchor, panel);
}

/* --- docsComponent (block atom) -------------------------------------------- */

export function DocsComponent(context) {
    return Node.create({
        name: 'docsComponent',
        group: 'block',
        atom: true,
        selectable: true,
        addOptions: () => ({ context }),
        addAttributes: () => ({
            name: { default: '' },
            attributes: { default: {} },
        }),
        parseHTML: () => [{ tag: 'div[data-docs-component]' }],
        renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes(HTMLAttributes, { 'data-docs-component': '' })],
        addNodeView() {
            const ctx = this.options.context;
            return ({ node, editor, getPos }) => {
                const dom = h('div', { class: 'dax-node dax-widget dax-widget-component', contenteditable: 'false' });
                const paint = (n) => {
                    dom.innerHTML = '';
                    const attrs = n.attrs.attributes || {};
                    const chips = Object.entries(attrs).map(([k, v]) =>
                        h('span', { class: 'dax-attr-chip' }, [h('code', {}, k), `=${v}`]));
                    dom.append(
                        h('span', { class: 'dax-widget-ic', html: ui('component', 16) }),
                        h('span', { class: 'dax-widget-label' }, [
                            h('span', { class: 'dax-widget-kind' }, 'Component'),
                            h('span', { class: 'dax-widget-name' }, labelFor(ctx.meta().components, n.attrs.name) || n.attrs.name || '—'),
                            chips.length ? h('span', { class: 'dax-attr-row' }, chips) : null,
                        ]),
                        h('span', { class: 'dax-node-actions' }, [
                            editButton(() => openComponentEditor(editor, getPos, n, ctx, dom)),
                            deleteButton(() => deleteNode(editor, getPos)),
                        ]),
                    );
                };
                paint(node);
                return {
                    dom,
                    update(updated) {
                        if (updated.type !== node.type) return false;
                        paint(updated);
                        return true;
                    },
                    stopEvent: () => true,
                    ignoreMutation: () => true,
                };
            };
        },
    });
}

function openComponentEditor(editor, getPos, node, ctx, anchor) {
    const panel = h('div', { class: 'dax-pop-body' });
    panel.append(
        popHeader('Component embed'),
        pickerList({
            items: ctx.meta().components || [],
            current: node.attrs.name,
            search: 'Search components…',
            empty: 'No components registered.',
            onPick: (it) => { setAttrs(editor, getPos, { name: it.name }); pop.close(); },
        }),
    );
    const pop = openPopover(anchor, panel);
}

/* --- docsHtml (block atom, read-only, not insertable) ---------------------- */

export function DocsHtml() {
    return Node.create({
        name: 'docsHtml',
        group: 'block',
        atom: true,
        selectable: true,
        addAttributes: () => ({ html: { default: '' } }),
        parseHTML: () => [{ tag: 'div[data-docs-html]' }],
        renderHTML: ({ HTMLAttributes }) => ['div', mergeAttributes(HTMLAttributes, { 'data-docs-html': '' })],
        addNodeView() {
            return ({ node, editor, getPos }) => {
                const dom = h('div', { class: 'dax-node dax-widget dax-widget-html', contenteditable: 'false' });
                dom.append(
                    h('span', { class: 'dax-widget-ic', html: ui('code', 16) }),
                    h('span', { class: 'dax-widget-label' }, [
                        h('span', { class: 'dax-widget-kind' }, 'HTML block'),
                        h('span', { class: 'dax-widget-name' }, 'preserved verbatim'),
                    ]),
                    h('span', { class: 'dax-node-actions' }, [
                        deleteButton(() => deleteNode(editor, getPos)),
                    ]),
                );
                return { dom, stopEvent: () => true, ignoreMutation: () => true };
            };
        },
    });
}

/* --- codeBlock (extended: language + title header) ------------------------- */

export function DocsCodeBlock() {
    return CodeBlock.extend({
        addAttributes() {
            return {
                language: { default: null },
                title: { default: null },
            };
        },
        addNodeView() {
            // The READER's code-card chrome (.docent-code + header) so the
            // block looks identical in Write and Preview; language and title
            // are edited inline through blended header inputs. No client-side
            // highlighting — the preview shows the real Phiki render.
            return ({ node, editor, getPos }) => {
                const dom = h('div', { class: 'docent-code dax-nv-code' });
                const head = h('div', { class: 'docent-code-header', contenteditable: 'false' });
                const langInput = h('input', {
                    type: 'text', class: 'dax-code-lang', placeholder: 'language', spellcheck: 'false',
                    value: node.attrs.language || '',
                    oninput: (e) => setAttrs(editor, getPos, { language: e.target.value.trim() === '' ? null : e.target.value.trim() }),
                });
                const titleInput = h('input', {
                    type: 'text', class: 'dax-code-title', placeholder: 'filename (optional)', spellcheck: 'false',
                    value: node.attrs.title || '',
                    oninput: (e) => setAttrs(editor, getPos, { title: e.target.value.trim() === '' ? null : e.target.value }),
                });
                head.append(langInput, titleInput);
                const pre = h('pre', { class: 'dax-code-pre' });
                const code = document.createElement('code');
                pre.appendChild(code);
                dom.append(head, pre);
                return {
                    dom,
                    contentDOM: code,
                    update(updated) {
                        if (updated.type !== node.type) return false;
                        if (document.activeElement !== langInput) langInput.value = updated.attrs.language || '';
                        if (document.activeElement !== titleInput) titleInput.value = updated.attrs.title || '';
                        return true;
                    },
                    ignoreMutation: (m) => m.type !== 'selection' && !code.contains(m.target),
                    stopEvent: (e) => !code.contains(e.target),
                };
            };
        },
    });
}

/* --- helpers --------------------------------------------------------------- */

function splitArgs(value) {
    return String(value || '')
        .split(',')
        .map((s) => s.trim())
        .filter((s) => s !== '');
}

/** All custom Docent extensions, wired to the shared registry context. */
export function docentNodes(context) {
    return [
        DocsGate(context),
        DocsCondition(context),
        DocsAudience(context),
        DocsCallout(context),
        DocsCards(context),
        DocsCard(context),
        DocsValue(context),
        DocsAppLink(context),
        DocsInclude(context),
        DocsComponent(context),
        DocsHtml(),
    ];
}
