# TechHat Remote Mobile Scanner - Documentation

## 📱 Overview

The Remote Mobile Scanner feature allows you to use your mobile phone as a barcode scanner for the TechHat inventory system. Scanned barcodes/serial numbers are automatically sent to your PC in real-time using a database polling mechanism.

## 🏗️ Architecture

```
┌─────────────────┐         ┌─────────────────┐         ┌─────────────────┐
│   PC Browser    │         │   MySQL Server  │         │ Mobile Browser  │
│ (add-product.php)│◄───────│ (scan_sessions) │◄────────│(mobile-scanner) │
│                 │  Poll   │                 │   Push  │                 │
│  ↓ AJAX/1sec    │         │                 │         │  ↑ Camera API   │
└─────────────────┘         └─────────────────┘         └─────────────────┘
```

### How It Works:
1. **PC (Receiver)**: Opens inventory page, generates unique session ID, displays QR code
2. **Server (Database)**: `scan_sessions` table stores scanned codes temporarily
3. **Mobile (Sender)**: Scans QR to open scanner page, camera captures barcodes, sends to server

## 📁 File Structure

```
techhat/
├── admin/
│   ├── add-product.php          # Main product form with scanner integration
│   ├── mobile-scanner.php       # Mobile scanner page (open on phone)
│   └── api/
│       ├── scan_endpoints.php   # API for scanner communication
│       └── save_product_scanner.php  # Product save with serial support
├── assets/
│   └── css/
│       └── add-product.css      # Modern UI styles
├── database_scanner_module.sql  # Full SQL schema
└── setup_scanner_module.php     # One-click setup script
```

## 🗄️ Database Tables

### 1. `scan_sessions`
Stores scanned codes from mobile devices.

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| session_id | VARCHAR(64) | Unique session identifier |
| scanned_code | VARCHAR(255) | The barcode/serial scanned |
| code_type | ENUM | barcode, qrcode, serial, imei |
| is_consumed | TINYINT | 0=pending, 1=consumed by PC |
| device_info | VARCHAR(255) | Mobile user agent |
| ip_address | VARCHAR(45) | Scanner IP |
| created_at | DATETIME | Scan timestamp |
| consumed_at | DATETIME | When PC received it |

### 2. `scan_session_registry`
Tracks active scanner sessions.

| Column | Type | Description |
|--------|------|-------------|
| session_id | VARCHAR(64) | Unique session ID |
| user_id | INT | Admin who created session |
| purpose | ENUM | serial_entry, inventory, pos, lookup |
| is_active | TINYINT | Session status |
| expires_at | DATETIME | Session expiry time |

### 3. `product_serials`
Individual serial/IMEI number tracking.

| Column | Type | Description |
|--------|------|-------------|
| serial_number | VARCHAR(100) | Unique serial/IMEI |
| status | ENUM | available, sold, reserved, returned, damaged |
| warranty_start | DATE | Warranty start date |
| warranty_end | DATE | Warranty end date |
| sale_id | BIGINT | Order/POS sale reference |

### 4. `attributes` & `attribute_values`
For variable product variations (Color, Size, RAM, etc.)

## 🚀 Installation

### Step 1: Run Setup Script
```
http://localhost/techhat/setup_scanner_module.php
```

Or run the SQL directly:
```sql
source database_scanner_module.sql;
```

### Step 2: Access Add Product Page
```
http://localhost/techhat/admin/add-product.php
```

## 📝 API Endpoints

### `POST /admin/api/scan_endpoints.php`

#### Action: `register`
Register a new scanner session.
```json
{
  "action": "register",
  "session_id": "scan_1234567890_abc123"
}
```

#### Action: `push`
Mobile sends scanned code.
```json
{
  "action": "push",
  "session_id": "scan_1234567890_abc123",
  "code": "1234567890123",
  "device_info": "Mozilla/5.0..."
}
```

### `GET /admin/api/scan_endpoints.php`

#### Action: `check`
PC polls for new codes.
```
?action=check&session=scan_1234567890_abc123
```
Response:
```json
{
  "status": "found",
  "code": "1234567890123",
  "type": "barcode"
}
```

#### Action: `ping`
Check if session is alive.
```
?action=ping&session=scan_1234567890_abc123
```

## 🎯 Usage Guide

### Adding Product with Serial Numbers

1. **Open Add Product Page**
   - Navigate to Admin → Add Product

2. **Fill Basic Info**
   - Enter product name, category, brand

3. **Enable Serial Tracking**
   - Go to "Inventory" tab
   - Enter stock quantity (e.g., 5)
   - Check "Has Serial/IMEI Number"

4. **Connect Mobile Scanner**
   - Click "🔗 Connect Mobile Scanner"
   - QR code appears on screen

5. **Scan with Mobile**
   - Open phone camera, scan QR code
   - Mobile scanner page opens
   - Point at barcodes to scan

6. **Auto-Fill Serial Numbers**
   - Each scan auto-fills the next empty serial field
   - Beep sound confirms successful scan
   - Toast notification shows scanned code

7. **Save Product**
   - Click "Save Product"
   - All serials are stored in database

## 🔧 Configuration

### Scanner Settings (in add-product.php)
```javascript
const CONFIG = {
    scannerCheckInterval: 1000, // Poll every 1 second
    sessionExpiry: 3600000,     // Session expires in 1 hour
};
```

### Supported Barcode Formats
- QR Code
- Code 128
- Code 39
- EAN-13 / EAN-8
- UPC-A / UPC-E
- ITF
- Codabar

## 🔒 Security Considerations

1. **Session Validation**: All sessions validated before accepting scans
2. **CSRF Protection**: Form submissions protected by CSRF tokens
3. **Input Sanitization**: All scanned codes sanitized before storage
4. **Session Expiry**: Auto-expire after 1 hour of inactivity
5. **Admin Only**: Scanner features require admin authentication

## 🧹 Maintenance

### Cleanup Old Sessions
Run periodically via cron or manually:
```
GET /admin/api/scan_endpoints.php?action=cleanup
```

This will:
- Delete scans older than 24 hours
- Remove expired sessions
- Deactivate inactive sessions (10+ minutes)

## 🐛 Troubleshooting

### Camera Not Working on Mobile
- Ensure HTTPS (required for camera API)
- Grant camera permission when prompted
- Try switching between front/back camera

### Scans Not Appearing on PC
- Check if session is still active (not expired)
- Verify network connectivity
- Check browser console for errors
- Ensure polling is running (check Network tab)

### QR Code Not Generating
- Make sure QRious library is loaded
- Check JavaScript console for errors

## 📱 Mobile Scanner Features

- **Multi-format Support**: Barcodes, QR codes, IMEI
- **Torch/Flash**: Toggle flashlight for dark conditions  
- **Camera Switch**: Front/back camera toggle
- **Manual Entry**: Type code if scanning fails
- **History**: View recent scans
- **Audio Feedback**: Beep sound on successful scan
- **Haptic Feedback**: Vibration on scan (if supported)

## 🎨 UI Components

The add-product page features:
- **Tabbed Interface**: General, Pricing, Inventory, Attributes, Media
- **Real-time Profit Calculator**: Auto-calculate margins
- **Dynamic Serial Fields**: Generate based on stock quantity
- **Image Upload**: Drag & drop with preview
- **Variation Builder**: Generate all attribute combinations
- **Toast Notifications**: Non-blocking success/error messages

## 📄 License

Part of TechHat Shop Inventory System.
