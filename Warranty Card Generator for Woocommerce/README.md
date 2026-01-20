# Warranty Card Generator for WooCommerce

Custom WooCommerce plugin that generates warranty cards for completed orders and allows printing or exporting them as PDF.

## Features
- Generates warranty card posts for completed WooCommerce orders.
- Printable warranty card view in the browser.
- PDF export using dompdf.
- Admin settings for company branding (logo, badge, signature).
- QR code embedded on the warranty card.

## Tech Stack
- PHP (WordPress plugin)
- WooCommerce
- dompdf (PDF rendering)
- HTML/CSS (inline template styles)

## Requirements
- WordPress 5.8+
- WooCommerce 6.x+
- PHP 7.4+

## Installation
1. Copy the plugin folder into `wp-content/plugins/`.
2. Activate the plugin from the WordPress admin.
3. Check **Warranty Cards** in the WP dashboard.
4. After successful purchase, all generated warranty cards will be stored there.
5. For troubleshooting, ensure `vendor/dompdf/` is present in the plugin directory.

## Usage
1. Complete a WooCommerce order to generate a warranty card.
2. Visit **Warranty Cards** in the WordPress admin.
3. Open a card and use:
   - **Print** to print the card in the browser.
   - **Export PDF** to download a PDF copy.

## Configuration
Go to **Warranty Cards → Settings** to configure company details and branding assets.

## PDF Layout Notes
The PDF uses the same template as the browser view, with additional print rules.
Edit the inline styles in `drts-warranty-card-generator.php` inside `get_card_markup()` to adjust PDF sizing or layout.

## Creator
- Name: DRTSWebWorks
- Contact: Update this section with your preferred details.

## License
Proprietary. All rights reserved.
