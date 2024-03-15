# From 2.x to 3.x

Support for Laravel 6, 7, 8, and 9 has been dropped. The minimum supported version is now Laravel 10.

## Environment changes (Impact: high)

Publish the new configuration file:

```bash
php artisan vendor:publish --tag=cloud-scheduler-config
```

Change the environment variables names:

- `STACKKIT_CLOUD_SCHEDULER_APP_URL` -> `CLOUD_SCHEDULER_APP_URL`

Add the following environment variable:

- `CLOUD_SCHEDULER_SERVICE_ACCOUNT` - The e-mail address of the service account invocating the task.
