# 🫖 Tea Commerce

Application Laravel de gestion de commerce avec import/export CSV, suivi des achats, produits, marges et synchronisation Shopify. Fonctionne dans un environnement Docker avec PostgreSQL, MinIO et Adminer.

---

## 🚀 Stack technique

- **Laravel 11**
- **PHP 8.2** (via Docker)
- **PostgreSQL**
- **MinIO** (stockage compatible S3)
- **Adminer** (interface base de données)
- **Bootstrap 5**
- **Docker & Docker Compose**

---

## 📦 Installation

### 1. Cloner le projet

```bash
git clone https://github.com/rtolotsoam/tea-commerce.git
cd tea-commerce
````

### 2. Copier le fichier d’environnement

```bash
cp .env.example .env
```

Puis, configure le fichier `.env` :

```env
APP_NAME=TeaCommerce
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=database
DB_PORT=5432
DB_DATABASE=tea_db
DB_USERNAME=postgres
DB_PASSWORD=postgres

FILESYSTEM_DISK=minio
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=local
AWS_ENDPOINT=http://minio:9000
```

---

## 🐳 Démarrer avec Docker

### 1. Lancer les services Docker

```bash
docker compose up --build -d
```

### 2. Installer les dépendances Laravel

```bash
docker compose exec app composer install
```

### 3. Générer la clé de l’application

```bash
docker compose exec app php artisan key:generate
```

### 4. Lancer les migrations & seeder

```bash
docker compose exec app php artisan migrate --seed
```

---

## 🔗 Accès rapide

| Service       | URL                                            |
| ------------- | ---------------------------------------------- |
| Application   | [http://localhost:8000](http://localhost:8000) |
| Adminer       | [http://localhost:8080](http://localhost:8080) |
| MinIO Console | [http://localhost:9001](http://localhost:9001) |

> **MinIO login** :
>
> * User : `minioadmin`
> * Password : `minioadmin`

---

## ✅ Fonctionnalités

* 📊 Tableau de bord des KPIs
* 📦 Gestion des produits & stocks
* 🧾 Import/export CSV (commandes, marges, stock)
* ☁️ Stockage S3 via MinIO
* 📁 Historique des exports
* 🔁 Synchronisation Shopify (en cours)
* 🤖 Module de scraping (optionnel)

---

## 📂 Structure personnalisée

* `app/Services/` → CSV, stockage, logique métiers
* `app/Http/Controllers/` → CRUD, dashboard, API
* `resources/views/` → Dashboard (Bootstrap)
* `routes/web.php` → Routes Laravel
* `Dockerfile` → Image Apache + PHP 8.2
* `docker-compose.yml` → Services (app, db, minio)

---

## 🧩 Schéma de la base de données

Voici un aperçu des principales tables et relations :

### 📦 `products`

* `id`
* `sku` (unique)
* `name`, `description`
* `category_id` → `categories(id)`
* `supplier_id` → `suppliers(id)`
* Divers champs Shopify (`shopify_product_id`, etc.)
* ✅ Index: `sku`, `supplier_id`, `shopify_*`

### 🧑‍💼 `suppliers`

* `id`
* `code` (unique)
* `name`, `email`, `phone`, `address`
* ✅ Index: `code`, `is_active`

### 📁 `categories`

* `id`
* `name`, `slug` (unique)
* `parent_id` → (auto-référentiel sur `categories`)
* ✅ Hiérarchie de catégories (sous-catégories)

### 🛒 `purchases`

* `id`
* `purchase_number` (unique)
* `supplier_id` → `suppliers(id)`
* `order_date`, `delivery_date`, `status`
* ✅ Index: `purchase_number`, `status`, `order_date`

### 📦 `purchase_items`

* `id`
* `purchase_id` → `purchases(id)`
* `product_id` → `products(id)`
* `quantity`, `unit_price`, `discounts`, `totals`
* ✅ Index: `purchase_id`, `product_id`

### ⚙️ `purchase_conditions`

* `id`
* `supplier_id`, `product_id`
* `quantity_min/max`, `unit_price`, `discounts`
* ✅ Index: `supplier_id`, `product_id`, `valid_from`, `valid_until`

### 📊 `margin_analysis`

* `id`
* `product_id` (unique) → `products(id)`
* `supplier_id` → `suppliers(id)`
* `purchase_price`, `selling_price`, `stock_quantity`
* Champs calculés :

  * `margin_amount = selling_price - purchase_price`
  * `margin_percent = ...`
  * `potential_profit = margin_amount * stock_quantity`
* ✅ Calculs auto via `TRIGGER`
* ✅ Index: `margin_percent`, `potential_profit`

### 📦 `stocks`

* `id`
* `product_id` (unique) → `products(id)`
* `quantity_on_hand`, `reserved`, `reorder_point`, `average_cost`
* ✅ Colonne générée : `quantity_available`
* ✅ Index: `reorder_point`, `quantity_available`

### 🔄 `stock_movements`

* Historique des entrées/sorties :

  * `movement_type` : `in`, `out`, `adjustment`
  * `reference_type`, `reference_id`
  * `balance_before`, `balance_after`
* ✅ Index: `product_id`, `reference_type`, `created_at`

### 💶 `shopify_prices`

* Lié à Shopify :

  * `product_id`
  * `shopify_product_id`, `variant_id`
  * `selling_price`, `compare_at_price`, `currency`
* ✅ Unique: `(product_id, shopify_product_id, shopify_variant_id)`
* ✅ Index: `last_sync_at`

### 🕸️ `scraped_data`

* Données issues de scraping/API :

  * `supplier_id`, `product_id`, `source_url`
  * `price`, `availability`, `raw_data`
* ✅ Index: `supplier_id`, `scraped_at`

### ⚙️ Triggers & fonctions PostgreSQL

* `update_margin_analysis_calculated_columns()` : met à jour automatiquement les champs `margin_*` à l’INSERT/UPDATE
* `update_stock_after_delivery()` : applique automatiquement les mouvements et maj des stocks après la livraison d’une commande

---

![Schéma de la base de données](./db_schema.svg)

---

# Formats CSV pour Import/Export

## 1. CSV Import Commandes Fournisseurs

### Format : `import_purchases.csv`
```csv
purchase_number,supplier_code,order_date,delivery_date,product_sku,quantity,unit_price,discount_percent,tax_rate
PO-2025-001,SUPP001,2025-07-01,2025-07-15,TEA-003,50,45.00,5,20
PO-2025-001,SUPP001,2025-07-01,2025-07-15,INF-001,30,12.50,0,20
PO-2025-002,SUPP002,2025-07-02,2025-07-20,TEA-002,100,38.50,7,20
PO-2025-003,SUPP003,2025-07-03,2025-07-10,TEA-001,25,65.00,0,20
```

### Colonnes :
- `purchase_number` : Numéro unique de commande
- `supplier_code` : Code fournisseur
- `order_date` : Date de commande (YYYY-MM-DD)
- `delivery_date` : Date de livraison prévue
- `product_sku` : Référence produit
- `quantity` : Quantité commandée
- `unit_price` : Prix unitaire HT
- `discount_percent` : Remise en %
- `tax_rate` : Taux de TVA

## 2. CSV Export Rapport d'Achats avec Marges

### Format : `export_purchase_margins.csv`
```csv
purchase_number,order_date,supplier_name,product_sku,product_name,quantity,purchase_price,selling_price,unit_margin,total_margin,margin_percent,status
PO-2025-001,2025-07-01,Les Thés de Chine,TEA-003,Thé Vert Gunpowder Bio,50,42.75,89.90,47.15,2357.50,52.46%,delivered
PO-2025-001,2025-07-01,Les Thés de Chine,INF-001,Infusion Camomille,30,12.50,24.90,12.40,372.00,49.80%,delivered
PO-2025-002,2025-07-02,Ceylon Tea Import,TEA-002,Thé Noir Ceylon OP,100,35.81,75.00,39.19,3919.00,52.25%,ordered
PO-2025-003,2025-07-03,Japan Green Tea Co,TEA-001,Thé Vert Sencha Premium,25,65.00,129.90,64.90,1622.50,49.96%,ordered
```

## 3. CSV Import/Update Produits

### Format : `products.csv`
```csv
sku,name,category,supplier_code,supplier_ref,unit_weight,unit_type,min_order_qty,shopify_product_id,active
TEA-001,Thé Vert Sencha Premium,thes-verts,SUPP003,JGT-SEN-001,0.1,kg,5,7854321098765,1
TEA-002,Thé Noir Ceylon OP,thes-noirs,SUPP002,CTI-CEY-OP1,0.1,kg,10,7854321098766,1
TEA-003,Thé Vert Gunpowder Bio,thes-verts,SUPP001,TDC-GUN-BIO,0.1,kg,20,7854321098767,1
TEA-004,Thé Blanc Pai Mu Tan,thes-blancs,SUPP001,TDC-PMT-001,0.05,kg,10,7854321098768,1
INF-001,Infusion Camomille,infusions,SUPP001,TDC-CAM-001,0.05,kg,10,7854321098769,1
```

## 4. CSV Import Conditions d'Achat (Tarifs Dégressifs)

### Format : `purchase_conditions.csv`
```csv
supplier_code,product_sku,qty_min,qty_max,unit_price,discount_percent,valid_from,valid_until
SUPP001,TEA-003,0,49,45.00,0,2025-01-01,2025-12-31
SUPP001,TEA-003,50,99,45.00,5,2025-01-01,2025-12-31
SUPP001,TEA-003,100,,45.00,10,2025-01-01,2025-12-31
SUPP002,TEA-002,0,99,38.50,0,2025-01-01,2025-12-31
SUPP002,TEA-002,100,,38.50,7,2025-01-01,2025-12-31
```

## 5. CSV Export Analyse des Stocks

### Format : `stock_analysis.csv`
```csv
sku,product_name,supplier,on_hand,reserved,available,reorder_point,last_purchase_date,avg_cost,current_value
TEA-001,Thé Vert Sencha Premium,Japan Green Tea Co,125,20,105,50,2025-06-15,65.00,8125.00
TEA-002,Thé Noir Ceylon OP,Ceylon Tea Import,280,45,235,100,2025-06-20,36.75,10290.00
TEA-003,Thé Vert Gunpowder Bio,Les Thés de Chine,450,80,370,200,2025-06-25,43.50,19575.00
INF-001,Infusion Camomille,Les Thés de Chine,180,30,150,50,2025-06-10,12.25,2205.00
```

## 6. CSV Données Scrapées/API

### Format : `scraped_prices.csv`
```csv
supplier_code,supplier_ref,product_name,price,currency,availability,stock_qty,source_url,scraped_at
SUPP001,TDC-GUN-BIO,Thé Vert Gunpowder Bio,44.50,EUR,En stock,500,https://supplier1.com/products/gun-bio,2025-07-02 08:00:00
SUPP002,CTI-CEY-OP1,Ceylon OP1,37.80,EUR,Stock limité,50,https://supplier2.com/teas/ceylon-op1,2025-07-02 08:15:00
SUPP003,JGT-SEN-001,Sencha Premium Grade,64.00,EUR,En stock,200,https://api.supplier3.com/products/sencha,2025-07-02 08:30:00
```

## 7. CSV Rapport Global des Marges

### Format : `margin_report.csv`
```csv
category,product_sku,product_name,stock_qty,purchase_price,selling_price,margin_amount,margin_percent,potential_profit
thes-verts,TEA-001,Thé Vert Sencha Premium,105,65.00,129.90,64.90,49.96%,6814.50
thes-noirs,TEA-002,Thé Noir Ceylon OP,235,35.81,75.00,39.19,52.25%,9209.65
thes-verts,TEA-003,Thé Vert Gunpowder Bio,370,42.75,89.90,47.15,52.46%,17445.50
infusions,INF-001,Infusion Camomille,150,12.50,24.90,12.40,49.80%,1860.00
TOTAL,,,,,,,,35329.65
```

## Notes sur les formats :

1. **Encodage** : UTF-8 avec BOM pour Excel
2. **Séparateur** : Virgule (,) ou point-virgule (;) selon la locale
3. **Décimales** : Point (.) comme séparateur décimal
4. **Dates** : Format ISO 8601 (YYYY-MM-DD)
5. **Booléens** : 0/1 ou true/false
6. **Champs vides** : Autorisés pour les valeurs NULL

## Exemple de validation des données à l'import :

```php
// Règles de validation Laravel
$rules = [
    'purchase_number' => 'required|unique:purchases,purchase_number',
    'supplier_code' => 'required|exists:suppliers,code',
    'product_sku' => 'required|exists:products,sku',
    'quantity' => 'required|numeric|min:0.01',
    'unit_price' => 'required|numeric|min:0',
    'order_date' => 'required|date_format:Y-m-d',
    'delivery_date' => 'nullable|date_format:Y-m-d|after:order_date'
];
```
