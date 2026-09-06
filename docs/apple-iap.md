# Apple non-renewing subscriptions

## Server configuration

Set these variables in the deployed environment and cache Laravel's configuration again:

```dotenv
APPLE_IAP_ISSUER_ID=
APPLE_IAP_KEY_ID=
APPLE_IAP_BUNDLE_ID=com.dmv.us
APPLE_IAP_APPLE_ID=
APPLE_IAP_PRIVATE_KEY_PATH=storage/app/private/apple/AuthKey_KEYID.p8
APPLE_IAP_ROOT_CA_PATHS=storage/app/private/apple/AppleRootCA-G3.cer
```

`APPLE_IAP_APPLE_ID` is the numeric App Store app ID. Download the root certificate from Apple's Certificate Authority page. PEM and DER certificate files are supported. The private key and Apple root CA files must not be committed to source control. Multiple trusted root certificate paths may be comma-separated.

The App Store Connect key must be an In-App Purchase key. After changing the environment, run:

```shell
php artisan config:clear
php artisan config:cache
php artisan migrate --force
```

The server scheduler must run every minute in production:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Flutter purchase flow

1. Call `GET /api/subscriptions/apple-account-token` while authenticated.
2. Pass `data.app_account_token` to StoreKit as the purchase's `appAccountToken`.
3. After StoreKit returns a verified purchase, send its transaction ID to `POST /api/subscriptions/verify`:

```json
{
  "transaction_id": "2000000000000001",
  "product_id": "com.dmv.us.monthly"
}
```

`product_id` is optional and is only a consistency check. The server selects the package from Apple's signed `productId`; it never trusts a client-selected package.

4. Use `GET /api/subscriptions/status` as the source of truth before opening paid features.

The old `/api/subscriptions/apple-iap` URL remains as an alias for compatibility, but it now accepts `transaction_id`, not legacy `receipt_data`.

Configure the package rows so that `apple_product_id` matches App Store Connect exactly and `duration_days` is `30` for `com.dmv.us.monthly` and `180` for `com.dmv.us.sixmonths`.
