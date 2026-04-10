# shamanicca-ecommerce-wp-theme-checkout

A lightweight **WordPress theme** that powers the checkout layer of the **Shamanicca headless e-commerce** site. The frontend is built with React and communicates with WordPress / WooCommerce via REST API or GraphQL (WPGraphQL). This theme is responsible for exposing cart and checkout data so the React application can render them seamlessly without loading a classic WordPress frontend.

---

## Overview

| Layer | Technology |
|-------|-----------|
| CMS / backend | WordPress + WooCommerce |
| Headless API | WooCommerce REST API / WPGraphQL |
| Frontend | React (separate repository) |
| Theme role | Minimal WP theme – disables the standard WP frontend and exposes cart/checkout endpoints |

---

## Features

- Headless-ready: disables the default WordPress frontend rendering so only the REST/GraphQL API is consumed.
- Cart endpoint support: exposes cart contents so the React app can display and modify them.
- Checkout integration: handles WooCommerce order creation through the API layer.
- Lightweight: no bundled CSS or JS meant for direct browser rendering – all presentation lives in the React app.

---

## Requirements

- PHP 8.0+
- WordPress 6.0+
- WooCommerce 7.0+
- (Optional) [WPGraphQL](https://www.wpgraphql.com/) + [WooGraphQL](https://github.com/wp-graphql/wp-graphql-woocommerce) for GraphQL support
- Node.js 18+ / npm 9+ *(only needed if you work on build tooling)*

---

## Installation

1. **Clone the repository** into your WordPress themes directory:
   ```bash
   git clone https://github.com/maubayuelo/shamanicca-ecommerce-wp-theme-checkout.git \
     wp-content/themes/shamanicca-ecommerce-wp-theme-checkout
   ```

2. **Activate the theme** in the WordPress admin panel under *Appearance → Themes*, or via WP-CLI:
   ```bash
   wp theme activate shamanicca-ecommerce-wp-theme-checkout
   ```

3. **Install PHP dependencies** (if a `composer.json` is present):
   ```bash
   composer install --no-dev
   ```

4. **Install Node dependencies** (if build tooling is used):
   ```bash
   npm install
   npm run build
   ```

---

## Project Structure

```
shamanicca-ecommerce-wp-theme-checkout/
├── functions.php          # Theme bootstrap: hooks, REST routes, WooCommerce setup
├── style.css              # Required by WordPress (theme header only – no visual styles)
├── index.php              # Minimal template (headless – never rendered by a browser)
├── inc/                   # PHP helpers and modules
│   ├── api/               # Custom REST API endpoints for cart & checkout
│   └── woocommerce/       # WooCommerce overrides and hooks
├── templates/             # Any server-side template partials (optional)
└── assets/                # Static assets if needed (icons, etc.)
```

---

## Configuration

Copy the sample environment file and adjust the values for your environment:

```bash
cp .env.example .env
```

Key variables:

| Variable | Description |
|----------|-------------|
| `REACT_APP_API_URL` | Base URL of your WordPress/WooCommerce installation |
| `WC_CONSUMER_KEY` | WooCommerce REST API consumer key |
| `WC_CONSUMER_SECRET` | WooCommerce REST API consumer secret |

> **Never commit `.env` files with real credentials.** The `.gitignore` in this repository already excludes them.

---

## Development

```bash
# Install Node dependencies
npm install

# Start development watcher (Sass / JS, if configured)
npm run dev

# Production build
npm run build
```

---

## Contributing

1. Fork the repository and create a feature branch: `git checkout -b feat/your-feature`
2. Commit your changes following [Conventional Commits](https://www.conventionalcommits.org/): `git commit -m "feat: add checkout endpoint"`
3. Push to your fork and open a Pull Request against `main`.

---

## License

This project is licensed under the [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) license, in accordance with WordPress licensing requirements.
