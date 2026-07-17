# V34 — Sale operation SQL, real ranking fallback, UI polish

## Scope
Fix issues reported after V33 on `salesloop.vn`:

- Sale operation page returned MySQL `ONLY_FULL_GROUP_BY` error while calculating operation-stage tab counts.
- Sales ranking looked empty although real closed orders existed in legacy/current data.
- Marketing ranking podium was too tall/clipped and visually collided with the table area.
- Ranking tables and sale-operation rows were too tight and had harsh cell borders.
- Several Pushsale filters/table areas felt misaligned.

## Backend fixes

### `app/Services/Operations/SaleOperationService.php`
The tab-count query previously cloned `queryFiltered()`, which contains `withCount('pendingSupplementPackets')`. That leaves `orders.*` and a subselect in the SELECT list. When grouped only by stage, MySQL with `ONLY_FULL_GROUP_BY` rejects the query.

V34 resets the selected columns before building the grouped count query:

```php
$countsQuery = (clone $baseQuery)->withoutEagerLoads()->reorder();
$countsQuery->getQuery()->columns = null;
```

Then it selects only:

- `COALESCE(operation_stage, 'no_operation') as stage_key`
- `COUNT(*) as aggregate`

and groups by the same COALESCE expression.

### `app/Services/Reports/RevenueRankingService.php`
Sales/marketing revenue ranking now treats both modern and legacy closed orders as real closed orders:

- modern: `closed_at` not null and `closed_at` within range
- legacy: `closing_status = closed`, `closed_at` null, and `data_arrived_at` within range
- fallback legacy: `closing_status = closed`, `closed_at` and `data_arrived_at` null, and `updated_at` within range

This avoids a ranking table showing all zeros when old/imported data is already marked closed but has no `closed_at`.

## UI fixes

### `resources/css/pushsale.css`
### `resources/css/pushsale-marketing.css`
### `public/build/assets/pushsale-VZglJWi2.css`
### `public/build/assets/app-*.css`

Added V34 CSS overrides:

- remove remaining header/filter shadows
- make sale workspace filters more balanced
- increase sale operation table min-width and row spacing
- soften table borders and alternate rows
- clean money/product nested cell borders
- reduce marketing ranking podium height and step calculation to prevent clipping
- widen marketing ranking table to 1940px
- increase ranking table row height, header height, sale column width, and avatar spacing
- align UTM checkbox to the left instead of floating in a weird isolated position

## Deploy notes

After pulling V34:

```bash
cd /var/www/erm-pushsale
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan horizon:terminate
```

Then verify:

```bash
php artisan horizon:status
curl -s "https://salesloop.vn/__erm-test/pages?secret=<secret>" | jq '.failed_results'
```

Open and click through:

- `/sales/workspace`
- `/sales/rankings`
- `/admin/rankings`
- `/marketing/rankings`
- `/admin/marketing/dashboard`

