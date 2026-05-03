# AfconWave Payment Gateway for Magento 2

> The official Magento 2 module for the AfconWave Secure Gateway. Accept Mobile Money and Card payments across Africa seamlessly.

---

## Features

- ✅ **Hosted Checkout**: Redirect customers to a secure AfconWave payment page.
- ✅ **Seamless Integration**: Supports Magento's standard checkout flow.
- ✅ **Automated Webhooks**: Real-time order status updates via secure HMAC verification.
- ✅ **Admin Management**: Manage API keys and sandbox settings from Magento Admin.
- ✅ **Full Refund Support**: Issue refunds directly from the Magento Credit Memo interface.

---

## Installation

### 1. Via Composer (Recommended)
```bash
composer require afconwave/module-payment
php bin/magento module:enable AfconWave_Payment
php bin/magento setup:upgrade
php bin/magento cache:clean
```

### 2. Manual Installation
1. Create a directory `app/code/AfconWave/Payment`.
2. Upload the contents of this folder into that directory.
3. Run the following commands:
```bash
php bin/magento module:enable AfconWave_Payment
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy -f
```

---

## Docker Testing (Instant Setup)

We have provided a `docker-compose.yml` to help you test the plugin locally in seconds.

### 1. Launch Magento
```bash
docker-compose up -d
```

### 2. Install the Plugin into the Container
```bash
# Copy the plugin files into the running container
docker cp . magento-server:/bitnami/magento/app/code/AfconWave/Payment

# Enable and Upgrade
docker exec -it magento-server php bin/magento module:enable AfconWave_Payment
docker exec -it magento-server php bin/magento setup:upgrade
```

### 3. Access your store
- **Frontend**: `http://localhost:8080`
- **Admin**: `http://localhost:8080/admin` (User: `user`, Password: `bitnami_password`)

---

## Configuration

1. Log in to your Magento Admin.
2. Navigate to **Stores > Configuration > Sales > Payment Methods**.
3. Locate **AfconWave Secure Gateway**.
4. Enter your **Secret Key** and **Webhook Secret**.
5. Set **Sandbox Mode** to "Yes" for testing.

---

## License
MIT © AfconWave
