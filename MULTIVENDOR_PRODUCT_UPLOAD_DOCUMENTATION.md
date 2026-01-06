# মাল্টি-ভেন্ডর প্রোডাক্ট আপলোড সিস্টেম
## Multi-Vendor Product Upload System - Complete Documentation

---

## 📋 সূচিপত্র (Table of Contents)

1. [ওভারভিউ](#overview)
2. [ফিচার লিস্ট](#features)
3. [ইনস্টলেশন](#installation)
4. [পেজ স্ট্রাকচার](#page-structure)
5. [ব্যবহার গাইড](#usage-guide)
6. [API এন্ডপয়েন্ট](#api-endpoints)
7. [ডাটাবেস স্ট্রাকচার](#database)

---

## 🎯 ওভারভিউ (Overview)

এটি একটি সম্পূর্ণ মাল্টি-ভেন্ডর ই-কমার্স প্রোডাক্ট আপলোড সিস্টেম যা ৪টি ট্যাবে বিভক্ত:

1. **Basic Information** - পণ্যের মৌলিক তথ্য
2. **Variations & Pricing** - দাম এবং ভেরিয়েশন
3. **Images & Media** - ছবি এবং ভিডিও
4. **SEO, Shipping & Warranty** - এসইও, শিপিং এবং ওয়ারেন্টি

---

## ✨ ফিচার লিস্ট (Features)

### ট্যাব ১: সাধারণ তথ্য (Basic Information)

#### 📝 পণ্যের নাম (Product Name)
- Required field
- Auto-populates Meta Title

#### 📁 ডাইনামিক ক্যাটাগরি (Dynamic Category)
- **হায়ারার্কিক্যাল সিস্টেম**: ইনফিনিট নেস্টিং সাপোর্ট
- **Tom Select Integration**: 
  - Search or Create functionality
  - টাইপ করে নতুন ক্যাটাগরি তৈরি
  - Auto-load sub-categories
- **Category Path**: Real-time breadcrumb display
- **API**: `get_children.php`, `create_category.php`

#### 🏷️ ব্র্যান্ড (Brand)
- Search or Create dropdown
- Tom Select enabled
- API: `add_brand.php`

#### 🔖 ট্যাগস (Tags)
- Comma-separated input
- For SEO and search optimization

#### 📄 Short Description
- 2-3 lines
- Displays on product cards

#### 📝 Long Description
- **Summernote Rich Text Editor**
- Bold, Italic, Lists, Tables
- Image insertion
- Video embedding

---

### ট্যাব ২: ভেরিয়েশন ও প্রাইসিং (Variations & Pricing)

#### Product Type Selection

##### 🔹 Simple Product
পণ্যে কোনো কালার/সাইজ ভেরিয়েশন নেই (যেমন: পেনড্রাইভ, মাউস প্যাড)

**ফিল্ডস:**
- **Purchase Price** (কেনা দাম)
- **Extra Cost** (শিপিং/কাস্টমস)
- **Selling Price** (বিক্রয় মূল্য)
- **Old Price** (ছাড় দেখানোর জন্য)
- **Stock Quantity** (স্টক)

**Real-time Profit Calculator:**
```
Profit = Selling Price - (Purchase Price + Extra Cost)
Profit % = (Profit / Total Cost) × 100
```
- ✅ **লাভ**: সবুজ টেক্সট
- ❌ **লস**: লাল টেক্সট
- ⚖️ **সমান**: ধূসর টেক্সট

##### 🔹 Variable Product
পণ্যে কালার, র‍্যাম, সাইজ ইত্যাদি আছে (যেমন: মোবাইল, ল্যাপটপ)

**ফিচারস:**
- **Attribute Selection**: Category-based dynamic attributes load
- **Add Variation Button**: নতুন ভেরিয়েশন row যোগ করুন
- **Variation Table Columns**:
  1. Attributes (Color, RAM, Storage)
  2. Purchase Price
  3. Extra Cost
  4. Selling Price
  5. Old Price
  6. Stock Quantity
  7. Image (ভেরিয়েশন-specific)
  8. **Profit** (Real-time calculated)
  9. Delete Action

**Profit Calculation:**
প্রতিটি row-তে আলাদাভাবে লাভ/লস হিসাব দেখাবে।

---

### ট্যাব ৩: মিডিয়া এবং গ্যালারি (Images & Media)

#### 🖼️ Thumbnail Image (Required)
- মূল প্রোডাক্ট ছবি
- Homepage এবং কার্ডে দেখাবে
- Preview with remove button

#### 🖼️ Gallery Images
- **Multiple Upload**: একাধিক ছবি আপলোড
- **Drag & Drop Zone**: (ভবিষ্যতে implement)
- Click to upload functionality
- Grid preview with remove buttons
- Stores as JSON array

#### 🎥 Video URL
- YouTube or Vimeo link
- Displays on product page
- Optional field

---

### ট্যাব ৪: এসইও, শিপিং ও ওয়ারেন্টি (SEO, Shipping & Warranty)

#### 🔍 SEO Optimization

**Meta Title**
- Auto-fills from Product Name
- Editable

**Meta Keywords**
- Comma-separated
- For search engines

**Meta Description**
- Short description for SERP
- 150-160 characters recommended

#### 📦 Shipping Info

**Weight (KG)**
- Used for courier charge calculation

**Dimensions (L × W × H cm)**
- Length, Width, Height
- For large parcel shipping

#### 🛡️ Warranty & Policy

**Warranty Type**
- No Warranty
- Brand Warranty
- Shop Warranty

**Warranty Period**
- 7 Days Replacement
- 6 Months
- 1 Year
- 2 Years
- 3 Years

**Return Policy**
- No Return
- 3 Days Return
- 7 Days Return
- 15 Days Return

---

## 🚀 ইনস্টলেশন (Installation)

### Step 1: Database Migration

প্রথমে database columns আপডেট করুন:

```sql
-- Run this SQL file
add_multivendor_columns.sql
```

অথবা:

```bash
mysql -u root -p techhat_db < add_multivendor_columns.sql
```

### Step 2: File Upload

নিশ্চিত করুন এই ফাইলগুলো আছে:

```
admin/
  ├── product_add_multivendor.php   (Main page)
  └── api/
      ├── save_product.php          (Save endpoint)
      ├── get_children.php          (Category children)
      ├── create_category.php       (Create category)
      ├── get_attributes.php        (Category attributes)
      ├── create_attribute.php      (Create attribute value)
      └── add_brand.php             (Create brand)

uploads/
  └── products/                     (Image storage)
```

### Step 3: Permissions

Upload folder এ write permission দিন:

```bash
chmod -R 755 uploads/products/
```

---

## 📂 পেজ স্ট্রাকচার (Page Structure)

### Frontend Components

```html
<!-- Tab Navigation -->
<div class="tab-buttons">
  - Basic Info
  - Variations & Pricing
  - Media & Images
  - SEO & Shipping
</div>

<!-- Tab Contents -->
<div id="tab1" class="tab-content active">...</div>
<div id="tab2" class="tab-content">...</div>
<div id="tab3" class="tab-content">...</div>
<div id="tab4" class="tab-content">...</div>

<!-- Fixed Bottom Actions -->
<div class="fixed-bottom">
  - Save as Draft
  - Publish Product
</div>
```

### JavaScript Functions

**Tab Management:**
- `switchTab(tabNumber)` - Switch between tabs
- `toggleProductType()` - Toggle simple/variable

**Category Management:**
- `initializeRootCategory()` - Load root categories
- `onCategorySelected()` - Handle category selection
- `addNextCategoryLevel()` - Add child category level
- `removeDeepLevels()` - Clean up unused levels
- `updateCategoryPath()` - Update breadcrumb
- `createNewCategory()` - Create new category via API
- `loadAttributesForCategory()` - Load category attributes

**Profit Calculators:**
- `calculateSimpleProfit()` - For simple products
- `calculateVariationProfit(id)` - For each variation row

**Variation Management:**
- `addVariationRow()` - Add new variation
- `removeVariationRow(id)` - Delete variation

**Image Handling:**
- `previewThumbnail()` - Thumbnail preview
- `previewGallery()` - Gallery preview
- `removeThumbnail()` - Remove thumbnail
- `removeGalleryImage(index)` - Remove gallery image

**Form Submission:**
- `saveAsDraft()` - Save with draft status
- `publishProduct()` - Save with published status
- `submitProductForm()` - API call to save_product.php

---

## 🔌 API এন্ডপয়েন্ট (API Endpoints)

### 1. Get Category Children
**Endpoint:** `GET api/get_children.php`

**Parameters:**
- `parent_id` (optional) - Parent category ID

**Response:**
```json
[
  { "id": 1, "name": "Electronics", "slug": "electronics" },
  { "id": 2, "name": "Fashion", "slug": "fashion" }
]
```

---

### 2. Create Category
**Endpoint:** `POST api/create_category.php`

**Request Body:**
```json
{
  "name": "Gaming Laptops",
  "parent_id": 5
}
```

**Response:**
```json
{
  "status": "success",
  "id": 12,
  "name": "Gaming Laptops",
  "slug": "gaming-laptops",
  "parent_id": 5,
  "level": 3
}
```

---

### 3. Get Attributes for Category
**Endpoint:** `GET api/get_attributes.php`

**Parameters:**
- `category_id` - Category ID

**Response:**
```json
[
  {
    "id": 1,
    "name": "Color",
    "is_required": 1,
    "values": [
      { "id": 1, "value": "Black" },
      { "id": 2, "value": "White" }
    ]
  }
]
```

---

### 4. Create Attribute Value
**Endpoint:** `POST api/create_attribute.php`

**Request Body:**
```json
{
  "attribute_id": 1,
  "value": "Rose Gold"
}
```

**Response:**
```json
{
  "status": "success",
  "id": 15,
  "value": "Rose Gold"
}
```

---

### 5. Save Product
**Endpoint:** `POST api/save_product.php`

**Request:** Multipart form data

**Form Fields:**

**Basic Info:**
- `product_name`
- `brand_id`
- `tags`
- `short_description`
- `long_description`

**Category:**
- `category_level_1`, `category_level_2`, etc.

**Product Type:**
- `product_type` (simple/variable)

**Simple Product:**
- `simple_purchase_price`
- `simple_extra_cost`
- `simple_selling_price`
- `simple_old_price`
- `simple_stock`

**Variable Product:**
- `variations[0][attributes]`
- `variations[0][purchase_price]`
- `variations[0][selling_price]`
- etc.

**Images:**
- `thumbnail` (file)
- `gallery[]` (multiple files)
- `video_url`

**SEO:**
- `meta_title`
- `meta_keywords`
- `meta_description`

**Shipping:**
- `weight`
- `length`, `width`, `height`

**Warranty:**
- `warranty_type`
- `warranty_period`
- `return_policy`

**Status:**
- `status` (draft/published)

**Response:**
```json
{
  "status": "success",
  "message": "Product saved successfully",
  "product_id": 45,
  "redirect": "products.php"
}
```

---

## 🗄️ ডাটাবেস স্ট্রাকচার (Database Structure)

### Products Table (Updated)

```sql
CREATE TABLE `products` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `category_id` INT(11) NOT NULL,
  `brand_id` INT(11) DEFAULT NULL,
  `description` LONGTEXT,
  `short_description` TEXT,
  `tags` VARCHAR(500),
  `meta_title` VARCHAR(255),
  `meta_keywords` VARCHAR(500),
  `meta_description` TEXT,
  `weight` DECIMAL(10,2) DEFAULT 0.00,
  `length` DECIMAL(10,2) DEFAULT 0.00,
  `width` DECIMAL(10,2) DEFAULT 0.00,
  `height` DECIMAL(10,2) DEFAULT 0.00,
  `warranty_type` ENUM('none', 'brand', 'shop') DEFAULT 'none',
  `warranty_period` VARCHAR(50),
  `return_policy` VARCHAR(50) DEFAULT 'no_return',
  `video_url` VARCHAR(500),
  `image` VARCHAR(255),
  `gallery_images` TEXT COMMENT 'JSON array',
  `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
  `vendor_id` INT(11),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_status` (`status`),
  KEY `idx_vendor` (`vendor_id`)
);
```

### Product Variations Table

```sql
CREATE TABLE `product_variations` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT(11) NOT NULL,
  `sku` VARCHAR(100) UNIQUE,
  `variation_json` LONGTEXT NOT NULL 
    COMMENT '{"Color":"Black","RAM":"8GB"}',
  `purchase_price` DECIMAL(12,2) DEFAULT 0.00,
  `extra_cost` DECIMAL(12,2) DEFAULT 0.00,
  `selling_price` DECIMAL(12,2) DEFAULT 0.00,
  `old_price` DECIMAL(12,2) DEFAULT NULL,
  `stock_qty` INT(11) DEFAULT 0,
  `image` VARCHAR(255),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
);
```

### Product Attribute Values Table (New)

```sql
CREATE TABLE `product_attribute_values` (
  `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT(11) NOT NULL,
  `attribute_id` INT(11) NOT NULL,
  `value_id` INT(11) NOT NULL,
  UNIQUE KEY (`product_id`, `attribute_id`, `value_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`attribute_id`) REFERENCES `attributes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`value_id`) REFERENCES `attribute_values`(`id`) ON DELETE CASCADE
);
```

---

## 📖 ব্যবহার গাইড (Usage Guide)

### ধাপ ১: Basic Information পূরণ করুন

1. **Product Name** লিখুন (যেমন: "iPhone 15 Pro Max")
2. **Category** সিলেক্ট করুন:
   - Electronics → Mobile → Smartphone
   - নতুন ক্যাটাগরি তৈরি করতে টাইপ করে Enter চাপুন
3. **Brand** সিলেক্ট করুন বা তৈরি করুন
4. **Tags** দিন (Gaming Phone, 5G, Premium)
5. **Short Description** - 2-3 লাইন
6. **Long Description** - Rich editor দিয়ে বিস্তারিত লিখুন
7. "পরবর্তী ধাপ" বাটনে ক্লিক করুন

---

### ধাপ ২: Variations & Pricing সেট করুন

#### Simple Product এর জন্য:
1. "সিম্পল প্রোডাক্ট" সিলেক্ট করুন
2. Purchase Price: ৬০০.০০
3. Extra Cost: ৫০.০০
4. Selling Price: ৮৯৯.০০
5. Old Price: ৯৯৯.০০ (optional)
6. Stock: ১৫
7. **Profit Display** দেখুন: সবুজ টেক্সটে লাভ দেখাবে

#### Variable Product এর জন্য:
1. "ভেরিয়েবল প্রোডাক্ট" সিলেক্ট করুন
2. Category attributes automatically load হবে
3. "Add Variation" বাটনে ক্লিক করুন
4. প্রতিটি variation এর জন্য:
   - Attributes: Black, 8GB, 128GB
   - দাম এবং স্টক দিন
   - Image আপলোড করুন
   - Real-time profit দেখুন
5. একাধিক variation যোগ করুন

---

### ধাপ ৩: Media আপলোড করুন

1. **Thumbnail**: "Upload Thumbnail" ক্লিক করে মূল ছবি আপলোড করুন
2. **Gallery**: "Choose Files" দিয়ে একাধিক ছবি সিলেক্ট করুন
3. **Video URL**: YouTube link দিন (optional)
4. Preview দেখুন এবং "পরবর্তী ধাপ" এ যান

---

### ধাপ ৪: SEO & Shipping সেট করুন

#### SEO:
- Meta Title (auto-filled থাকবে)
- Meta Keywords: smartphone, 5g, gaming
- Meta Description: Short summary

#### Shipping:
- Weight: 0.25 KG
- Dimensions: 16 × 8 × 1 cm

#### Warranty:
- Type: Brand Warranty
- Period: 1 Year
- Return Policy: 7 Days Return

---

### ধাপ ৫: Save করুন

**Save as Draft:**
- পরে এডিট করার জন্য সেভ করুন
- Status: Draft

**Publish Product:**
- সরাসরি live করুন
- Status: Published

---

## ⚡ টেকনিক্যাল হাইলাইটস (Technical Highlights)

### 🎨 Frontend Technologies
- **Tailwind CSS** - Modern UI
- **Tom Select** - Advanced dropdowns
- **Summernote** - Rich text editor
- **Vanilla JavaScript** - No framework dependency
- **Bootstrap Icons** - Icon library

### 🔧 Backend Technologies
- **PHP 7.4+** - Server-side logic
- **PDO** - Database interaction
- **Transactions** - Data integrity
- **File Upload** - Image handling
- **JSON** - Flexible variation storage

### 📊 Database Features
- **Foreign Keys** - Referential integrity
- **Indexes** - Performance optimization
- **JSON Fields** - Flexible attribute storage
- **Cascading Deletes** - Clean data removal

### 🚀 Performance Features
- **Lazy Loading** - Categories load on demand
- **AJAX Requests** - No page reloads
- **Real-time Calculations** - Instant profit display
- **Image Optimization** - (Future: Add image compression)

---

## 🐛 ট্রাবলশুটিং (Troubleshooting)

### সমস্যা ১: Tom Select কাজ করছে না
**সমাধান:**
- নিশ্চিত করুন CDN link সঠিক আছে
- Browser console check করুন
- jQuery load হয়েছে কিনা দেখুন

### সমস্যা ২: Image আপলোড হচ্ছে না
**সমাধান:**
- `uploads/products/` folder এর permission check করুন
- PHP `upload_max_filesize` বাড়ান
- `post_max_size` check করুন

### সমস্যা ৩: Profit calculator কাজ করছে না
**সমাধান:**
- Input fields এ `oninput="calculateSimpleProfit()"` আছে কিনা check করুন
- Browser console এ JavaScript error দেখুন

### সমস্যা ৪: Category children load হচ্ছে না
**সমাধান:**
- `api/get_children.php` file আছে কিনা verify করুন
- Database এ categories table populated আছে কিনা check করুন
- Network tab এ API response দেখুন

---

## 🔮 ভবিষ্যত উন্নতি (Future Enhancements)

1. **Drag & Drop Gallery** - Image reordering
2. **Bulk Upload** - CSV import
3. **Product Duplication** - Clone products
4. **Version History** - Track changes
5. **AI Description** - Auto-generate descriptions
6. **Image Compression** - Auto-optimize images
7. **Inventory Alerts** - Low stock notifications
8. **Multi-language** - Bangla/English toggle

---

## 📞 সাপোর্ট (Support)

সমস্যা হলে এই তথ্য দিয়ে contact করুন:
- Browser console errors
- Network tab API responses
- PHP error logs
- Database structure

---

**Created by:** TechHat Development Team  
**Version:** 1.0.0  
**Last Updated:** January 2026  
**License:** Proprietary

---

## 🎉 সংক্ষিপ্ত সারাংশ

এই সিস্টেম দিয়ে ভেন্ডররা:
- ✅ ৪টি ধাপে সহজে প্রোডাক্ট আপলোড করতে পারবে
- ✅ Dynamic category এবং attribute ব্যবহার করতে পারবে
- ✅ Real-time profit দেখতে পারবে
- ✅ Multiple variations manage করতে পারবে
- ✅ Draft save করে পরে publish করতে পারবে

**সবকিছু রেডি!** 🚀
