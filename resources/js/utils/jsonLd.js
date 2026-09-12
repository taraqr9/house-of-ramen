import { h } from 'vue';

// Inertia's <Head> component does its OWN vnode-to-HTML-string walk for
// head tags (see @inertiajs/vue3's src/head.ts) instead of going through
// Vue's normal SSR renderer, and that walker has two hard constraints
// neither Vue's template compiler nor Inertia's own docs make obvious:
//
// 1. `<script>` is an HTML "raw text" element, so Vue's template compiler
//    never evaluates {{ }} mustaches written inside one - the whole tag
//    body is captured as static text, verbatim, in both SSR and CSR. A
//    literal `<script>{{ jsonExpr }}</script>` in a template ships the
//    string "{{ jsonExpr }}", not JSON.
// 2. Inertia's walker treats every vnode prop as a literal HTML attribute
//    with no special case for `innerHTML`/`textContent`, so v-html on a
//    <script> serializes as the broken `<script innerHTML="...">` rather
//    than real script content. And it explicitly rejects component vnodes
//    ("Using components in the <Head> component is not supported").
//
// Its one correct path is a vnode whose `type` is a plain zero-arg
// function returning a *native* element vnode (type is the tag name
// string) with a plain string as children - that's exactly what Vue's
// `<component :is="fn">` produces when `fn` is a function, and what this
// helper builds. Usage: `<component :is="jsonLdVNode(value, headKey)" />`
// inside a <Head> block.
export function jsonLdVNode(value, headKey) {
    // Defensive: JSON.stringify never produces this sequence for our own
    // catalogue data, but escaping it stops a literal "</script>" inside a
    // string value from prematurely closing the tag.
    const json = JSON.stringify(value).replace(/</g, '\\u003c');

    return () => h('script', { type: 'application/ld+json', 'head-key': headKey }, json);
}
