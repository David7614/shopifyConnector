# SAMBAai E-commerce Platform Connector

A robust integration system that connects e-commerce platforms (Shopify) to SAMBAai marketing automation platform by synchronizing customer, product, and order data through automated XML feed generation.

## Table of Contents

- [Overview](#overview)
- [How It Works](#how-it-works)
- [Architecture](#architecture)
- [Data Flow](#data-flow)
- [Supported Platforms](#supported-platforms)
- [Installation](#installation)
- [Configuration](#configuration)
- [Cron Jobs](#cron-jobs)
- [Troubleshooting](#troubleshooting)

## Overview

This connector acts as a middleware between e-commerce platforms and SAMBAai. It performs three key functions:

1. **Data Synchronization**: Fetches products, customers, and orders from connected stores via their APIs
2. **Local Storage**: Stores synchronized data in a local MySQL database
3. **XML Generation**: Generates standardized XML feeds accessible by SAMBAai for marketing automation

## How It Works

### High-Level Workflow

```
E-commerce Store API → Connector (Fetch & Store) → Database → XML Generator → XML Feeds → SAMBAai
```

### Detailed Process

#### 1. User Registration & Authentication

Users register their store by providing:
- Store domain/URL
- API credentials (Client ID, Client Secret, or Access Token)
- Shop type (Shopify)

The system stores these credentials in the `user` and `Session` tables and uses them to authenticate API requests.

#### 2. Queue-Based Data Synchronization

The connector uses a queue system (`xml_feed_queue` table) to manage data synchronization:

**Queue States:**
- `0` - PENDING: Waiting to execute
- `1` - RUNNING: Currently processing
- `2` - EXECUTED: Successfully completed
- `5` - MISSED: Skipped
- `99` - ERROR: Failed (error details stored in parameters)

**Queue Types:**
- `product` - Product synchronization
- `customer` - Customer synchronization  
- `order` - Order synchronization

**Two-Phase Queue System:**

The `prepareQueue` method (runs daily at 23:01) creates **TWO queue entries** per user for each data type, scheduled for the next 3 days:

**Phase 1: API Data Fetching** (First Queue Entry)
- **When**: Scheduled for each day (e.g., Day 1 at 00:00)
- **Parameters**: Empty `[]`
- **Process Type**: `'objects'`
- **Action**: Fetches data from Shopify API → Saves to local database
- **Triggered By**: `processData()` method in Feed classes

**Phase 2: XML Generation** (Second Queue Entry)  
- **When**: 10 minutes after Phase 1 (e.g., Day 1 at 00:10)
- **Parameters**: `['objects_done' => 1]`
- **Process Type**: `null`
- **Action**: Reads data from local database → Generates XML files
- **Triggered By**: `prepareXml()` and `createXml()` methods

**How It Works:**

```php
// In XmlGeneratorService::executeQueue()
$parameters = $queue->additionalParameters;
$processType = isset($parameters['objects_done']) ? null : 'objects';

// In ProductFeed::generate($processType)
if ($processType == 'objects') {
    return $this->processData();  // Phase 1: Shopify API → Database
}
// Otherwise Phase 2: Database → XML files
```

**Why Two Phases?**

1. **Data Consistency**: Ensures ALL data is fetched from the API before XML generation begins
2. **Performance**: Separates heavy API operations from file generation
3. **Reliability**: If API fetching takes longer than expected, XML generation waits
4. **Error Isolation**: API failures don't corrupt existing XML files

**Special Case**: For Shoper platform (non-customer feeds), only Phase 1 is created as Shoper handles XML generation differently.

#### 3. Data Fetching from Store APIs

For each queue item, the system:

**Shopify Integration:**
- Uses Shopify GraphQL API (version 2025-07)
- Fetches data in batches (configurable page size)
- Implements cursor-based pagination
- Supports incremental updates (fetches only data updated since last sync)
- Handles GraphQL queries for:
  - Products (with variants, metafields, images, pricing, inventory)
  - Customers (with addresses, contact info, marketing consent)
  - Orders (with line items, fulfillments, financial status)

**Process Flow:**
1. Retrieve user's session/access token
2. Initialize API client with credentials
3. Check queue constraints (active user, enabled feeds)
4. Determine export type (full or incremental)
5. Fetch data page by page
6. Transform API response to local data model
7. Save to database
8. Update queue pagination info
9. Repeat until all pages processed

#### 4. Local Database Storage

Data is normalized and stored in MySQL tables:

**Products Table:**
```
- PRODUCT_ID: Unique product identifier
- TITLE: Product name
- DESCRIPTION: Product description
- PRICE: Current price
- PRICE_BEFORE_DISCOUNT: Original price
- URL: Product page URL
- IMAGE: Featured image URL
- BRAND: Vendor/manufacturer
- CATEGORYTEXT: Product category path
- STOCK: Inventory quantity
- VARIANT: Serialized variant data (sizes, colors, etc.)
- PARAMETERS: Serialized metafields/attributes
- user_id: Owner reference
- params_hash: Change detection hash
```

**Customers Table:**
```
- CUSTOMER_ID: Unique customer identifier
- EMAIL: Contact email
- FIRSTNAME, LASTNAME: Name
- PHONE: Phone number
- ADDRESS, CITY, POSTCODE, REGION, COUNTRY
- MARKETING_CONSENT: Email subscription status
- user_id: Owner reference
```

**Orders Table:**
```
- ORDER_ID: Unique order identifier
- CUSTOMER_ID: Associated customer
- DATE: Order date
- TOTAL_PRICE: Order total
- CURRENCY: Currency code
- STATUS: Order status
- PRODUCTS: Serialized line items
- user_id: Owner reference
```

#### 5. XML Feed Generation

The system uses a **two-phase queue approach** to ensure data consistency:

**Phase 1: API Data Synchronization** (Queue Entry with empty parameters)
- **Trigger**: Cron runs every 10 minutes
- **Process**: `processType = 'objects'` → calls `processData()`
- **Action**: 
  - Fetches fresh data from Shopify/Shoper/Idosell APIs
  - Transforms API responses to local data models
  - Saves/updates records in MySQL database
  - Handles pagination and incremental updates
- **Frequency**: Continuous (every 10 minutes via bash scripts)
- **Status**: Marks queue as EXECUTED when all pages fetched

**Phase 2: XML File Generation** (Queue Entry with `['objects_done' => 1]`)
- **Trigger**: Scheduled 10 minutes after Phase 1
- **Process**: `processType = null` → calls `prepareXml()` / `createXml()`
- **Action**:
  - Reads synchronized data from local database
  - Applies user configuration filters
  - Generates XML chunks (100 records per page)
  - Merges chunks into final XML file
- **Output**: `modules/xml_generator/src/feeds/{type}/{user_uuid}/{type}.xml`

**XML Generation Steps (Phase 2):**
1. Query database for user's records (paginated, 100 per page)
2. For each record, call `{Type}Xml::getEntity()` to transform to XML
3. Apply XML sanitization via `SambaHelper::sanitizeForXml()`
4. Append XML strings to temporary file (`{type}.xml.tmp`)
5. After all pages processed, wrap content in root tags
6. Save as final XML file (`{type}.xml`)
7. Delete temporary file

**Example Product XML Structure:**
```xml
<PRODUCTS>
  <PRODUCT>
    <PRODUCT_ID>12345</PRODUCT_ID>
    <URL>https://store.example.com/products/item</URL>
    <TITLE>Product Name</TITLE>
    <PRICE>99.99</PRICE>
    <IMAGE>https://cdn.example.com/image.jpg</IMAGE>
    <DESCRIPTION>Product description</DESCRIPTION>
    <BRAND>Brand Name</BRAND>
    <STOCK>10</STOCK>
    <PRICE_BEFORE_DISCOUNT>129.99</PRICE_BEFORE_DISCOUNT>
    <CATEGORYTEXT>Category > Subcategory</CATEGORYTEXT>
    <PARAMETERS>
      <PARAMETER>
        <NAME>Color</NAME>
        <VALUE>Red</VALUE>
      </PARAMETER>
    </PARAMETERS>
    <VARIANT>
      <PRODUCT_ID>12345-1</PRODUCT_ID>
      <TITLE>Product Name - Small</TITLE>
      <PRICE>99.99</PRICE>
      <URL>https://store.example.com/products/item?variant=1</URL>
    </VARIANT>
  </PRODUCT>
</PRODUCTS>
```

#### 6. Feed Access & Distribution

XML feeds are accessible via HTTP:

**Endpoint Pattern:**
```
https://your-domain.com/xml/{user_uuid}/{type}.xml
```

**Feed Status API:**
```
GET /feed/index?id={user_uuid}

Returns:
{
  "products": {
    "status": "Ready",
    "url": "https://domain.com/xml/{uuid}/products.xml",
    "current": "150",
    "all": "150"
  },
  "customers": {...},
  "orders": {...}
}
```

SAMBAai polls these endpoints to retrieve updated data for marketing campaigns.

## Architecture

### Tech Stack

- **Framework**: Yii2 (PHP 7.0+)
- **Database**: MySQL/MariaDB
- **API Clients**: 
  - PHPShopify SDK for Shopify
- **Task Scheduling**: Cron jobs
- **Data Format**: XML

### Directory Structure

```
shopify/
├── commands/                    # CLI controllers
│   ├── XmlGeneratorController.php
│   └── XmlGeneratorService.php
├── config/                      # Application configuration
│   ├── db.php                  # Database config
│   └── web.php                 # Web application config
├── controllers/                 # Web controllers
│   ├── AuthorizationController.php
│   ├── FeedController.php
│   └── SiteController.php
├── models/                      # Database models
│   ├── User.php
│   ├── Product.php
│   ├── Customers.php
│   ├── Orders.php
│   ├── Queue.php
│   └── Session.php
├── modules/
│   ├── shopify/                # Shopify integration
│   │   ├── ApiClient.php
│   │   ├── ProductFeed.php
│   │   ├── CustomerFeed.php
│   │   ├── OrderFeed.php
│   │   └── models/
│   │       ├── Product.php     # Shopify product transformer
│   │       ├── ProductXml.php  # XML generator for products
│   │       ├── Customer.php
│   │       ├── CustomerXml.php
│   │       ├── Order.php
│   │       └── OrderXml.php
│   ├── shoper/                 # Shoper integration
│   ├── idosellv3/              # Idosell integration
│   └── xml_generator/          # XML generation module
│       ├── src/
│       │   ├── XmlFeed.php     # Base feed generator
│       │   └── feeds/          # Generated XML files
│       └── helper/
│           └── SambaHelper.php # XML sanitization utilities
├── integration-bash-*.sh       # Cron scripts for data sync
└── yii                         # CLI entry point
```

### Key Components

#### XmlFeed (Base Class)
Central orchestrator that:
- Manages feed type routing
- Handles file path generation
- Coordinates generation phases

#### Platform-Specific Feeds (ProductFeed, CustomerFeed, OrderFeed)
Each implements:
- `processData()`: Fetches from API → saves to database
- `prepareXml()`: Reads database → generates XML chunks
- `createXml()`: Merges chunks into final file

#### Model Transformers (Product, Customer, Order in modules/shopify/models/)
- Extract data from API responses
- Map platform fields to standardized schema
- Handle edge cases and data validation
- `prepareFromApi()`: Save transformed data to database

#### XmlEntity Generators (ProductXml, CustomerXml, OrderXml)
- Read database records
- Apply user configuration filters
- Generate XML strings
- Sanitize output

## Data Flow

### Complete Synchronization Cycle

```
┌─────────────────────────────────────────────────────────────────┐
│                    CRON: Every 10 minutes                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Queue Preparation (Daily 23:01)                                │
│  php yii xml-generator/prepare-queue                            │
│  - Creates TWO queue entries per user per type for next 3 days  │
│                                                                  │
│  Queue Entry 1: parameters = []                                 │
│  → Triggers Phase 1 (API Fetch)                                 │
│                                                                  │
│  Queue Entry 2: parameters = ['objects_done' => 1], +10 min     │
│  → Triggers Phase 2 (XML Generation)                            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  PHASE 1: Data Fetching (integration-bash-*.sh every 10 min)   │
│  php yii xml-generator/generate-products                        │
│  php yii xml-generator/generate-customers                       │
│  php yii xml-generator/generate-orders                          │
│                                                                  │
│  Processes queue entries where parameters = []                  │
│  → processType = 'objects'                                      │
│  → Calls processData() method                                   │
│                                                                  │
│  For each queue item:                                           │
│  1. Get user session/credentials                                │
│  2. Initialize API client                                       │
│  3. Fetch data page (GraphQL/REST)                              │
│  4. Transform API response → local model                        │
│  5. Save to database                                            │
│  6. Update queue pagination                                     │
│  7. Mark complete or advance to next page                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  Local Database (MySQL)                                         │
│  - product (products with variants & parameters)                │
│  - customers (customer profiles)                                │
│  - orders (order history with line items)                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  PHASE 2: XML Generation (runs 10 min after Phase 1)           │
│                                                                  │
│  Processes queue entries where parameters = ['objects_done'=>1] │
│  → processType = null                                           │
│  → Calls prepareXml() / createXml() methods                     │
│                                                                  │
│  1. Read from database (paginated)                              │
│  2. Apply user filters (enabled fields)                         │
│  3. Generate XML entities                                       │
│  4. Append to temp file                                         │
│  5. Merge into final .xml file                                  │
│  6. Store in:                                                   │
│     modules/xml_generator/src/feeds/{type}/{uuid}/{type}.xml    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  HTTP Access                                                    │
│  https://domain.com/xml/{uuid}/products.xml                     │
│  https://domain.com/xml/{uuid}/customers.xml                    │
│  https://domain.com/xml/{uuid}/orders.xml                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                         SAMBAai Platform
```

## Supported Platforms

### Shopify
- **API**: GraphQL API (v2025-07)
- **Authentication**: Access Token (stored in Session table)
- **Features**:
  - Product variants and metafields
  - Customer marketing consent
  - Order fulfillment tracking
  - Incremental sync support
  - Taxonomy/category resolution

## Installation

### Prerequisites

```bash
- PHP >= 7.0
- MySQL/MariaDB
- Composer
- Web server (Apache/Nginx)
- Cron access
```

### Setup Steps

1. **Install Dependencies**
```bash
composer install
```

2. **Configure Database**
```bash
# Copy example config
cp config/example_db.php config/db.php

# Edit config/db.php with your database credentials
```

3. **Run Migrations**
```bash
php yii migrate
```

4. **Set Permissions**
```bash
chmod -R 777 runtime/
chmod -R 777 web/assets/
chmod 755 yii
```

5. **Configure Web Server**

**Apache:**
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/shopify/web
    
    <Directory /path/to/shopify/web>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/shopify/web;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

6. **Create Required Directories**
```bash
mkdir -p modules/xml_generator/src/feeds/product
mkdir -p modules/xml_generator/src/feeds/customer
mkdir -p modules/xml_generator/src/feeds/order
```

## Configuration

### User Configuration Options

Users can configure which data fields to include in XML feeds via `UserConfig`:

**Product Fields:**
- `product_image` - Include product images
- `product_description` - Include descriptions
- `product_brand` - Include brand/vendor
- `product_stock` - Include inventory levels
- `product_price_before_discount` - Include original prices
- `product_category` - Include category paths
- `product_parameters` - Include metafields/attributes
- `product_variants` - Include product variants

**Export Type:**
- `export_type = 0` - Full export (all products every time)
- `export_type = 1` - Incremental export (only updated products)

**Other Settings:**
- `feed_enabled` - Enable/disable feed generation
- `data_language` - Language for taxonomy resolution (en, pl, etc.)

### Platform Configuration

**Shopify:**
- Configure in `modules/shopify/ApiClient.php`
- API version: 2025-07
- Requires: Shop URL + Access Token

## Cron Jobs

### Required Cron Configuration

Add to crontab (`crontab -e`):

```bash
# Prepare daily queue (creates queue entries for all users)
1 23 * * * /usr/bin/php /path/to/yii xml-generator/prepare-queue >/dev/null 2>&1

# Generate country data (if applicable)
* * * * * /usr/bin/php /path/to/yii xml-generator/generate-countries >/dev/null 2>&1

# Process general integrations (categories, tags)
*/10 * * * * /bin/bash /home/yii/shopify.sambaai.pl/integration-bash-shopify-products.sh >/dev/null 2>&1
*/10 * * * * /bin/bash /home/yii/shopify.sambaai.pl/integration-bash-shopify-customers.sh >/dev/null 2>&1
*/10 * * * * /bin/bash /home/yii/shopify.sambaai.pl/integration-bash-shopify-orders.sh >/dev/null 2>&1
```

### Bash Script Structure

Each integration script (`integration-bash-*.sh`) runs for ~50 seconds, calling the appropriate generator command repeatedly:

```bash
#!/bin/bash
SECONDS=0
while (($SECONDS <= 50)); do
   /usr/bin/php /path/to/yii xml-generator/generate-products
   # Continues until 50 seconds elapsed
done
```

This ensures maximum processing within cron intervals while avoiding overlap.

## Troubleshooting

### Debugging Queue Issues

The integration relies on the `xml_feed_queue` table. To debug:

```sql
-- Find user's queue entries
SELECT * FROM xml_feed_queue 
WHERE current_integrate_user = {user_id}
ORDER BY next_integration_date DESC;

-- Check for errors
SELECT * FROM xml_feed_queue 
WHERE integrated = 99;  -- Error status

-- Reset stuck queue
UPDATE xml_feed_queue 
SET integrated = 0, page = 0 
WHERE id = {queue_id};
```

### Common Issues

**1. Queue stuck in RUNNING state**
```sql
UPDATE xml_feed_queue SET integrated = 0 WHERE integrated = 1;
```

**2. Feed not generating**
- Check `xml_feed_queue` for errors (integrated = 99)
- Verify user is active: `SELECT active FROM user WHERE id = {user_id}`
- Check for disabled feeds: `SELECT * FROM disabled_feeds WHERE user_id = {user_id}`

**3. Authentication errors**
- Verify credentials in `user` table (client_id, client_secret)
- Check `Session` table for valid access tokens
- Test API connection manually

**4. XML file missing**
- Check file permissions on `modules/xml_generator/src/feeds/`
- Verify cron jobs are running: `grep CRON /var/log/syslog`
- Check logs in `runtime/logs/`

**5. Empty or incomplete data**
- Verify queue max_page is set correctly
- Check integration date filters in `integration_data` table
- Review processType parameter (should be 'objects' for data fetching)

### Verification Tools

**Database Verification:**
```sql
-- Cross-reference user and accesstokens
SELECT u.id, u.username, u.email, s.shop, s.accessToken 
FROM user u 
LEFT JOIN Session s ON u.id = s.userId 
WHERE u.id = {user_id};
```

**Feed Status Check:**
```bash
# Check if XML exists
ls -lh modules/xml_generator/src/feeds/product/{uuid}/

# Count products in XML
grep -o "<PRODUCT>" modules/xml_generator/src/feeds/product/{uuid}/product.xml | wc -l

# Compare with database
mysql -e "SELECT COUNT(*) FROM product WHERE user_id = {user_id};"
```

**Force Integration:**
```bash
# Force specific queue record (use queue ID from xml_feed_queue table)
php yii xml-generator/generate-products {queue_id}

# Force specific queue with page override
php yii xml-generator/generate-products {queue_id} {page_number}

# Example: Find queue ID first
# SELECT id FROM xml_feed_queue WHERE current_integrate_user = {user_id} AND integration_type = 'product';
# Then: php yii xml-generator/generate-products 123
```

### Admin Panel

Access admin tools at `/adminer.php` for direct database inspection.

### Logs

Check application logs:
```bash
tail -f runtime/logs/app.log
tail -f logs/integration-log.txt
```

## Support

For issues or questions, review:
1. Queue status in `xml_feed_queue` table
2. Error messages in `parameters` column for failed queues
3. Application logs in `runtime/logs/`
4. Cron execution logs

---

**Built with Yii2 Framework**  
**License**: BSD-3-Clause

