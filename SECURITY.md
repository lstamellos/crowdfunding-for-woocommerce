# Security policy

This fork exists in part because upstream Crowdfunding for WooCommerce 3.1.14 is no longer maintained and contains known security issues.

Security-sensitive areas in this fork include:

- shortcode rendering and HTML attribute escaping;
- administrator state-changing actions and CSRF protection;
- open-price validation and WooCommerce cart price integrity;
- order discovery/calculation logic;
- cached backer data containing customer information.

The removed “Product by User” subsystem is not supported by this fork and should not be reintroduced without a separate threat model and review.

Do not treat a compatibility declaration as evidence by itself. HPOS and Cart/Checkout Blocks stay explicitly incompatible until integration tests demonstrate correct behavior.
