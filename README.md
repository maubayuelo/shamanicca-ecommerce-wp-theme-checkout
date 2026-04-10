# Shamanicca — WooCommerce Checkout Theme (storefront-child)

Custom WordPress child theme built on [Storefront](https://woocommerce.com/storefront/) that replaces the default WooCommerce checkout, cart, and order-received pages with a clean, conversion-optimised layout.

The React / Next.js storefront lives in a separate repository. This theme only handles the WooCommerce-native pages that the headless frontend hands off to WordPress (checkout, cart, order-received).

---

## What this theme customises

### Pages affected
- `/checkout` — full checkout page
- `/cart` — cart page
- `/checkout/order-received/` — order confirmation (thank-you) page

### Layout & UX changes
- **Two-column desktop layout**: billing form left, order summary card right (sticky)
- **Single-column mobile layout**: order summary on top, billing form below
- **Minimal header**: logo + "Back to cart" + "Support" links only — full Storefront nav removed
- **Express pay placement**: Apple Pay / Google Pay buttons captured via PHP output buffer and re-emitted inside the order card (above card fields), not below the billing form
- **Order Notes removed**: reduces checkout friction and abandonment
- **Handheld footer bar removed**: Storefront's mobile icon bar hidden on checkout/cart/order-received
- **Skip link hidden**: Storefront's accessibility skip link hidden on minimal-header pages

### Visual design
- Warm off-white page background (`#F7F6F4`)
- White cards with subtle border + shadow for billing form and order summary
- Poppins typeface throughout
- Custom Select2 dropdown styling to match form design
- `box-sizing: border-box` enforced on all form elements to prevent horizontal overflow
- `select.select2-hidden-accessible` restored to 1 px clip — WooCommerce replaces the native `<select>` with Select2 and hides the original; our global `width: 100% !important` rule was overriding Select2's hidden styles and causing horizontal overflow on mobile. Fixed by scoping all `select` rules to `:not(.select2-hidden-accessible)`.
- Responsive at 768 px (grid collapses to single column) and 600 px (header stacks vertically, border-radius tightens)

### Order-received page
- 4-column grid for ORDER NUMBER / DATE / TOTAL / PAYMENT METHOD labels (collapses to 2-col on mobile)
- Billing address shown in normal weight, no italic
- Product names rendered as plain text (hyperlinks removed via `woocommerce_order_item_permalink → __return_false`)
- "Thank you" heading margin reduced

### Key PHP hooks (functions.php)

| Section | Hook | Purpose |
|---------|------|---------|
| 4 | `body_class` | Add `shamanicca-checkout`, `shamanicca-cart`, `shamanicca-thankyou` body classes for CSS scoping |
| 4 | `storefront_header` | Remove default nav, search, cart sitewide |
| 4 | `wp` + `storefront_footer` | Remove handheld footer bar on target pages |
| 6b | `storefront_before_footer` | Remove footer widgets on target pages |
| 10 | `storefront_header` priority 30 | Inject minimal nav (Back to cart + Support) inside first col-full alongside logo |
| 15b | `wp` + output buffer at `woocommerce_checkout_before_customer_details` + `woocommerce_review_order_before_payment` | Relocate Apple/Google Pay buttons into the order card. Uses `wp` hook (not `init`) because `is_checkout()` is not available at `init` time. |
| 7b | `woocommerce_order_item_permalink` | Remove product hyperlinks on order-received page |

---

## Installation

> The repository contains only the child theme files (`functions.php` and `style.css`).
> You must place them inside a folder named **`storefront-child`** — WordPress identifies themes by their folder name.

### Requirements
- WordPress 6.x
- WooCommerce 8.x+
- [Storefront theme](https://woocommerce.com/storefront/) installed as parent (do **not** activate it, do **not** modify it)
- WooPayments or Stripe for WooCommerce (required for Apple Pay / Google Pay buttons)

### Steps

1. **Clone the repository** into your themes folder, specifying the required folder name:
   ```bash
   cd wp-content/themes/
   git clone https://github.com/maubayuelo/shamanicca-ecommerce-wp-theme-checkout.git storefront-child
   ```
   The `storefront-child` argument tells Git to clone into a folder with that exact name. This is required — WordPress will not recognise the theme without it, and the parent-child relationship with Storefront depends on it.

2. **Install the Storefront parent theme** via:
   WordPress Admin → Appearance → Themes → Add New → search "Storefront" → Install.
   Do not activate it.

3. **Activate the child theme**:
   WordPress Admin → Appearance → Themes → activate **Shamanicca Checkout (storefront-child)**.

4. **Verify**: visit `/checkout` — you should see the two-column layout with the Shamanicca logo, minimal nav, and order summary card on the right.

### Updating
```bash
cd wp-content/themes/storefront-child/
git pull origin main
```
Clear any caching plugin cache after pulling.

---

## File structure

```
storefront-child/
├── style.css        # All custom CSS (checkout, cart, order-received, responsive)
├── functions.php    # All PHP hooks and WooCommerce customisations
├── .gitignore
└── README.md
```

No build step. No node_modules. No compiled assets. Edit and deploy directly.

---

## Environment awareness

The "Back to cart" link in the minimal nav is environment-aware:

```php
$cart_url = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
    ? 'http://localhost:3000/cart'      // local Next.js dev server
    : 'https://shamanicca.com/cart/';  // production
```

Set `WP_DEBUG=true` in `wp-config.php` locally. Ensure it is `false` on production.

---

## Known technical notes

- **Express pay (Apple Pay / Google Pay)**: WooPayments renders its express-checkout buttons via a hook that fires before the billing form. Moving the DOM element that contains a Stripe iframe destroys the Stripe mount. The solution is a PHP output buffer: capture the HTML at `woocommerce_checkout_before_customer_details` priority 0–2, suppress the original, and re-emit it at `woocommerce_review_order_before_payment` priority 5 inside the order card.
- **Storefront col-full responsive bug**: At viewports below ~1063 px, Storefront switches `.col-full` from `margin: auto` to a fixed `margin-left`. Overridden with `margin: auto !important` on both header and content col-full.
- **Select2 overflow**: WooCommerce's country/province dropdowns are replaced by jQuery Select2. The original `<select>` remains in the DOM as `select.select2-hidden-accessible` (1 px, `position: absolute`). Our `select { width: 100% !important }` was overriding this and causing horizontal scroll on mobile. Fixed by scoping to `:not(.select2-hidden-accessible)` and adding an explicit reset rule.

---

## Related repositories

| Repo | Description |
|------|-------------|
| This repo | WooCommerce checkout child theme |
| `shamanicca/web-app-graphql` | Next.js + GraphQL headless storefront |

---

## Support

`contact@shamanicca.com`
