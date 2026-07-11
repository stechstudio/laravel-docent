/* ---------------------------------------------------------------------------
 * Slash command menu. Typing "/" at the start of an empty block opens a
 * filterable command list (headings, lists, quote, code, table, image, divider,
 * plus every Docent block/inline node). Registry-backed items open the relevant
 * picker immediately; picking inserts the configured node.
 * ------------------------------------------------------------------------- */

import { Extension } from '@tiptap/core';
import Suggestion from '@tiptap/suggestion';
import { TextSelection } from '@tiptap/pm/state';
import { h, openPopover, pickerList } from './ui.js';
import { ui } from './icons.js';

/* Insert helpers ----------------------------------------------------------- */

function insertBlock(editor, range, json) {
    editor.chain().focus().deleteRange(range).insertContent(json).run();
}

/**
 * Insert a container node with an empty paragraph and drop the cursor inside
 * it. `insertContent` leaves the selection after the node, so we re-select the
 * first text position within the freshly inserted block.
 */
function insertContainer(editor, range, json) {
    const at = range.from;
    editor.chain().focus().deleteRange(range).insertContentAt(at, json)
        .command(({ tr, dispatch }) => {
            if (dispatch) {
                const pos = Math.min(at + 2, tr.doc.content.size);
                tr.setSelection(TextSelection.near(tr.doc.resolve(pos)));
            }
            return true;
        }).run();
}

/* The command catalogue. Each item: {title, description, icon, group, keywords,
 * run(editor, range, context)}. Registry pickers are opened against the caret. */
function commands() {
    return [
        { title: 'Heading 1', description: 'Large section heading', icon: 'heading', group: 'Basic', keywords: 'h1 title',
            run: (e, r) => insertBlock(e, r, { type: 'heading', attrs: { level: 1 } }) },
        { title: 'Heading 2', description: 'Medium section heading', icon: 'heading', group: 'Basic', keywords: 'h2',
            run: (e, r) => insertBlock(e, r, { type: 'heading', attrs: { level: 2 } }) },
        { title: 'Heading 3', description: 'Small section heading', icon: 'heading', group: 'Basic', keywords: 'h3',
            run: (e, r) => insertBlock(e, r, { type: 'heading', attrs: { level: 3 } }) },
        { title: 'Bullet list', description: 'A simple bulleted list', icon: 'list', group: 'Basic', keywords: 'ul unordered',
            run: (e, r) => e.chain().focus().deleteRange(r).toggleBulletList().run() },
        { title: 'Numbered list', description: 'An ordered list', icon: 'list-ordered', group: 'Basic', keywords: 'ol ordered',
            run: (e, r) => e.chain().focus().deleteRange(r).toggleOrderedList().run() },
        { title: 'Task list', description: 'A checkable to-do list', icon: 'task', group: 'Basic', keywords: 'todo checkbox check',
            run: (e, r) => insertBlock(e, r, { type: 'bulletList', content: [{ type: 'listItem', attrs: { checked: false }, content: [{ type: 'paragraph' }] }] }) },
        { title: 'Quote', description: 'Capture a quotation', icon: 'quote', group: 'Basic', keywords: 'blockquote',
            run: (e, r) => e.chain().focus().deleteRange(r).toggleBlockquote().run() },
        { title: 'Code block', description: 'Code with a language + title', icon: 'code', group: 'Basic', keywords: 'pre monospace',
            run: (e, r) => insertBlock(e, r, { type: 'codeBlock' }) },
        { title: 'Table', description: '3×3 table with a header row', icon: 'table', group: 'Basic', keywords: 'grid',
            run: (e, r) => e.chain().focus().deleteRange(r).insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
        { title: 'Image', description: 'Upload and embed an image', icon: 'image', group: 'Basic', keywords: 'picture photo upload',
            run: (e, r, ctx) => { e.chain().focus().deleteRange(r).run(); ctx.image && ctx.image(); } },
        { title: 'Divider', description: 'A horizontal rule', icon: 'divider', group: 'Basic', keywords: 'hr line separator',
            run: (e, r) => e.chain().focus().deleteRange(r).setHorizontalRule().run() },

        { title: 'Callout', description: 'Highlighted note / tip / warning', icon: 'callout', group: 'Docent', keywords: 'note tip info warning danger admonition',
            run: (e, r) => insertContainer(e, r, { type: 'docsCallout', attrs: { type: 'note' }, content: [{ type: 'paragraph' }] }) },
        { title: 'Gate', description: 'Show or hide by ability', icon: 'lock', group: 'Docent', keywords: 'can cannot permission authorize auth',
            run: (e, r, ctx, anchor) => pickThenContainer(e, r, anchor, {
                items: (ctx.meta().abilities || []).map((a) => ({ name: a, label: a })), search: 'Search abilities…',
                empty: 'No gate abilities registered.',
                build: (it) => ({ type: 'docsGate', attrs: { mode: 'can', ability: it.name, arguments: [] }, content: [{ type: 'paragraph' }] }),
            }) },
        { title: 'Condition', description: 'Show or hide by a registered condition', icon: 'branch', group: 'Docent', keywords: 'when unless if',
            run: (e, r, ctx, anchor) => pickThenContainer(e, r, anchor, {
                items: ctx.meta().conditions || [], search: 'Search conditions…', empty: 'No conditions registered.',
                build: (it) => ({ type: 'docsCondition', attrs: { condition: it.name, negated: false, arguments: [] }, content: [{ type: 'paragraph' }] }),
            }) },
        { title: 'Audience', description: 'Show to a named audience', icon: 'audience', group: 'Docent', keywords: 'segment role group',
            run: (e, r, ctx, anchor) => pickThenContainer(e, r, anchor, {
                items: ctx.meta().audiences || [], search: 'Search audiences…', empty: 'No audiences registered.',
                build: (it) => ({ type: 'docsAudience', attrs: { name: it.name }, content: [{ type: 'paragraph' }] }),
            }) },
        { title: 'Cards', description: 'A grid of linkable cards', icon: 'cards', group: 'Docent', keywords: 'grid tiles',
            run: (e, r) => insertContainer(e, r, { type: 'docsCards', attrs: { columns: 2 }, content: [
                { type: 'docsCard', attrs: { title: 'New card', icon: null, href: null }, content: [{ type: 'paragraph' }] },
            ] }) },
        { title: 'Include', description: 'Embed a reusable partial', icon: 'include', group: 'Docent', keywords: 'partial snippet reuse',
            run: (e, r) => insertBlock(e, r, { type: 'docsInclude', attrs: { name: '' } }) },
        { title: 'Component', description: 'Embed a registered component', icon: 'component', group: 'Docent', keywords: 'widget embed',
            run: (e, r, ctx, anchor) => pickThenAtom(e, r, anchor, {
                items: ctx.meta().components || [], search: 'Search components…', empty: 'No components registered.',
                build: (it) => ({ type: 'docsComponent', attrs: { name: it.name, attributes: {} } }),
            }) },
        { title: 'Value chip', description: 'Inline dynamic value', icon: 'value', group: 'Docent', keywords: 'variable dynamic token',
            run: (e, r, ctx, anchor) => pickThenAtom(e, r, anchor, {
                items: ctx.meta().values || [], search: 'Search values…', empty: 'No values registered.',
                build: (it) => ({ type: 'docsValue', attrs: { key: it.name, arguments: [] } }),
            }) },
        { title: 'App link', description: 'Inline link to a registered link/route', icon: 'link', group: 'Docent', keywords: 'route url internal',
            run: (e, r, ctx, anchor) => pickThenAtom(e, r, anchor, {
                items: ctx.meta().links || [], search: 'Search links…', empty: 'No links registered.',
                build: (it) => ({ type: 'docsAppLink', attrs: { kind: 'link', key: it.name, parameters: [] } }),
            }) },
    ];
}

function pickThenContainer(editor, range, anchorRect, { items, search, empty, build }) {
    editor.chain().focus().deleteRange(range).run();
    openCaretPicker(editor, anchorRect, { items, search, empty, onPick: (it) => {
        editor.chain().focus().insertContent(build(it)).run();
    } });
}

function pickThenAtom(editor, range, anchorRect, { items, search, empty, build }) {
    editor.chain().focus().deleteRange(range).run();
    openCaretPicker(editor, anchorRect, { items, search, empty, onPick: (it) => {
        editor.chain().focus().insertContent(build(it)).run();
    } });
}

/** Open a registry picker anchored to the current caret coordinates. */
function openCaretPicker(editor, anchorRect, opts) {
    const anchor = h('span', {});
    anchor.getBoundingClientRect = () => anchorRect;
    const panel = h('div', { class: 'dax-pop-body' });
    panel.appendChild(pickerList({ ...opts, onPick: (it) => { opts.onPick(it); pop.close(); } }));
    const pop = openPopover(anchor, panel);
}

/* Menu renderer ------------------------------------------------------------ */

function renderer(context) {
    return () => {
        let el, items, selected = 0, cmd, range;

        const paint = () => {
            el.innerHTML = '';
            if (!items.length) {
                el.appendChild(h('p', { class: 'dax-pick-empty' }, 'No blocks match.'));
                return;
            }
            let lastGroup = null;
            items.forEach((item, i) => {
                if (item.group !== lastGroup) {
                    lastGroup = item.group;
                    el.appendChild(h('p', { class: 'dax-slash-group' }, item.group));
                }
                el.appendChild(h('button', {
                    type: 'button',
                    class: 'dax-slash-item' + (i === selected ? ' is-active' : ''),
                    onmousedown: (e) => e.preventDefault(),
                    onclick: () => choose(i),
                    onmouseenter: () => { selected = i; highlight(); },
                }, [
                    h('span', { class: 'dax-slash-ic', html: ui(item.icon, 16) }),
                    h('span', { class: 'dax-slash-text' }, [
                        h('span', { class: 'dax-slash-title' }, item.title),
                        h('span', { class: 'dax-slash-desc' }, item.description),
                    ]),
                ]));
            });
        };

        const highlight = () => {
            [...el.querySelectorAll('.dax-slash-item')].forEach((n, i) =>
                n.classList.toggle('is-active', i === selected));
            const active = el.querySelector('.dax-slash-item.is-active');
            active && active.scrollIntoView({ block: 'nearest' });
        };

        const choose = (i) => {
            const item = items[i];
            if (!item) return;
            const rect = props.clientRect ? props.clientRect() : null;
            item.run(cmd, range, context, rect || { top: 0, bottom: 0, left: 0, right: 0, width: 0, height: 0 });
        };

        let props, pop;
        const anchorEl = h('span', {});

        return {
            onStart: (p) => {
                props = p; cmd = p.editor; range = p.range; items = p.items; selected = 0;
                el = h('div', { class: 'dax-slash' });
                anchorEl.getBoundingClientRect = () => props.clientRect() || new DOMRect();
                pop = openPopover(anchorEl, el, { onClose: () => {} });
                paint();
            },
            onUpdate: (p) => {
                props = p; range = p.range; items = p.items; selected = Math.min(selected, Math.max(0, items.length - 1));
                pop && pop.reposition();
                paint();
            },
            onKeyDown: (p) => {
                if (!items.length && p.event.key !== 'Escape') return false;
                if (p.event.key === 'ArrowDown') { selected = (selected + 1) % items.length; highlight(); return true; }
                if (p.event.key === 'ArrowUp') { selected = (selected - 1 + items.length) % items.length; highlight(); return true; }
                if (p.event.key === 'Enter') { choose(selected); return true; }
                if (p.event.key === 'Escape') { pop && pop.close(); return true; }
                return false;
            },
            onExit: () => { pop && pop.close(); },
        };
    };
}

export function SlashMenu(context) {
    const catalogue = commands();
    return Extension.create({
        name: 'slashMenu',
        addProseMirrorPlugins() {
            return [
                Suggestion({
                    editor: this.editor,
                    char: '/',
                    allowSpaces: false,
                    startOfLine: false,
                    command: ({ editor, range, props }) => props.command(editor, range),
                    items: ({ query }) => {
                        const q = query.trim().toLowerCase();
                        return catalogue.filter((c) =>
                            !q || `${c.title} ${c.keywords} ${c.description}`.toLowerCase().includes(q));
                    },
                    render: renderer(context),
                }),
            ];
        },
    });
}
