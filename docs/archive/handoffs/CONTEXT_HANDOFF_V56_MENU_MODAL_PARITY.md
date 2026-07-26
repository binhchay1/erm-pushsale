# V56 - Menu / modal parity

Pushsale source keeps the left menu as `sidebar-menu ul1 > li1/a1 > ul2 > li2/a2 > ul3` inside AdminLTE 2 skin-blue-light. The app must keep React/Inertia routing, but the visual contract is now centralized at the end of `resources/css/pushsale.css` under `V56 - Pushsale exact menu + modal motion contract`.

Rules:
- Do not add page-level sidebar/menu CSS again.
- If the menu needs a change, edit only the V56 menu contract block.
- Menu open/close is a transform transition, default hidden, open overlays content.
- Root submenu expansion uses max-height/opacity/translate transition.
- Third-level menu uses the Pushsale blue flyout style and a small slide-in animation.
- All modal/dialog opening should use backdrop fade + dialog slide-down animation. Do not add `align-items:flex-start`, sidebar offsets, or page-scoped dialog placement rules.

Source references used:
- `Pasted text(11).txt`: AdminLTE/skin-blue-light/css imports.
- `Pasted text(11).txt`: menu DOM (`ul1`, `li1`, `a1`, `ul2`, `ul3`).
- `Pasted text(11).txt`: `showPopupCommon()` modal behavior.
