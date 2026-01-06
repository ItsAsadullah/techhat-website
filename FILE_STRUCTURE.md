# TechHat E-commerce Platform - File Structure

```
techhat/
│
├── 📄 ROOT LEVEL FILES
│   ├── index.php                                    # Homepage
│   ├── product.php                                  # Product detail page
│   ├── category.php                                 # Category listing
│   ├── search.php                                   # Search page
│   ├── cart.php                                     # Shopping cart
│   ├── checkout.php                                 # Checkout page
│   ├── checkout_submit.php                          # Order submission
│   ├── order_success.php                            # Order confirmation
│   ├── order_view.php                               # Order details
│   ├── dashboard.php                                # User dashboard
│   ├── categories.php                               # Category page
│   ├── login.php                                    # Login page
│   ├── logout.php                                   # Logout handler
│   ├── register.php                                 # Registration page
│   ├── install.php                                  # Installation script
│   └── setup.php                                    # Database setup (NEW)
│
├── 📁 ADMIN FOLDER
│   ├── index.php                                    # Admin dashboard
│   ├── 📊 PRODUCT MANAGEMENT
│   │   ├── products.php                             # Product listing
│   │   ├── product_add.php                          # ✨ NEW: Dynamic product upload
│   │   ├── product_add_enhanced.php                 # Enhanced product form
│   │   ├── product_add_new.php                      # New product form
│   │   └── product_edit.php                         # Edit product
│   │
│   ├── 📋 ADMIN PAGES
│   │   ├── accounts.php                             # User accounts
│   │   ├── banners.php                              # Banner management
│   │   ├── brands.php                               # Brand management
│   │   ├── categories.php                           # Category management
│   │   ├── orders.php                               # Order listing
│   │   ├── order_detail.php                         # Order details
│   │   ├── purchases.php                            # Purchase history
│   │   ├── customer_ledger.php                      # Customer ledger
│   │   ├── generate_invoice_pdf.php                 # Invoice generation
│   │
│   ├── ⚙️ SETTINGS
│   │   ├── settings.php                             # General settings
│   │   ├── site_settings.php                        # Site configuration
│   │   ├── payment_settings.php                     # Payment settings
│   │   ├── delivery_settings.php                    # Delivery settings
│   │   ├── contact_settings.php                     # Contact settings
│   │   ├── return_settings.php                      # Return policy
│   │   └── social_settings.php                      # Social media settings
│   │
│   ├── 🛒 POS SYSTEM
│   │   ├── pos.php                                  # Point of Sale
│   │   ├── pos_submit.php                           # POS submission
│   │   ├── pos_sales.php                            # Sales tracking
│   │   ├── pos_cancel.php                           # Cancel order
│   │   ├── pos_invoice.php                          # Invoice print
│   │   ├── pos_print_summary.php                    # Print summary
│   │   ├── pos_return.php                           # Return handling
│   │   ├── pos_return_invoice.php                   # Return invoice
│   │   └── pos_return_submit.php                    # Return submission
│   │
│   ├── 📁 API FOLDER
│   │   └── api/
│   │       ├── category_ajax.php                    # Old category API
│   │       ├── 🆕 get_children.php                  # ✨ NEW: Fetch categories/subcategories
│   │       ├── 🆕 create_category.php               # ✨ NEW: Create category dynamically
│   │       ├── 🆕 get_attributes.php                # ✨ NEW: Get category attributes
│   │       └── 🆕 create_attribute.php              # ✨ NEW: Create attribute values
│   │
│   └── 📁 PARTIALS FOLDER
│       └── partials/
│           └── sidebar.php                          # Admin sidebar menu
│
├── 📁 API FOLDER (Root Level)
│   └── api/
│       ├── add_brand.php                            # Add brand API
│       ├── add_category.php                         # Add category API
│       ├── cart_ajax.php                            # Cart operations
│       ├── get_subcategories.php                    # Get subcategories
│       ├── order_detail.php                         # Order detail API
│       └── wishlist_ajax.php                        # Wishlist operations
│
├── 📁 CORE FOLDER
│   └── core/
│       ├── auth.php                                 # Authentication logic
│       ├── auth_handler.php                         # Auth handler
│       ├── config.php                               # Configuration
│       ├── db.php                                   # Database connection
│       ├── order.php                                # Order logic
│       └── stock.php                                # Stock management (FIXED)
│
├── 📁 INCLUDES FOLDER
│   └── includes/
│       ├── header.php                               # Header template
│       ├── footer.php                               # Footer template
│       ├── auth-modal.php                           # Auth modal
│       ├── cart-widget.php                          # Cart widget (FIXED)
│       └── search_filters.php                       # Search filters
│
├── 📁 ASSETS FOLDER
│   └── assets/
│       ├── 🎨 css/
│       │   ├── style.css                            # Main stylesheet
│       │   ├── animations.css                       # Animations
│       │   └── transitions.css                      # Transitions
│       │
│       ├── 🖼️ images/
│       │   └── [product images, icons, etc.]
│       │
│       └── 🔧 js/
│           ├── bd-locations.js                      # Location/area JS
│           └── spa-navigation.js                    # SPA navigation
│
├── 📁 UPLOADS FOLDER
│   └── uploads/
│       └── products/                                # Product images
│
├── 🔧 DATABASE & CONFIG FILES
│   ├── database.sql                                 # Database backup
│   ├── techhat_db_backup.sql                        # DB backup
│   ├── schema_hierarchical_categories.sql           # ✨ NEW: Hierarchical categories schema
│   ├── migrate_variant_system.sql                   # Variant system migration
│   ├── setup_category_attributes.sql                # Category-attribute mapping
│   ├── setup_attribute_values.sql                   # Attribute values setup
│   ├── add_new_tables.sql                           # New tables migration
│   ├── add_variant_image.sql                        # Variant image column
│   ├── add_specifications_column.sql                # Specifications column
│   ├── add_delivery_and_warranty_cols.php           # Delivery/warranty columns
│   ├── add_badge_and_variant_cols.php               # Badge/variant columns
│   ├── add_address_cols_to_users.php                # User address columns
│   ├── add_more_variant_cols.php                    # More variant columns
│   ├── create_banners_table.sql                     # Banners table
│   ├── create_expenses_table.sql                    # Expenses table
│   ├── create_homepage_settings.sql                 # Homepage settings
│   ├── create_purchase_tables.sql                   # Purchase tables
│   ├── create_return_tables.sql                     # Return tables
│   ├── create_reviews_table.sql                     # Reviews table
│   ├── create_services_table.sql                    # Services table
│   ├── create_wishlist_table.sql                    # Wishlist table
│   └── database_migration_pos_custom_return.sql     # POS custom return migration
│
├── 📚 DOCUMENTATION FILES
│   ├── IMPLEMENTATION_CHECKLIST.md                  # ✨ Implementation checklist
│   ├── PRODUCT_ADD_PAGE_DOCUMENTATION.md            # ✨ Product page docs
│   ├── QUICK_START_PRODUCT_PAGE.md                  # ✨ Quick start guide
│   ├── PRODUCT_VARIANT_SYSTEM_README.md             # Variant system guide
│   ├── VARIANT_SYSTEM_GUIDE.md                      # Variant guide
│   ├── SYSTEM_ARCHITECTURE.md                       # System architecture
│   ├── SYSTEM_STATUS.txt                            # System status
│   ├── FRONTEND_FIXES_SUMMARY.md                    # Frontend fixes
│   ├── QUICK_START_GUIDE.md                         # Quick start
│   └── README.md                                    # Project README (if exists)
│
├── 🧪 TEST FILES
│   ├── test_color_logic.php                         # Color logic tests
│   ├── test_category_variant.php                    # Category variant tests
│   ├── check_variant_data.php                       # Variant data check
│   ├── check_offer_price.php                        # Offer price check
│   ├── fix_offer_prices.php                         # Fix offer prices
│   ├── fix_suppliers.php                            # Fix suppliers
│   └── run_migration_variant_img.php                # Run variant image migration
│
└── .git/                                            # Git repository
```

---

## 📊 Summary Statistics

### **Total Files**: ~120+

### **By Category**:
- 📄 **Frontend Pages**: 15 files
- 🛠️ **Admin Pages**: 25+ files
- 🔌 **API Endpoints**: 10 files
- ⚙️ **Core System**: 6 files
- 🎨 **Frontend Assets**: 8 files
- 📁 **Folders**: 6 main folders
- 🗄️ **Database Files**: 20+ SQL files
- 📚 **Documentation**: 9 markdown/text files
- 🧪 **Test Files**: 7 test files

### **Key Sections**:

| Section | Status | Description |
|---------|--------|-------------|
| **Frontend** | ✅ Complete | Homepage, product pages, cart, checkout |
| **Admin** | ✅ Complete | Product management, orders, settings |
| **API** | ✅ Complete | Category, product, cart, wishlist APIs |
| **Dynamic Upload** | ✨ NEW | Tom Select, hierarchical categories |
| **Database** | ✅ Complete | 15+ tables with proper relationships |
| **Documentation** | ✅ Complete | Full technical docs & guides |
| **Variant System** | ✅ Complete | Dynamic product attributes |
| **Frontend Fixes** | ✅ Complete | All pages updated for new schema |

---

## 🆕 Recently Added Files

```
✨ NEW - Dynamic Product Upload System:
├── admin/product_add.php                  (979 lines) - Main upload page
├── admin/api/get_children.php             (42 lines)  - Fetch categories
├── admin/api/create_category.php          (73 lines)  - Create categories
├── admin/api/get_attributes.php           (53 lines)  - Get attributes
├── admin/api/create_attribute.php         (77 lines)  - Create attr values
├── schema_hierarchical_categories.sql     (129 lines) - DB schema
├── setup.php                              (42 lines)  - Setup page
└── Documentation Files                    (1500+ lines)

✅ FIXED - Frontend Compatibility:
├── core/stock.php                         - Stock management
├── includes/cart-widget.php              - Cart calculations
├── checkout.php                          - Order processing
├── api/cart_ajax.php                     - Cart AJAX
├── category.php                          - Category listing
├── product.php                           - Product display
├── search.php                            - Search results
├── index.php                             - Homepage
└── dashboard.php                         - User dashboard
```

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│                    FRONTEND                         │
│  (Homepage, Product, Category, Cart, Checkout)     │
└──────────────────┬──────────────────────────────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
┌───────▼────────┐    ┌──────▼──────────┐
│   API LAYER    │    │  ADMIN PANEL    │
│ (JSON Responses)    │  (CRUD Operations)
└───────┬────────┘    └──────┬──────────┘
        │                     │
        └──────────┬──────────┘
                   │
        ┌──────────▼──────────┐
        │   CORE SYSTEM       │
        │  (Auth, DB, Stock)  │
        └──────────┬──────────┘
                   │
        ┌──────────▼──────────┐
        │    DATABASE         │
        │  (MySQL / InnoDB)   │
        └─────────────────────┘
```

---

## 💾 Database Tables

```
Core Tables:
├── users                    (Customers & Admins)
├── products                 (Product listings)
├── categories               (✨ NEW: Hierarchical)
├── attributes               (✨ NEW: Product attributes)
├── attribute_values         (✨ NEW: Attribute options)
├── category_attributes      (✨ NEW: Category-Attribute mapping)
├── product_images           (Product photos)
├── product_variations       (✨ NEW: Dynamic variants)
├── product_variants_legacy  (Old variant data)
├── cart_items               (Shopping cart)
├── orders                   (Order records)
├── order_items              (Order line items)
├── reviews                  (Product reviews)
├── wishlist                 (User wishlists)
├── brands                   (Brand info)
├── banners                  (Homepage banners)
├── flash_sales              (Flash sale events)
├── stock_movements          (Stock history)
└── [20+ more tables]        (Settings, transactions, etc.)
```

---

**Last Updated**: January 6, 2026  
**Status**: ✅ Production Ready with Dynamic Product Upload System
