/* ---------------------------------------------------------------------------
 * Selection bubble menu: bold / italic / strike / code / link. A lightweight
 * ProseMirror plugin positions the menu above the current text selection — no
 * tippy/popper dependency. Marks are limited to the closed contract set.
 * ------------------------------------------------------------------------- */

import { Extension } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { h, openPopover } from './ui.js';
import { ui } from './icons.js';

function markButton(ref, { icon, title, isActive, run }) {
    const btn = h('button', {
        type: 'button', class: 'dax-bubble-btn', title, 'aria-label': title,
        html: ui(icon, 15), onmousedown: (e) => e.preventDefault(),
        onclick: () => run(ref.editor),
    });
    btn._sync = () => btn.classList.toggle('is-active', ref.editor && isActive(ref.editor));
    return btn;
}

export function buildBubbleMenu(ref) {
    const element = h('div', { class: 'dax-bubble' });
    element.style.display = 'none';

    const buttons = [
        markButton(ref, { icon: 'bold', title: 'Bold', isActive: (e) => e.isActive('bold'), run: (e) => e.chain().focus().toggleBold().run() }),
        markButton(ref, { icon: 'italic', title: 'Italic', isActive: (e) => e.isActive('italic'), run: (e) => e.chain().focus().toggleItalic().run() }),
        markButton(ref, { icon: 'strike', title: 'Strikethrough', isActive: (e) => e.isActive('strike'), run: (e) => e.chain().focus().toggleStrike().run() }),
        markButton(ref, { icon: 'code', title: 'Inline code', isActive: (e) => e.isActive('code'), run: (e) => e.chain().focus().toggleCode().run() }),
    ];
    const linkBtn = h('button', {
        type: 'button', class: 'dax-bubble-btn', title: 'Link', 'aria-label': 'Link',
        html: ui('link', 15), onmousedown: (e) => e.preventDefault(),
        onclick: () => openLinkPopover(ref.editor, linkBtn),
    });
    linkBtn._sync = () => linkBtn.classList.toggle('is-active', ref.editor && ref.editor.isActive('link'));

    element.append(...buttons, h('span', { class: 'dax-bubble-sep' }), linkBtn);
    document.body.appendChild(element);

    const syncAll = () => [...buttons, linkBtn].forEach((b) => b._sync());

    let linkOpen = false;
    const hide = () => { if (!linkOpen) element.style.display = 'none'; };
    const show = () => { element.style.display = 'flex'; };

    const position = (view) => {
        const { state } = view;
        const { from, to, empty } = state.selection;
        const editor = ref.editor;
        if (!editor || !view.hasFocus() && !linkOpen) return hide();
        if (empty || state.selection.node || editor.isActive('codeBlock')) return hide();
        show();
        syncAll();
        const start = view.coordsAtPos(from);
        const end = view.coordsAtPos(to, -1);
        const left = (Math.min(start.left, end.left) + Math.max(start.right, end.right)) / 2;
        const rect = element.getBoundingClientRect();
        let x = left - rect.width / 2;
        x = Math.max(8, Math.min(x, window.innerWidth - rect.width - 8));
        let y = start.top - rect.height - 8;
        if (y < 8) y = end.bottom + 8;
        element.style.left = `${x}px`;
        element.style.top = `${y}px`;
    };

    const extension = Extension.create({
        name: 'docentBubbleMenu',
        addProseMirrorPlugins() {
            return [
                new Plugin({
                    key: new PluginKey('docentBubble'),
                    view: (view) => {
                        const update = () => position(view);
                        return {
                            update: () => position(view),
                            destroy: () => { element.remove(); },
                        };
                    },
                    props: {
                        handleDOMEvents: {
                            blur: () => { setTimeout(() => { if (!linkOpen) hide(); }, 10); return false; },
                        },
                    },
                }),
            ];
        },
    });

    // Expose so the link popover can hold the menu open while editing.
    element._setLinkOpen = (v) => { linkOpen = v; if (!v) hide(); };

    return { element, extension };

    function openLinkPopover(editor, anchor) {
        if (!editor) return;
        element._setLinkOpen(true);
        const current = editor.getAttributes('link').href || '';
        const input = h('input', {
            type: 'text', class: 'dax-input dax-pop-input dax-mono',
            placeholder: 'https:// or a page slug', value: current,
        });
        const apply = () => {
            const href = input.value.trim();
            if (href === '') editor.chain().focus().unsetLink().run();
            else editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
            pop.close();
        };
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); apply(); } });
        const panel = h('div', { class: 'dax-pop-body dax-link-pop' }, [
            h('div', { class: 'dax-link-row' }, [
                input,
                h('button', { type: 'button', class: 'dax-btn dax-btn-primary dax-btn-sm', onclick: apply }, 'Apply'),
            ]),
            current && h('button', {
                type: 'button', class: 'dax-link-remove',
                onclick: () => { editor.chain().focus().unsetLink().run(); pop.close(); },
            }, 'Remove link'),
        ]);
        const pop = openPopover(anchor, panel, { placement: 'top-start', onClose: () => element._setLinkOpen(false) });
        setTimeout(() => input.focus(), 0);
    }
}
