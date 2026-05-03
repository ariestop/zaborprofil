# Catalog

Базовый catalog core для будущего commerce-слоя.

Реализовано:

- `Category` — дерево категорий с ручным `path`, `slug`, сортировкой и active-флагом.
- `Product` — товар с `category`, `path`, `status`, описаниями и SEO-ready `isIndexable`.
- `Variant` — SKU-вариант товара с ценой в minor units (`priceCents`) и валютой.
- Admin API:
  - `GET/POST /admin/api/catalog/categories`;
  - `PUT/DELETE /admin/api/catalog/categories/{id}`;
  - `GET/POST /admin/api/catalog/products`;
  - `PUT/DELETE /admin/api/catalog/products/{id}`;
  - `POST /admin/api/catalog/products/{productId}/variants`;
  - `PUT/DELETE /admin/api/catalog/variants/{id}`.
- Public SSR:
  - `GET /catalog/`;
  - `GET /catalog/{path}` для активных категорий и опубликованных товаров.
- SEO:
  - product meta description / canonical / OpenGraph поля;
  - JSON-LD `Product` с `Offer` по активным вариантам;
  - опубликованные indexable товары попадают в `/sitemap.xml`.

Не входит в этот slice:

- корзина и заказ;
- складские остатки и доставка;
- payments;
- Public API v1.
