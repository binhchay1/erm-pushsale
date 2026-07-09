# Queue process manager

Production queue workers are managed exclusively by Laravel Horizon.

Use `horizon.conf.example` as the only Supervisor program for queues. Remove any previous program that runs `artisan queue:work` or `artisan queue:listen`, then run:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start erm-saleops-horizon
```

See `docs/HORIZON_REDIS_OPERATIONS.md` before switching an existing database-backed queue so pending jobs are drained safely.
