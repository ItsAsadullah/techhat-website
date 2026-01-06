# 🚀 Quick Start Guide - Multi-Vendor Product Upload

## তাড়াতাড়ি শুরু করার জন্য ৫ মিনিটের গাইড

---

## ⚡ দ্রুত ইনস্টলেশন

### ১. Database Update করুন

Terminal/CMD খুলুন এবং রান করুন:

```bash
cd C:\xampp\htdocs\techhat
```

**Option A: phpMyAdmin দিয়ে:**
1. http://localhost/phpmyadmin খুলুন
2. `techhat_db` database সিলেক্ট করুন
3. SQL tab এ যান
4. `add_multivendor_columns.sql` ফাইলের কন্টেন্ট কপি করে paste করুন
5. Go button চাপুন

**Option B: Command Line দিয়ে:**
```bash
mysql -u root -p techhat_db < add_multivendor_columns.sql
```

### ২. Folder Permission দিন

```bash
# Windows PowerShell:
icacls "C:\xampp\htdocs\techhat\uploads\products" /grant Everyone:F

# Or manually:
# Right-click uploads/products → Properties → Security → Edit → Add Everyone → Full Control
```

### ৩. পেজ Access করুন

Browser এ যান:
```
http://localhost/techhat/admin/product_add_multivendor.php
```

---

## 📝 দ্রুত টেস্ট করার উপায়

### Test Product তৈরি করুন

#### **Tab 1: Basic Info**
```
Product Name: Samsung Galaxy S24 Ultra
Category: Electronics > Mobile > Smartphone
  (টাইপ করুন এবং dropdown থেকে সিলেক্ট করুন)
Brand: Samsung
Tags: Flagship, 5G, AI Camera
Short Description: Flagship smartphone with S Pen and AI features
Long Description: (Rich editor দিয়ে লিখুন)
  - 6.8" Dynamic AMOLED Display
  - Snapdragon 8 Gen 3
  - 200MP Camera
```

**Next বাটনে ক্লিক করুন**

---

#### **Tab 2: Variations & Pricing**

**Variable Product সিলেক্ট করুন**

**"Add Variation" বাটনে ক্লিক করুন** (3 বার)

| Attributes | Purchase | Extra Cost | Selling | Old Price | Stock |
|------------|----------|------------|---------|-----------|-------|
| Black, 12GB, 256GB | 95000 | 5000 | 135000 | 145000 | 10 |
| Titanium Gray, 12GB, 512GB | 105000 | 5000 | 155000 | 165000 | 8 |
| Phantom Black, 12GB, 1TB | 115000 | 5000 | 175000 | 185000 | 5 |

**Profit Display:** প্রতিটি row-তে সবুজ রঙে profit দেখাবে ✅

**Next বাটনে ক্লিক করুন**

---

#### **Tab 3: Media & Images**

1. **Thumbnail Upload:**
   - "Upload Thumbnail" ক্লিক করুন
   - যেকোনো মোবাইলের ছবি সিলেক্ট করুন
   - Preview দেখুন

2. **Gallery Images:**
   - "Choose Files" ক্লিক করুন
   - 3-4টি ছবি সিলেক্ট করুন
   - Grid preview দেখুন

3. **Video URL:** (Optional)
   ```
   https://www.youtube.com/watch?v=dQw4w9WgXcQ
   ```

**Next বাটনে ক্লিক করুন**

---

#### **Tab 4: SEO, Shipping & Warranty**

**SEO:**
```
Meta Title: (Auto-filled থাকবে)
Meta Keywords: samsung, galaxy, s24, ultra, flagship
Meta Description: Latest Samsung flagship with AI features and 200MP camera
```

**Shipping:**
```
Weight: 0.25 KG
Dimensions: 16.3 × 7.9 × 0.9 cm
```

**Warranty:**
```
Warranty Type: Brand Warranty
Warranty Period: 1 Year
Return Policy: 7 Days Return
```

---

#### **Save করুন**

**নিচের Fixed Bar থেকে:**

- **Save as Draft** (পরে এডিট করতে চাইলে)
- অথবা
- **Publish Product** ✅ (এখনই live করতে চাইলে)

---

## ✅ ভেরিফিকেশন

### Database Check করুন:

phpMyAdmin এ যান এবং query রান করুন:

```sql
-- Check products table
SELECT * FROM products ORDER BY id DESC LIMIT 1;

-- Check variations
SELECT * FROM product_variations WHERE product_id = (SELECT MAX(id) FROM products);

-- Check variation JSON
SELECT 
    pv.id, 
    pv.sku, 
    pv.variation_json,
    pv.selling_price,
    pv.stock_qty
FROM product_variations pv
WHERE pv.product_id = (SELECT MAX(id) FROM products);
```

**Expected Result:**
```
variation_json: {"Color":"Black","RAM":"12GB","Storage":"256GB"}
selling_price: 135000.00
stock_qty: 10
```

---

## 🔧 Common Quick Fixes

### Problem: Tom Select না দেখা যাচ্ছে
**Fix:**
```html
<!-- Check these CDN links exist in <head> -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
```

### Problem: Summernote Editor না দেখা যাচ্ছে
**Fix:**
```html
<!-- Check jQuery loads BEFORE Summernote -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
```

### Problem: Image upload হচ্ছে না
**Fix:**
```bash
# Create folder if missing
mkdir -p uploads/products

# Give permissions
chmod -R 755 uploads/products
```

### Problem: API errors
**Fix:**
```php
// Check these files exist:
admin/api/save_product.php
admin/api/get_children.php
admin/api/create_category.php
admin/api/get_attributes.php
admin/api/create_attribute.php
admin/api/add_brand.php
```

---

## 📊 Feature Checklist

পেজ সঠিকভাবে কাজ করছে কিনা check করুন:

### ট্যাব ১ (Basic Info):
- [ ] Product Name field visible
- [ ] Tom Select category dropdown working
- [ ] "Create new category" on Enter works
- [ ] Brand dropdown with Tom Select
- [ ] Tags input field
- [ ] Summernote rich editor loads
- [ ] Next button switches to Tab 2

### ট্যাব ২ (Variations):
- [ ] Product Type toggle (Simple/Variable)
- [ ] Simple product form shows
- [ ] Profit calculator works (green/red)
- [ ] Variable product attributes load
- [ ] "Add Variation" button works
- [ ] Variation table appears
- [ ] Per-row profit calculation
- [ ] Delete variation works

### ট্যাব ৩ (Media):
- [ ] Thumbnail upload button
- [ ] Image preview shows
- [ ] Gallery multi-upload works
- [ ] Gallery grid preview
- [ ] Remove buttons work
- [ ] Video URL field

### ট্যাব ৪ (SEO & Shipping):
- [ ] Meta fields present
- [ ] Auto-fill meta title
- [ ] Shipping fields (weight, dimensions)
- [ ] Warranty dropdown options
- [ ] Return policy options

### Fixed Bottom Bar:
- [ ] "Save as Draft" button
- [ ] "Publish Product" button
- [ ] Buttons call API
- [ ] Success message shows
- [ ] Redirects to products.php

---

## 🎯 Quick Test Scenarios

### Scenario 1: Simple Product (পেনড্রাইভ)
```
Name: SanDisk 64GB Pen Drive
Category: Electronics > Storage > Pen Drive
Type: Simple Product
Purchase: 500
Extra Cost: 50
Selling: 850
Stock: 50
```

### Scenario 2: Variable Product (টি-শার্ট)
```
Name: Premium Cotton T-Shirt
Category: Fashion > Men > T-Shirts
Type: Variable Product

Variations:
- Black, M: Purchase 200, Selling 450, Stock 20
- Black, L: Purchase 200, Selling 450, Stock 15
- White, M: Purchase 200, Selling 450, Stock 18
- White, L: Purchase 200, Selling 450, Stock 12
```

### Scenario 3: Electronics with Specs (ল্যাপটপ)
```
Name: Dell Inspiron 15 3000
Category: Electronics > Computer > Laptop
Type: Variable Product

Variations:
- i5, 8GB, 512GB SSD: Purchase 45000, Selling 62000
- i7, 16GB, 1TB SSD: Purchase 58000, Selling 78000
```

---

## 📸 Screenshot Guide

যদি কোনো সমস্যা হয়, এই screenshots তুলুন:

1. **Tab Navigation**: Top bar with 4 tabs
2. **Category Selection**: Tom Select dropdown
3. **Profit Display**: Green/Red profit text
4. **Variation Table**: Dynamic rows
5. **Browser Console**: F12 → Console tab (errors দেখুন)
6. **Network Tab**: F12 → Network → API responses

---

## 🔗 Useful Links

```
Main Page:
http://localhost/techhat/admin/product_add_multivendor.php

Products List:
http://localhost/techhat/admin/products.php

Database:
http://localhost/phpmyadmin

API Test:
http://localhost/techhat/admin/api/get_children.php
http://localhost/techhat/admin/api/get_attributes.php?category_id=1
```

---

## 💡 Pro Tips

1. **Meta Title Auto-fill:**
   - Product Name টাইপ করলে Meta Title automatic fill হয়

2. **Category Path:**
   - Category সিলেক্ট করলে নিচে breadcrumb দেখাবে
   - Electronics > Mobile > Smartphone

3. **Profit Calculator:**
   - দাম দেওয়ার সাথে সাথে profit দেখাবে
   - কোনো JS library লাগবে না (Vanilla JS)

4. **Draft Save:**
   - অসম্পূর্ণ product save করে পরে complete করতে পারবেন

5. **Image Preview:**
   - Upload করার সাথে সাথে preview দেখাবে

---

## 🎓 Next Steps

সফলভাবে test product তৈরি করার পর:

1. **Products List Page** তৈরি করুন:
   - `admin/products.php` update করুন
   - Draft এবং Published products আলাদা দেখান

2. **Product Edit Page** তৈরি করুন:
   - Same form, just populate with existing data
   - Update functionality

3. **Frontend Product Display**:
   - Variation selector dropdown
   - Price update based on variation
   - Stock availability check

4. **Image Optimization**:
   - Auto-resize uploaded images
   - WebP conversion
   - Thumbnail generation

---

**এখনই টেস্ট করুন!** 🚀

আপনার প্রথম product upload করুন এবং দেখুন কত সহজ! 

যদি কোনো সমস্যা হয়, উপরের troubleshooting section দেখুন অথবা browser console check করুন।
