/* ---------------------------------------------------------------------------
 * Minimal `link` mark. Deliberately hand-rolled (rather than
 * @tiptap/extension-link, which bundles linkifyjs) because the closed contract
 * only needs an href attribute and set/unset/toggle commands. Keeping the
 * attribute set to href alone guarantees clean JSON round-trips.
 * ------------------------------------------------------------------------- */

import { Mark, mergeAttributes } from '@tiptap/core';

export const DocentLink = Mark.create({
    name: 'link',
    priority: 1000,
    keepOnSplit: false,
    inclusive: false,

    addAttributes() {
        return { href: { default: null } };
    },

    parseHTML() {
        return [{ tag: 'a[href]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['a', mergeAttributes(HTMLAttributes, { rel: 'noopener nofollow', class: 'dax-link' }), 0];
    },

    addCommands() {
        return {
            setLink: (attrs) => ({ chain }) => chain().setMark(this.name, attrs).run(),
            toggleLink: (attrs) => ({ chain }) => chain().toggleMark(this.name, attrs).run(),
            unsetLink: () => ({ chain }) => chain().unsetMark(this.name, { extendEmptyMarkRange: true }).run(),
        };
    },
});
