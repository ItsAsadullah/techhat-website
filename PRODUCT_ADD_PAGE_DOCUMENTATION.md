# Product Upload Page - Tom Select Dynamic Categories & Attributes

## Overview

A complete **Multi-vendor Product Upload System** with:
- ✅ **Hierarchical Categories** - Unlimited nesting (Main → Sub → Child)
- ✅ **Tom Select Integration** - Advanced select with create on-the-fly
- ✅ **Dynamic Attributes** - Category-based attribute system
- ✅ **AJAX Backend** - Real-time category/attribute creation
- ✅ **Tailwind CSS** - Modern responsive design

---

## Architecture

### Database Schema

```
categories (Hierarchical)
├── id (INT)
├── name (VARCHAR) - Unique
├── slug (VARCHAR)
├── parent_id (INT) - Self-referencing FK
├── level (INT) - 0 for root, 1+ for children
├── is_active (TINYINT)
└── timestamps

attributes (Available attributes)
├── id (INT)
├── name (VARCHAR) - "Color", "Size", "Storage", etc.
├── slug (VARCHAR)
├── type (ENUM) - select, multiselect, text, number, color
└── is_active (TINYINT)

attribute_values (Possible values per attribute)
├── id (INT)
├── attribute_id (FK)
├── value (VARCHAR) - "Red", "Blue", "64GB", etc.
├── color_code (VARCHAR) - For color attributes
└── is_active (TINYINT)

category_attributes (Mapping - Which attributes for which categories)
├── id (INT)
├── category_id (FK)
├── attribute_id (FK)
├── is_required (TINYINT)
└── display_order (INT)
```

---

## Backend API Endpoints

### 1. GET /admin/api/get_children.php

**Purpose**: Fetch categories (root or children of parent)

**Parameters**:
- `parent_id` (optional, GET): Parent category ID. Omit for root categories.

**Response**:
```json
{
  "status": "success",
  "data": [
    { "id": 1, "name": "Electronics", "slug": "electronics", "level": 0, "parent_id": null },
    { "id": 2, "name": "Fashion", "slug": "fashion", "level": 0, "parent_id": null }
  ],
  "count": 2
}
```

---

### 2. POST /admin/api/create_category.php

**Purpose**: Create a new category dynamically

**Parameters** (POST):
- `name` (required): Category name
- `parent_id` (optional): Parent category ID

**Response**:
```json
{
  "status": "success",
  "id": 123,
  "name": "Smart Watch",
  "slug": "smart-watch",
  "parent_id": 1,
  "level": 1
}
```

**Error Response**:
```json
{
  "status": "error",
  "message": "Category already exists"
}
```

---

### 3. GET /admin/api/get_attributes.php

**Purpose**: Fetch attributes for a specific category

**Parameters**:
- `category_id` (required, GET): Category ID

**Response**:
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Color",
      "slug": "color",
      "type": "select",
      "is_required": 1,
      "display_order": 1,
      "values": [
        { "id": 101, "value": "Black", "color_code": "#000000" },
        { "id": 102, "value": "Red", "color_code": "#FF0000" }
      ]
    },
    {
      "id": 3,
      "name": "Storage",
      "slug": "storage",
      "type": "select",
      "is_required": 0,
      "values": [
        { "id": 201, "value": "64GB", "color_code": null },
        { "id": 202, "value": "128GB", "color_code": null }
      ]
    }
  ]
}
```

---

### 4. POST /admin/api/create_attribute.php

**Purpose**: Create new attribute value dynamically

**Parameters** (POST):
- `attribute_id` (required): Attribute ID
- `value` (required): New value
- `color_code` (optional): Hex color for color attributes

**Response**:
```json
{
  "status": "success",
  "id": 103,
  "value": "Teal Blue",
  "color_code": "#008080",
  "existing": false
}
```

---

## Frontend Implementation

### File: `/admin/product_add.php`

#### Key Components:

1. **Category Level 1 Selector** (Root Categories)
   - Initialized with Tom Select
   - `create: true` - Allows creating new categories
   - AJAX on create → saves new category
   - AJAX on change → fetches attributes

2. **Dynamic Category Levels** (Level 2, 3, etc.)
   - Created dynamically based on parent's children
   - Each level has its own Tom Select
   - Infinite nesting support

3. **Attributes Section**
   - Loaded after category selection
   - Supports select, multiselect, and text inputs
   - Tom Select on dropdowns for better UX

4. **Pricing & Stock**
   - Base price (required)
   - Offer price (optional)
   - Stock quantity (required)

5. **Image Upload**
   - Multiple file support
   - Drag-and-drop UI
   - Live preview

---

## JavaScript Logic Flow

```
┌─ Initialize Root Categories
│  └─ Fetch from get_children.php
│  └─ Create Tom Select
│
├─ On Category Created
│  └─ POST to create_category.php
│  └─ Add to dropdown & select
│
├─ On Category Selected
│  ├─ Fetch attributes (get_attributes.php)
│  │  └─ Populate attribute inputs
│  │
│  └─ Check for children
│     ├─ If children exist → Create next level selector
│     └─ If no children → Hide deeper levels
│
├─ Tom Select Event: create
│  └─ Trigger category/attribute creation
│
└─ Form Submit
   └─ POST all data to product creation endpoint
```

---

## Sample Data

### Root Categories (Level 0)
- Electronics
- Fashion
- Home & Garden

### Sub-Categories (Level 1)
- Mobile Phones (under Electronics)
- Laptops (under Electronics)
- Men Clothing (under Fashion)
- Women Clothing (under Fashion)

### Attributes
- **Color**: Black, White, Red, Blue, Green, Gold, Silver
- **Size**: XS, S, M, L, XL, XXL
- **Storage**: 64GB, 128GB, 256GB, 512GB, 1TB
- **RAM**: 2GB, 4GB, 6GB, 8GB, 12GB, 16GB

### Category-Attribute Links
- **Mobile Phones**: Color (required), Storage, RAM
- **Laptops**: Color, Storage, RAM (required)

---

## Usage Example

### Step 1: User Opens Product Add Page
```
GET /admin/product_add.php
↓
JavaScript initializes root categories via get_children.php
↓
Display: Category Level 1 dropdown with Tom Select
```

### Step 2: User Selects "Electronics"
```
Tom Select onChange → onCategorySelected()
↓
Fetch attributes for Electronics
↓
Check for children: Mobile Phones, Laptops, Accessories
↓
Create Category Level 2 dropdown dynamically
```

### Step 3: User Selects "Mobile Phones"
```
Tom Select onChange → onCategorySelected()
↓
Fetch attributes: Color (required), Storage, RAM
↓
Display attribute inputs
↓
Check for children: None
↓
Remove any deeper level selectors
```

### Step 4: User Types "iPhone 15" (New Category)
```
Tom Select onChange with new text
↓
POST to create_category.php {name: "iPhone 15", parent_id: 4}
↓
Backend creates new category
↓
Response: {id: 100, name: "iPhone 15", ...}
↓
JavaScript adds option and selects it
↓
Attributes loaded for "iPhone 15"
```

### Step 5: User Submits Form
```
Form data collected:
{
  title: "iPhone 15 Pro Max",
  sku: "SKU-2024-001",
  category_id: 100,
  attributes: {
    1: ["101"], // Color: Black
    3: ["201"], // Storage: 64GB
    4: ["103"]  // RAM: 4GB
  },
  price: 999.99,
  stock_quantity: 50,
  images: [File, File, ...]
}
↓
POST to product creation endpoint
```

---

## Tom Select Configuration

```javascript
new TomSelect(selectElement, {
  create: true,                    // Allow creating new options
  placeholder: 'Select or create', // Placeholder text
  createOnBlur: true,              // Create when losing focus
  maxItems: null,                  // Unlimited for multiselect
  onChange: callback               // Trigger on selection/creation
});
```

---

## Key Features

✅ **Infinite Category Nesting**
- No depth limit
- Each level auto-generates based on parent's children
- Removes deeper levels when selecting leaf category

✅ **Create Categories On-The-Fly**
- Type new category → Press Enter
- AJAX saves to database
- Instantly selected and attributes loaded

✅ **Dynamic Attribute Loading**
- Only shows attributes relevant to selected category
- Multiple input types (select, multiselect, text)
- Required attributes marked with red asterisk

✅ **Responsive Design**
- Mobile-friendly Tailwind CSS
- Tom Select adapts to all screen sizes
- Grid layout for attribute inputs

✅ **AJAX Validation**
- Prevent duplicate categories
- Check for existing attribute values
- Real-time feedback via JSON responses

---

## Customization Guide

### Add New Root Category
```sql
INSERT INTO categories (name, slug, parent_id, level)
VALUES ('New Category', 'new-category', NULL, 0);
```

### Add Sub-Category
```sql
INSERT INTO categories (name, slug, parent_id, level)
VALUES ('Sub Category', 'sub-category', 1, 1);
```

### Add New Attribute
```sql
INSERT INTO attributes (name, slug, type)
VALUES ('Weight', 'weight', 'text');
```

### Add Attribute Value
```sql
INSERT INTO attribute_values (attribute_id, value)
VALUES (5, '500g');
```

### Link Attribute to Category
```sql
INSERT INTO category_attributes (category_id, attribute_id, is_required)
VALUES (4, 1, 1); -- Mobile Phones requires Color
```

---

## Security Considerations

✅ **Authentication**: Checks `$_SESSION['user_id']` in all APIs  
✅ **Input Validation**: Validates name length, trims whitespace  
✅ **SQL Injection**: Uses prepared statements with PDO  
✅ **Duplicate Prevention**: Unique constraints on names  
✅ **CORS**: Endpoints only accept authenticated requests  

---

## Performance Tips

1. **Caching**: Add Redis for frequently accessed categories
2. **Lazy Loading**: Load attributes only on demand
3. **Indexes**: Already added on parent_id, attribute_id, category_id
4. **Pagination**: For large category lists, implement pagination

---

## Future Enhancements

- [ ] Bulk category/attribute import (CSV)
- [ ] Category-level visibility (public/private)
- [ ] Attribute value ordering/sorting
- [ ] Category image/icon upload
- [ ] Translation support for multi-language sites
- [ ] Category SEO settings (meta description, keywords)

---

**Version**: 1.0  
**Last Updated**: January 6, 2026  
**Status**: Production Ready ✅
