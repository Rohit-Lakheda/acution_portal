# PDF Library Installation Guide

To enable perfect PDF generation, you need to install the required vendor libraries using Composer.

## Installation Steps

1. **Install Composer** (if not already installed):
   - Download from: https://getcomposer.org/download/
   - Or use: `php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"`
   - Run: `php composer-setup.php`

2. **Install PDF Libraries**:
   ```bash
   composer install
   ```
   
   Or if composer.json already exists:
   ```bash
   composer update
   ```

3. **Libraries Installed**:
   - **DomPDF** (v2.0+) - Primary PDF generator (better HTML to PDF conversion)
   - **TCPDF** (v6.6+) - Fallback PDF generator (programmatic PDF creation)

## How It Works

The system will automatically:
1. **First try DomPDF** - Best for HTML-based invoices with perfect formatting
2. **Fallback to TCPDF** - If DomPDF is not available
3. **Client-side PDF** - If no vendor libraries are available (uses html2pdf.js)

## Usage

Simply click "Download as PDF" button on the invoice page. The system will automatically use the best available PDF generation method.

## Notes

- DomPDF provides the best quality PDFs with perfect HTML rendering
- TCPDF is used as a fallback for programmatic PDF generation
- Both libraries are installed via Composer in the `vendor/` directory
- The `vendor/autoload.php` file is automatically loaded when available

