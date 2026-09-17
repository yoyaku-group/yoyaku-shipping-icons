# YOYAKU Shipping Icons - CLAUDE.md

> **Plugin:** yoyaku-shipping-icons | **Sites:** YOYAKU.IO, yydistribution.fr | **Version:** 1.9.0
> **Status:** Production | **Last Updated:** 2026-01-30

## What is YOYAKU Shipping Icons?

WordPress/WooCommerce plugin that injects carrier logos (Chronopost, Colissimo, Spring GDS, UPS, FedEx) in front of shipping method labels at checkout and cart. Also sorts shipping rates by price (cheapest first) with pickup options at the end.

## Architecture

```
yoyaku-shipping-icons/
├── yoyaku-shipping-icons.php     # Main plugin file (all logic here)
├── assets/                       # Carrier logo PNG files
│   ├── chronopost-logo.png
│   ├── colissimo-logo.png
│   ├── fedex-logo.png
│   ├── spring-gds.png
│   ├── ups-logo.png
│   └── ups-wwe.png               # UPS Worldwide Economy variant
├── delivengo-exclusion-patch.php # Delivengo-specific fixes
└── composer.json
```

## Key Features

1. **Logo Injection** - Matches shipping method labels against carrier patterns and prepends a carrier logo (`ysl_icon_html()`, `.ysl-shipping-logo`, 28px via assets/shipping-icons.css)
2. **Rate Sorting** - Sorts shipping rates by price (cheapest first), keeps "Pick up" / "Retrait" at the end
3. **Multi-Carrier Support** - FedEx, UPS, Chronopost, Colissimo, Spring GDS with priority pattern matching

## Carrier Patterns

```php
// Pattern priority order (specific patterns first)
"fedex priority express" => "fedex-logo.png",
"fedex" => "fedex-logo.png",
"chronopost" => "chronopost-logo.png",
"colissimo" => "colissimo-logo.png",
"spring gds" => "spring-gds.png",
"ups worldwide economy" => "ups-wwe.png",
"ups" => "ups-logo.png",
```

## Quick Debug

```bash
# Check plugin is active
ssh yoyaku-hetzner "cd /var/www/yoyaku.io/public_html && wp --allow-root plugin list | grep shipping-icons"

# View debug logs (logo matching)
ssh yoyaku-hetzner "grep ShippingLabel /var/www/yoyaku.io/public_html/wp-content/debug.log | tail -20"

# Deploy after changes
~/yoyaku-team-config/tools/01-core/deploy-plugin.sh yoyaku-shipping-icons
```

## WooCommerce Hooks

| Hook | Function | Priority |
|------|----------|----------|
| `woocommerce_cart_shipping_method_full_label` | Logo injection | 10 |
| `woocommerce_checkout_shipping_method_full_label` | Logo injection | 10 |
| `woocommerce_package_rates` | Rate sorting | 100 |
| `woocommerce_cart_shipping_packages` | Package rate sorting | 100 |

## Adding New Carriers

1. Add logo PNG to `assets/` (height: 50px recommended)
2. Add pattern(s) to `$patterns` array in `ysl_debug_and_icon()`
3. Order matters: specific patterns before generic ones
4. Deploy to both sites

---
**Maintainer:** Benjamin Belaga
