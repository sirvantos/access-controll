---
name: surface-default-centering
description: Preserve Surface default centering for generic empty and error states. Use when changing `resources/js/components/shared/Surface.vue` or Vue components that render shared Surface content.
---

# Surface Default Centering

- Keep `Surface` default `alignClass` as `text-center`.
- Use explicit `align-class="text-left"` for forms, tables, filters, cards, and dense content that must align left.
- Do not change the shared default to fix one page; prefer local `align-class` on that page or component.
