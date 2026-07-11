/* ---------------------------------------------------------------------------
 * Docent visual editor — assembles Tiptap with the closed Docent schema.
 *
 * `createDocentEditor` returns a plain Editor instance (kept OUTSIDE Alpine's
 * reactive proxy by the caller). The document loads from and saves to the
 * ProseMirror JSON contract in DESIGN.md §"Tiptap schema contract":
 * getJSON() is the `content_tiptap` wire payload sent on save and preview.
 * ------------------------------------------------------------------------- */

import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import ListItem from '@tiptap/extension-list-item';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableHeader from '@tiptap/extension-table-header';
import TableCell from '@tiptap/extension-table-cell';

import { docentNodes, DocsCodeBlock, setAttrsOnListItem } from './nodes.js';
import { SlashMenu } from './slash.js';
import { buildBubbleMenu } from './bubble.js';
import { DocentLink } from './link.js';

const EMPTY_DOC = { type: 'doc', content: [{ type: 'paragraph' }] };

/**
 * `listItem` extended with the contract's `checked` attribute (non-null = task
 * item). A checkbox node view renders and toggles it; plain items (checked ===
 * null) render as ordinary bullets.
 */
const DocentListItem = ListItem.extend({
    addAttributes() {
        return {
            checked: {
                default: null,
                keepOnSplit: false,
                parseHTML: (el) => {
                    const v = el.getAttribute('data-checked');
                    return v === null ? null : v === 'true';
                },
                renderHTML: (attrs) => (attrs.checked == null ? {} : { 'data-checked': attrs.checked ? 'true' : 'false' }),
            },
        };
    },
    addNodeView() {
        return ({ node, editor, getPos }) => {
            const li = document.createElement('li');
            const content = document.createElement('div');
            content.className = 'dax-li-content';
            let checkbox = null;
            const build = (n) => {
                if (n.attrs.checked == null) {
                    li.className = '';
                    li.removeAttribute('data-checked');
                    if (checkbox) { checkbox.remove(); checkbox = null; }
                    return;
                }
                li.className = 'dax-task';
                li.setAttribute('data-checked', n.attrs.checked ? 'true' : 'false');
                if (!checkbox) {
                    checkbox = document.createElement('span');
                    checkbox.className = 'dax-checkbox';
                    checkbox.contentEditable = 'false';
                    checkbox.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        const pos = getPos();
                        const cur = editor.state.doc.nodeAt(pos);
                        if (cur) setAttrsOnListItem(editor, getPos, { checked: !cur.attrs.checked });
                    });
                    li.prepend(checkbox);
                }
                checkbox.classList.toggle('is-checked', !!n.attrs.checked);
            };
            li.appendChild(content);
            build(node);
            return {
                dom: li,
                contentDOM: content,
                update(updated) {
                    if (updated.type !== node.type) return false;
                    build(updated);
                    return true;
                },
                ignoreMutation: (m) => m.type !== 'selection' && !content.contains(m.target),
            };
        };
    },
});

export function createDocentEditor({ element, content, editable = true, meta, onUpdate, onImage, placeholder }) {
    const context = {
        meta: typeof meta === 'function' ? meta : () => meta,
        image: onImage || (() => {}),
    };

    const ref = { editor: null };
    const bubble = buildBubbleMenu(ref);

    const editor = new Editor({
        element,
        editable,
        content: normalizeDoc(content),
        editorProps: {
            attributes: { class: 'docent-prose dax-editor-surface', spellcheck: 'true' },
        },
        extensions: [
            StarterKit.configure({
                codeBlock: false,
                listItem: false,
                heading: { levels: [1, 2, 3, 4] },
                dropcursor: { color: 'var(--docent-accent)', width: 2 },
            }),
            DocentListItem,
            DocsCodeBlock(),
            DocentLink,
            Image.configure({ inline: true, allowBase64: false }),
            Table.configure({ resizable: true }),
            TableRow,
            TableHeader,
            TableCell,
            Placeholder.configure({
                placeholder: ({ node }) =>
                    node.type.name === 'paragraph' ? (placeholder || 'Start writing, or press / for blocks…') : '',
                showOnlyWhenEditable: true,
                includeChildren: false,
            }),
            ...docentNodes(context),
            SlashMenu(context),
            bubble.extension,
        ],
        onUpdate: ({ editor }) => onUpdate && onUpdate(editor.getJSON()),
    });

    ref.editor = editor;
    return editor;
}

/** Guard against an empty/absent doc so ProseMirror always has a valid root. */
function normalizeDoc(content) {
    if (!content || typeof content !== 'object' || content.type !== 'doc') return EMPTY_DOC;
    if (!Array.isArray(content.content) || content.content.length === 0) return EMPTY_DOC;
    return content;
}
