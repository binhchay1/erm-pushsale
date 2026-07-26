# V61 - Source deploy UI cleanup

Decision after V60 cleanup:

- Production deploy builds assets with `pnpm build`; `public/build` must not be committed.
- Runtime/generated storage files must not be committed; only `.gitignore` placeholders stay.
- The app can no longer depend on checked-in `public/vendor/font-awesome`/AdminLTE assets. `resources/css/pushsale.css` owns the required AdminLTE-like button contract and FontAwesome-compatible `.fa` fallback selectors.
- Marketing dashboard table uses `width: 100%` and no desktop horizontal overflow. Only small screens can scroll.
- Composer lock now contains the missing `symfony/polyfill-php83` entry required by Horizon.

Do not reintroduce public/build, vendor, node_modules, or storage runtime files into git.
